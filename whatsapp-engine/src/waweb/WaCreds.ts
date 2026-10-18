import crypto from 'crypto';
import fs from 'fs';
import path from 'path';
import { Curve25519 } from '../crypto/Curve25519.js';
import { Signature } from '../crypto/Signature.js';
import type { IKeyPair } from '../protocol/proto/WaProto.js';

export interface ISignedPreKey {
  keyId: number;
  keyPair: IKeyPair;
  signature: Buffer;
}

export interface SignalIdentity {
  identifier: { name: string; deviceId: number };
  identifierKey: Buffer;
}

export interface WaCreds {
  noiseKey: IKeyPair;
  pairingEphemeralKeyPair: IKeyPair;
  signedIdentityKey: IKeyPair;
  signedPreKey: ISignedPreKey;
  registrationId: number;
  advSecretKey: string; // base64
  nextPreKeyId: number;
  firstUnuploadedPreKeyId: number;
  accountSigKey?: Buffer;
  account?: unknown;
  me?: { id: string; name?: string; lid?: string };
  signalIdentities?: SignalIdentity[];
  platform?: string;
  routingInfo?: Buffer;
  pairingCode?: string;
  registered?: boolean;
  /** Uploaded one-time pre-keys, keyed by preKeyId — needed to accept inbound sessions */
  preKeys?: Record<string, IKeyPair>;
  lastAccountSyncTimestamp?: number;
  myAppStateKeyId?: string;
  /** Signal double-ratchet session records, keyed by remote jid */
  signalSessions?: Record<string, unknown>;
  /** Sender-key (group/broadcast) ratchet state, keyed "groupJid::authorJid" */
  signalSenderKeys?: Record<string, unknown>;
}

export const generateRegistrationId = (): number =>
  Uint16Array.from(crypto.randomBytes(2))[0]! & 16383;

/** Convert our internal {privateKey, publicKey} pair to the WA {private, public} shape. */
export const toWaKeyPair = (kp: { privateKey: Buffer; publicKey: Buffer }): IKeyPair => ({
  private: kp.privateKey,
  public: kp.publicKey,
});

export const generateWaKeyPair = (): IKeyPair => toWaKeyPair(Curve25519.generateKeyPair());

export const signedKeyPair = (identityKeyPair: IKeyPair, keyId: number): ISignedPreKey => {
  const preKey = generateWaKeyPair();
  const pubKey = Signature.prefixedPubKey(preKey.public);
  const signature = Signature.sign(identityKeyPair.private, pubKey);
  return { keyPair: preKey, signature, keyId };
};

export const initAuthCreds = (): WaCreds => {
  const identityKey = generateWaKeyPair();
  return {
    noiseKey: generateWaKeyPair(),
    pairingEphemeralKeyPair: generateWaKeyPair(),
    signedIdentityKey: identityKey,
    signedPreKey: signedKeyPair(identityKey, 1),
    registrationId: generateRegistrationId(),
    advSecretKey: crypto.randomBytes(32).toString('base64'),
    nextPreKeyId: 1,
    firstUnuploadedPreKeyId: 1,
    signalIdentities: [],
  };
};

/** JSON (de)serialization that round-trips Buffers — mirrors BufferJSON. */
export const BufferJSON = {
  replacer: (_k: string, value: unknown): unknown => {
    if (
      Buffer.isBuffer(value) ||
      value instanceof Uint8Array ||
      (value as { type?: string })?.type === 'Buffer'
    ) {
      return {
        type: 'Buffer',
        data: Buffer.from((value as { data?: number[] })?.data || (value as Buffer)).toString(
          'base64',
        ),
      };
    }
    return value;
  },

  reviver: (_k: string, value: unknown): unknown => {
    const v = value as { type?: string; data?: string } | null;
    if (typeof v === 'object' && v !== null && v.type === 'Buffer' && typeof v.data === 'string') {
      return Buffer.from(v.data, 'base64');
    }
    return value;
  },
};

/**
 * File-backed credential store — one JSON file per session,
 * written with 0600 permissions inside the engine data dir.
 */
export class FileCredsStore {
  private readonly dir: string;

  constructor(baseDir?: string) {
    this.dir =
      baseDir ||
      path.resolve(process.env.ENGINE_DATA_DIR || path.join(process.cwd(), 'data'), 'creds');
    fs.mkdirSync(this.dir, { recursive: true, mode: 0o700 });
  }

  private fileFor(sessionId: string): string {
    const safe = sessionId.replace(/[^a-zA-Z0-9_-]/g, '_');
    return path.join(this.dir, `${safe}.json`);
  }

  public load(sessionId: string): WaCreds | null {
    const file = this.fileFor(sessionId);
    if (!fs.existsSync(file)) {
      return null;
    }
    const raw = fs.readFileSync(file, 'utf8');
    return JSON.parse(raw, BufferJSON.reviver) as WaCreds;
  }

  public save(sessionId: string, creds: WaCreds): void {
    const file = this.fileFor(sessionId);
    const tmp = `${file}.tmp`;
    fs.writeFileSync(tmp, JSON.stringify(creds, BufferJSON.replacer), { mode: 0o600 });
    fs.renameSync(tmp, file);
  }

  public delete(sessionId: string): void {
    const file = this.fileFor(sessionId);
    if (fs.existsSync(file)) {
      fs.unlinkSync(file);
    }
  }

  /** List session ids whose stored credentials belong to a paired account. */
  public listPaired(): string[] {
    if (!fs.existsSync(this.dir)) return [];
    const ids: string[] = [];
    for (const f of fs.readdirSync(this.dir)) {
      if (!f.endsWith('.json')) continue;
      try {
        const creds = JSON.parse(
          fs.readFileSync(path.join(this.dir, f), 'utf8'),
          BufferJSON.reviver,
        ) as WaCreds;
        if (creds?.me?.id) {
          ids.push(f.slice(0, -5));
        }
      } catch {
        // skip unreadable creds
      }
    }
    return ids;
  }
}
