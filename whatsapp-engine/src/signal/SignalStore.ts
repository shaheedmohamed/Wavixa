import { Curve25519 } from '../crypto/Curve25519.js';
import type { IKeyPair } from '../protocol/proto/WaProto.js';
import type { WaCreds, ISignedPreKey } from '../waweb/WaCreds.js';
import { SessionRecord, type SessionRecordData, type SignalKeyPair } from './SessionTypes.js';

export const prefixKeyInPublicKey = (pubKey: Buffer): Buffer => {
  if (pubKey.length === 33) return pubKey;
  return Buffer.concat([Buffer.from([5]), pubKey]);
};

export const toSignalKeyPair = (kp: IKeyPair): SignalKeyPair => ({
  privKey: kp.private,
  pubKey: prefixKeyInPublicKey(kp.public),
});

export const generateSignalKeyPair = (): SignalKeyPair => {
  const kp = Curve25519.generateKeyPair();
  return { privKey: kp.privateKey, pubKey: prefixKeyInPublicKey(kp.publicKey) };
};

/**
 * Signal storage — backed by WaCreds for identity/prekeys plus an
 * in-memory session map persisted back into creds.signalSessions.
 */
export class SignalStore {
  private sessions = new Map<string, SessionRecord>();
  private readonly onPersist: () => void;

  constructor(
    private readonly creds: WaCreds & { signalSessions?: Record<string, SessionRecordData> },
    onPersist?: () => void,
  ) {
    this.onPersist = onPersist || (() => undefined);
    if (creds.signalSessions) {
      for (const [k, v] of Object.entries(creds.signalSessions)) {
        this.sessions.set(k, SessionRecord.deserialize(v));
      }
    }
  }

  // -- identity / registration -------------------------------------------

  public getOurIdentity(): SignalKeyPair {
    return toSignalKeyPair(this.creds.signedIdentityKey);
  }

  public getOurRegistrationId(): number {
    return this.creds.registrationId;
  }

  public isTrustedIdentity(_id: string, _key: Buffer): boolean {
    // Trust-on-first-use; identity changes are handled at the WA layer.
    return true;
  }

  // -- prekeys ------------------------------------------------------------

  public loadPreKey(preKeyId: number): IKeyPair | undefined {
    return this.creds.preKeys?.[preKeyId.toString()];
  }

  public removePreKey(preKeyId: number): void {
    if (this.creds.preKeys) {
      delete this.creds.preKeys[preKeyId.toString()];
      this.onPersist();
    }
  }

  public loadSignedPreKey(keyId: number): ISignedPreKey | undefined {
    if (this.creds.signedPreKey?.keyId === keyId) return this.creds.signedPreKey;
    return undefined;
  }

  // -- sessions -------------------------------------------------------------

  public loadSession(addr: string): SessionRecord | undefined {
    return this.sessions.get(addr);
  }

  public storeSession(addr: string, record: SessionRecord): void {
    record.removeOldSessions();
    this.sessions.set(addr, record);
    this.creds.signalSessions = this.creds.signalSessions || {};
    this.creds.signalSessions[addr] = record.serialize();
    this.onPersist();
  }

  public hasSession(addr: string): boolean {
    const rec = this.sessions.get(addr);
    return !!rec && rec.haveOpenSession();
  }
}
