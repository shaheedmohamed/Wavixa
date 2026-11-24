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

export interface FlatRecord {
  /** 0 = SET, 1 = REMOVE (SyncdMutation.SyncdOperation). */
  operation: number;
  keyId?: Buffer;
  value?: Buffer;
  /** SyncdIndex.blob — the index MAC. Present even when value is absent (REMOVE). */
  indexMac?: Buffer;
  /** App-state collection this record came from ('regular_low', …). */
  collection?: string;
  /** Came from a snapshot (full state), not a patch delta. */
  fromSnapshot?: boolean;
}

export interface CollectionState {
  name: string;
  /** Highest snapshot/patch version seen inside this result. */
  version: number;
  /** Server says more patches are pending — request again. */
  hasMore: boolean;
  /** The result carried a snapshot — resets the local integrity state. */
  hadSnapshot: boolean;
}

export interface CollectResult {
  records: FlatRecord[];
  collections: CollectionState[];
}

export interface OutgoingMutation {
  /** Syncd index, e.g. ['markChatAsRead', jid]. */
  index: string[];
  /** Static per-action version SyncActionData.version carries (3 for markChatAsRead). */
  version: number;
  /** SyncActionValue fields — protobufjs-shaped object. */
  value: Record<string, unknown>;
}

export interface EncodedPatch {
  /** Serialized SyncdPatch — goes inside <collection><patch>. */
  patchBytes: Buffer;
  /** New collection version (old + 1). */
  version: number;
  /** New LtHash state after applying the mutations. */
  hash: Buffer;
  /** indexMac → valueMac for each mutation sent — merge into the tracker's map. */
  mutationMacs: { indexMac: Buffer; valueMac: Buffer }[];
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
  private static expandMutationKeys(keyData: Buffer): {
    index: Buffer;
    valueEnc: Buffer;
    valueMac: Buffer;
    snapshotMac: Buffer;
    patchMac: Buffer;
  } {
    const exp = Hkdf.derive(
      keyData,
      Buffer.alloc(32),
      Buffer.from(AppStateProcessor.MUTATION_KEYS_INFO, 'utf8'),
      160,
    );
    return {
      index: exp.subarray(0, 32),
      valueEnc: exp.subarray(32, 64),
      valueMac: exp.subarray(64, 96),
      snapshotMac: exp.subarray(96, 128),
      patchMac: exp.subarray(128, 160),
    };
  }

  private static valueEncryptionKey(keyData: Buffer): Buffer {
    return AppStateProcessor.expandMutationKeys(keyData).valueEnc;
  }

  // ------------------------------------------------------------------
  // LtHash ("WhatsApp Patch Integrity") — order-independent running hash
  // over mutation value MACs. Required so we can WRITE patches: the
  // server verifies snapshotMac/patchMac against its own copy.
  // ------------------------------------------------------------------

  private static readonly LT_HASH_INFO = 'WhatsApp Patch Integrity';
  public static readonly LT_HASH_SIZE = 128;

  /** Add (or subtract) each item's expanded hash into `base` in place. */
  public static ltHashOp(base: Buffer, items: Buffer[], subtract: boolean): void {
    for (const item of items) {
      const expanded = Hkdf.derive(
        item,
        Buffer.alloc(32),
        Buffer.from(AppStateProcessor.LT_HASH_INFO, 'utf8'),
        AppStateProcessor.LT_HASH_SIZE,
      );
      for (let i = 0; i < base.length; i += 2) {
        const x = base.readUInt16LE(i);
        const y = expanded.readUInt16LE(i);
        base.writeUInt16LE((subtract ? x - y : x + y) & 0xffff, i);
      }
    }
  }

  private static hmacSha256(key: Buffer, ...data: Buffer[]): Buffer {
    const h = crypto.createHmac('sha256', key);
    for (const d of data) h.update(d);
    return h.digest();
  }

  private static hmacSha512(key: Buffer, ...data: Buffer[]): Buffer {
    const h = crypto.createHmac('sha512', key);
    for (const d of data) h.update(d);
    return h.digest();
  }

  private static u64be(val: number): Buffer {
    const b = Buffer.alloc(8);
    b.writeBigUInt64BE(BigInt(val));
    return b;
  }

  /**
   * Encrypt and MAC a batch of SyncAction mutations into a SyncdPatch.
   * `getPrevValueMac(indexMac)` must return the value MAC of the previous
   * SET on the same index so its contribution is subtracted from the hash.
   */
  public static async encodePatch(opts: {
    collection: string;
    version: number;
    hash: Buffer;
    keyId: Buffer;
    keyData: Buffer;
    mutations: OutgoingMutation[];
    getPrevValueMac: (indexMac: Buffer) => Buffer | undefined;
  }): Promise<EncodedPatch> {
    const root = await loadWaProto();
    const dataT = root.lookupType('proto.SyncActionData');
    const patchT = root.lookupType('proto.SyncdPatch');
    const keys = AppStateProcessor.expandMutationKeys(opts.keyData);

    const newHash = Buffer.from(opts.hash);
    const added: Buffer[] = [];
    const removed: Buffer[] = [];
    const records: any[] = [];
    const mutationMacs: { indexMac: Buffer; valueMac: Buffer }[] = [];

    for (const m of opts.mutations) {
      const indexBytes = Buffer.from(JSON.stringify(m.index), 'utf8');
      const dataBytes = dataT.encode(
        dataT.fromObject({
          index: indexBytes,
          value: { ...m.value, timestamp: Date.now() },
          version: m.version,
        }),
      ).finish();

      const iv = crypto.randomBytes(16);
      const cipher = crypto.createCipheriv('aes-256-cbc', keys.valueEnc, iv);
      const enc = Buffer.concat([iv, cipher.update(Buffer.from(dataBytes)), cipher.final()]);

      // value MAC: SHA512(opByte | keyId | enc | keyDataLength)[:32]; op = SET(0)+1.
      const valueMac = AppStateProcessor.hmacSha512(
        keys.valueMac,
        Buffer.from([1]),
        opts.keyId,
        enc,
        AppStateProcessor.u64be(opts.keyId.length + 1),
      ).subarray(0, 32);
      const indexMac = AppStateProcessor.hmacSha256(keys.index, indexBytes);

      const prev = opts.getPrevValueMac(indexMac);
      if (prev) removed.push(prev);
      added.push(valueMac);
      mutationMacs.push({ indexMac, valueMac });
      records.push({
        operation: 0,
        record: {
          index: { blob: indexMac },
          value: { blob: Buffer.concat([enc, valueMac]) },
          keyId: { id: opts.keyId },
        },
      });
    }

    AppStateProcessor.ltHashOp(newHash, removed, true);
    AppStateProcessor.ltHashOp(newHash, added, false);

    const version = opts.version + 1;
    const collectionBytes = Buffer.from(opts.collection, 'utf8');
    const snapshotMac = AppStateProcessor.hmacSha256(
      keys.snapshotMac,
      newHash,
      AppStateProcessor.u64be(version),
      collectionBytes,
    );
    const patchMac = AppStateProcessor.hmacSha256(
      keys.patchMac,
      snapshotMac,
      ...added,
      AppStateProcessor.u64be(version),
      collectionBytes,
    );

    const patchBytes = Buffer.from(
      patchT.encode(
        patchT.fromObject({
          mutations: records,
          snapshotMac,
          patchMac,
          keyId: { id: opts.keyId },
        }),
      ).finish(),
    );

    return { patchBytes, version, hash: newHash, mutationMacs };
  }

  /** SyncdValue.blob layout: [iv(16) | AES-256-CBC ciphertext | valueMac(32)] → SyncActionData. */
  private static decryptRecordValue(blob: Buffer | undefined, encKey: Buffer): Buffer | null {
    if (!blob || blob.length <= 48) return null;
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
  public static async collectRecords(node: BinaryNode): Promise<CollectResult> {
    const root = await loadWaProto();
    const patchT = root.lookupType('proto.SyncdPatch');
    const snapT = root.lookupType('proto.SyncdSnapshot');
    const mutsT = root.lookupType('proto.SyncdMutations');
    const toObj = { bytes: Buffer, longs: Number, defaults: false } as const;

    // iq result wraps collections in <sync>; pushed deltas carry them bare.
    const sync = getBinaryNodeChild(node, 'sync');
    const collectionNodes = sync
      ? getBinaryNodeChildren(sync, 'collection')
      : getBinaryNodeChildren(node, 'collection');

    const records: FlatRecord[] = [];
    const collections: CollectionState[] = [];

    for (const coll of collectionNodes) {
      const name = String(coll.attrs?.name || '');
      let version = 0;
      let hadSnapshot = false;
      const pushRecord = (operation: number, rec: any, fallbackKeyId?: Buffer, fromSnapshot = false) => {
        const keyId = rec?.keyId?.id ?? fallbackKeyId;
        records.push({
          operation,
          keyId: keyId != null ? Buffer.from(keyId) : undefined,
          value: rec?.value?.blob ? Buffer.from(rec.value.blob) : undefined,
          indexMac: rec?.index?.blob ? Buffer.from(rec.index.blob) : undefined,
          collection: name,
          fromSnapshot,
        });
      };

      const snapNode = getBinaryNodeChild(coll, 'snapshot');
      if (snapNode?.content && Buffer.isBuffer(snapNode.content)) {
        try {
          const snap = snapT.toObject(snapT.decode(snapNode.content), toObj);
          const snapKeyId = snap?.keyId?.id != null ? Buffer.from(snap.keyId.id) : undefined;
          const snapVersion = Number(snap?.version?.version ?? 0);
          if (snapVersion > version) version = snapVersion;
          hadSnapshot = true;
          for (const rec of snap?.records || []) pushRecord(0, rec, snapKeyId, true);
        } catch (e) {
          logger.warn({ err: e, collection: coll.attrs?.name }, 'app-state snapshot decode failed');
        }
      }

      // Pushed deltas arrive as <collection><patch>… directly — resync
      // results wrap them in <collection><patches><patch>…. Take both.
      const patchesNode = getBinaryNodeChild(coll, 'patches');
      const patchNodes = [
        ...getBinaryNodeChildren(patchesNode, 'patch'),
        ...getBinaryNodeChildren(coll, 'patch'),
      ];
      for (const patchNode of patchNodes) {
        if (!Buffer.isBuffer(patchNode.content)) continue;
        let patch: any;
        try {
          patch = patchT.toObject(patchT.decode(patchNode.content), toObj);
        } catch {
          continue;
        }
        const patchVersion = Number(patch?.version?.version ?? 0);
        if (patchVersion > version) version = patchVersion;
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

      if (name) {
        collections.push({
          name,
          version,
          hasMore: coll.attrs?.has_more_patches === 'true',
          hadSnapshot,
        });
      }
    }

    return { records, collections };
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
  /** Same decode path as extractContacts but pulls MarkChatAsReadAction
   *  (index ["markChatAsRead", jid]) — "read on another device" patches. */
  public static async extractChatReads(
    records: FlatRecord[],
    appStateKeys: Map<string, Buffer>,
    dedupeInOrder = false,
  ): Promise<{ jid: string; read: boolean; lastMessageTimestamp?: number; collection?: string }[]> {
    const root = await loadWaProto();
    const dataT = root.lookupType('proto.SyncActionData');
    const toObj = { bytes: Buffer, longs: Number, defaults: false } as const;
    const encKeyCache = new Map<string, Buffer>();
    const out: { jid: string; read: boolean; lastMessageTimestamp?: number; collection?: string }[] = [];
    const byJid = new Map<string, { jid: string; read: boolean; lastMessageTimestamp?: number; collection?: string }>();
    const indexMacToJid = new Map<string, string>();

    for (const rec of records) {
      const idxHex = rec.indexMac ? rec.indexMac.toString('hex') : null;
      if (rec.operation === 1) {
        if (dedupeInOrder && idxHex) {
          const prevJid = indexMacToJid.get(idxHex);
          if (prevJid) byJid.delete(prevJid);
          indexMacToJid.delete(idxHex);
        }
        continue;
      }
      const keyIdB64 = rec.keyId ? rec.keyId.toString('base64') : null;
      const keyData = keyIdB64 ? appStateKeys.get(keyIdB64) : undefined;
      if (!keyData || !rec.value) continue;
      let encKey = encKeyCache.get(keyIdB64!);
      if (!encKey) {
        encKey = AppStateProcessor.valueEncryptionKey(keyData);
        encKeyCache.set(keyIdB64!, encKey);
      }
      const plain = AppStateProcessor.decryptRecordValue(rec.value, encKey);
      if (!plain) continue;
      let data: any;
      try {
        data = dataT.toObject(dataT.decode(plain), toObj);
      } catch { continue; }
      const action = data?.value?.markChatAsReadAction;
      if (!action) continue;
      let jid: string | undefined;
      try {
        const index = JSON.parse(Buffer.from(data.index).toString('utf8'));
        jid = typeof index?.[1] === 'string' ? index[1] : undefined;
      } catch { /* malformed */ }
      if (!jid) continue;
      jid = jid.replace(/:\d+@/, '@');
      if (jid.endsWith('@c.us')) jid = jid.replace(/@c\.us$/, '@s.whatsapp.net');
      else if (/^\d+$/.test(jid)) jid = `${jid}@s.whatsapp.net`;

      let ts = Number(
        action.messageRange?.lastMessageTimestamp ||
        action.messageRange?.lastSystemMessageTimestamp ||
        0,
      );
      if (ts > 100_000_000_000) ts = Math.floor(ts / 1000);
      if (!ts && data?.value?.timestamp) {
        const vts = Number(data.value.timestamp);
        ts = vts > 100_000_000_000 ? Math.floor(vts / 1000) : vts;
      }

      const entry = {
        jid,
        read: action.read !== false,
        lastMessageTimestamp: ts > 0 ? ts : undefined,
        collection: rec.collection,
      };
      if (dedupeInOrder) {
        if (idxHex) indexMacToJid.set(idxHex, jid);
        byJid.set(jid, entry);
      } else {
        out.push(entry);
      }
    }
    return dedupeInOrder ? [...byJid.values()] : out;
  }

  /** Same decode path for ArchiveChatAction (index ["archiveChat", jid]). */
  public static async extractChatArchives(
    records: FlatRecord[],
    appStateKeys: Map<string, Buffer>,
  ): Promise<{ jid: string; archived: boolean; collection?: string }[]> {
    const root = await loadWaProto();
    const dataT = root.lookupType('proto.SyncActionData');
    const toObj = { bytes: Buffer, longs: Number, defaults: false } as const;
    const encKeyCache = new Map<string, Buffer>();
    const out: { jid: string; archived: boolean; collection?: string }[] = [];
    for (const rec of records) {
      const keyIdB64 = rec.keyId ? rec.keyId.toString('base64') : null;
      const keyData = keyIdB64 ? appStateKeys.get(keyIdB64) : undefined;
      if (!keyData) continue;
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
      } catch { continue; }
      const action = data?.value?.archiveChatAction;
      if (!action) continue;
      let jid: string | undefined;
      try {
        const index = JSON.parse(Buffer.from(data.index).toString('utf8'));
        jid = typeof index?.[1] === 'string' ? index[1] : undefined;
      } catch { /* malformed */ }
      if (!jid) continue;
      if (jid.endsWith('@c.us')) jid = jid.replace(/@c\.us$/, '@s.whatsapp.net');
      else if (/^\d+$/.test(jid)) jid = `${jid}@s.whatsapp.net`;
      out.push({ jid, archived: action.archived !== false, collection: rec.collection });
    }
    return out;
  }

  /** Same decode path for PinChatAction (index ["pin", jid]). */
  public static async extractChatPins(
    records: FlatRecord[],
    appStateKeys: Map<string, Buffer>,
  ): Promise<{ jid: string; pinned: boolean; collection?: string }[]> {
    const root = await loadWaProto();
    const dataT = root.lookupType('proto.SyncActionData');
    const toObj = { bytes: Buffer, longs: Number, defaults: false } as const;
    const encKeyCache = new Map<string, Buffer>();
    const out: { jid: string; pinned: boolean; collection?: string }[] = [];
    for (const rec of records) {
      const keyIdB64 = rec.keyId ? rec.keyId.toString('base64') : null;
      const keyData = keyIdB64 ? appStateKeys.get(keyIdB64) : undefined;
      if (!keyData) continue;
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
      } catch { continue; }
      const action = data?.value?.pinAction;
      if (!action) continue;
      let jid: string | undefined;
      try {
        const index = JSON.parse(Buffer.from(data.index).toString('utf8'));
        jid = typeof index?.[1] === 'string' ? index[1] : undefined;
      } catch { /* malformed */ }
      if (!jid) continue;
      if (jid.endsWith('@c.us')) jid = jid.replace(/@c\.us$/, '@s.whatsapp.net');
      else if (/^\d+$/.test(jid)) jid = `${jid}@s.whatsapp.net`;
      out.push({ jid, pinned: action.pinned !== false, collection: rec.collection });
    }
    return out;
  }

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
