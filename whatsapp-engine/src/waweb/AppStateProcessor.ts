import crypto from 'crypto';
import axios from 'axios';
import zlib from 'zlib';
import {
  BinaryNode,
  getBinaryNodeChild,
  getBinaryNodeChildren,
} from '../protocol/binary/BinaryNode.js';
import { loadWaProto } from '../protocol/proto/WaProto.js';
import { Hkdf } from '../crypto/Hkdf.js';
import { MediaManager } from '../message/MediaManager.js';
import { logger } from '../config/Logger.js';

export interface SyncedContact {
  jid: string;
  name: string;
  firstName?: string;
}

export interface AppStateDecodeResult {
  contacts: SyncedContact[];
  /** keyIds (base64) referenced by mutations we could not decrypt. */
  missingKeyIds: string[];
}

interface FlatRecord {
  /** 0 = SET, 1 = REMOVE (SyncdMutation.SyncdOperation). */
  operation: number;
  keyId?: Buffer;
  value?: Buffer;
}

/**
 * Read-only decoder for WhatsApp app-state (syncd) collections. The primary
 * phone stores the owner's address book as encrypted ContactAction /
 * LidContactAction mutations; this extracts the saved names so the CRM can
 * label conversations with them instead of the sender's own pushname.
 *
 * Integrity MACs (value/patch/snapshot/LTHash) are deliberately not verified:
 * we never write app-state back, and a wrong key already fails at AES padding.
 */
export class AppStateProcessor {
  private static readonly MUTATION_KEYS_INFO = 'WhatsApp Mutation Keys';
  private static readonly APP_STATE_MEDIA_TYPE = 'appstate';

  /** HKDF(keyData, 160) → [index 32 | valueEnc 32 | valueMac 32 | snapshotMac 32 | patchMac 32]. */
  private static valueEncryptionKey(keyData: Buffer): Buffer {
    return Hkdf.derive(
      keyData,
      Buffer.alloc(32),
      Buffer.from(AppStateProcessor.MUTATION_KEYS_INFO, 'utf8'),
      160,
    ).subarray(32, 64);
  }

  /** SyncdValue.blob layout: [iv(16) | AES-256-CBC ciphertext | valueMac(32)] → SyncActionData. */
  private static decryptRecordValue(blob: Buffer, encKey: Buffer): Buffer | null {
    if (blob.length <= 48) return null;
    const enc = blob.subarray(0, blob.length - 32);
    try {
      const decipher = crypto.createDecipheriv('aes-256-cbc', encKey, enc.subarray(0, 16));
      return Buffer.concat([decipher.update(enc.subarray(16)), decipher.final()]);
    } catch {
      return null;
    }
  }

  /**
   * Flatten every SyncdRecord referenced by an app-state iq result (or a
   * <notification> carrying collection children) into an ordered list:
   * snapshot records first, then each patch's mutations in wire order.
   */
  public static async collectRecords(node: BinaryNode): Promise<FlatRecord[]> {
    const root = await loadWaProto();
    const patchT = root.lookupType('proto.SyncdPatch');
    const snapT = root.lookupType('proto.SyncdSnapshot');
    const mutsT = root.lookupType('proto.SyncdMutations');
    const toObj = { bytes: Buffer, longs: Number, defaults: false } as const;

    // iq result wraps collections in <sync>; pushed deltas carry them bare.
    const sync = getBinaryNodeChild(node, 'sync');
    const collections = sync
      ? getBinaryNodeChildren(sync, 'collection')
      : getBinaryNodeChildren(node, 'collection');

    const records: FlatRecord[] = [];
    const pushRecord = (operation: number, rec: any, fallbackKeyId?: Buffer) => {
      const value = rec?.value?.blob ? Buffer.from(rec.value.blob) : undefined;
      const keyId = rec?.keyId?.id ?? fallbackKeyId;
      if (!value) return;
      records.push({
        operation,
        keyId: keyId != null ? Buffer.from(keyId) : undefined,
        value,
      });
    };

    for (const coll of collections) {
      const snapNode = getBinaryNodeChild(coll, 'snapshot');
      if (snapNode?.content && Buffer.isBuffer(snapNode.content)) {
        try {
          const snap = snapT.toObject(snapT.decode(snapNode.content), toObj);
          const snapKeyId = snap?.keyId?.id != null ? Buffer.from(snap.keyId.id) : undefined;
          for (const rec of snap?.records || []) pushRecord(0, rec, snapKeyId);
        } catch (e) {
          logger.warn({ err: e, collection: coll.attrs?.name }, 'app-state snapshot decode failed');
        }
      }

      const patchesNode = getBinaryNodeChild(coll, 'patches');
      for (const patchNode of getBinaryNodeChildren(patchesNode, 'patch')) {
        if (!Buffer.isBuffer(patchNode.content)) continue;
        let patch: any;
        try {
          patch = patchT.toObject(patchT.decode(patchNode.content), toObj);
        } catch {
          continue;
        }
        const patchKeyId = patch?.keyId?.id != null ? Buffer.from(patch.keyId.id) : undefined;
        for (const m of patch?.mutations || []) {
          pushRecord(Number(m?.operation ?? 0), m?.record, patchKeyId);
        }
        // Large mutation batches are offloaded to the CDN as an encrypted
        // ExternalBlobReference holding a serialized SyncdMutations.
        if (patch?.externalMutations?.mediaKey && patch?.externalMutations?.directPath) {
          const ext = await AppStateProcessor.downloadExternalMutations(
            patch.externalMutations,
            mutsT,
            toObj,
          );
          for (const m of ext) {
            pushRecord(Number(m?.operation ?? 0), m?.record, patchKeyId);
          }
        }
      }
    }

    return records;
  }

  private static async downloadExternalMutations(
    ref: { mediaKey: Buffer | Uint8Array; directPath: string },
    mutsT: import('protobufjs').Type,
    toObj: { bytes: any; longs: any; defaults: boolean },
  ): Promise<any[]> {
    try {
      const url = `https://mmg.whatsapp.net${ref.directPath}`;
      const resp = await axios.get(url, {
        responseType: 'arraybuffer',
        timeout: 60_000,
        maxContentLength: 256 * 1024 * 1024,
      });
      const blob = MediaManager.decryptMedia(
        Buffer.from(resp.data),
        Buffer.from(ref.mediaKey),
        AppStateProcessor.APP_STATE_MEDIA_TYPE,
      );
      let decoded = blob;
      if (blob.length > 2 && blob[0] === 0x78) {
        try {
          decoded = zlib.inflateSync(blob);
        } catch {
          /* raw protobuf */
        }
      }
      const muts = mutsT.toObject(mutsT.decode(decoded), toObj);
      return muts?.mutations || [];
    } catch (e) {
      logger.warn({ err: e }, 'app-state external mutations download failed');
      return [];
    }
  }

  /**
   * Decrypt the value blob of every record and pull out ContactAction /
   * LidContactAction names. Records must be ordered (wire order) so later
   * SET/REPLACE mutations overwrite earlier state and REMOVE deletes it.
   */
  public static async extractContacts(
    records: FlatRecord[],
    appStateKeys: Map<string, Buffer>,
  ): Promise<AppStateDecodeResult> {
    const root = await loadWaProto();
    const dataT = root.lookupType('proto.SyncActionData');
    const toObj = { bytes: Buffer, longs: Number, defaults: false } as const;

    const byJid = new Map<string, SyncedContact>();
    const missing = new Set<string>();
    const encKeyCache = new Map<string, Buffer>();

    for (const rec of records) {
      const keyIdB64 = rec.keyId ? rec.keyId.toString('base64') : null;
      const keyData = keyIdB64 ? appStateKeys.get(keyIdB64) : undefined;
      if (!keyData) {
        // A record we cannot decrypt — remember its key so the caller can
        // ask the primary for an AppStateSyncKeyShare.
        if (keyIdB64) missing.add(keyIdB64);
        continue;
      }

      let encKey = encKeyCache.get(keyIdB64!);
      if (!encKey) {
        encKey = AppStateProcessor.valueEncryptionKey(keyData);
        encKeyCache.set(keyIdB64!, encKey);
      }

      const plain = AppStateProcessor.decryptRecordValue(rec.value!, encKey);
      if (!plain) continue;

      let data: any;
      try {
        data = dataT.toObject(dataT.decode(plain), toObj);
      } catch {
        continue;
      }
      const action = data?.value?.contactAction || data?.value?.lidContactAction;
      if (!action) continue;

      let jid: string | undefined;
      try {
        const index = JSON.parse(Buffer.from(data.index).toString('utf8'));
        jid = typeof index?.[1] === 'string' ? index[1] : undefined;
      } catch {
        /* malformed index blob */
      }
      if (!jid) continue;

      // Normalize c.us → s.whatsapp.net; a bare number is a PN identity.
      if (jid.endsWith('@c.us')) jid = jid.replace(/@c\.us$/, '@s.whatsapp.net');
      else if (/^\d+$/.test(jid)) jid = `${jid}@s.whatsapp.net`;

      const name = String(action.fullName || action.firstName || '').trim();
      if (rec.operation === 1 || !name) {
        byJid.delete(jid); // REMOVE — or a contact whose name was cleared
        continue;
      }
      byJid.set(jid, {
        jid,
        name,
        firstName: action.firstName ? String(action.firstName) : undefined,
      });
    }

    return { contacts: [...byJid.values()], missingKeyIds: [...missing] };
  }
}
