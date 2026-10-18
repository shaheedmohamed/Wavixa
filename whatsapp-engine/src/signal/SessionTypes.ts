/**
 * Signal session record types — mirrors libsignal's SessionRecord
 * structure, serializable to JSON for persistence in creds.
 */

export enum ChainType {
  SENDING = 1,
  RECEIVING = 2,
}

export enum BaseKeyType {
  OURS = 1,
  THEIRS = 2,
}

export interface SignalKeyPair {
  privKey: Buffer;
  pubKey: Buffer; // 33 bytes (0x05 prefix)
}

export interface Chain {
  chainKey: { counter: number; key?: Buffer };
  chainType: ChainType;
  messageKeys: Record<number, Buffer>;
}

export interface SessionRatchet {
  rootKey: Buffer;
  ephemeralKeyPair: SignalKeyPair;
  lastRemoteEphemeralKey: Buffer;
  previousCounter: number;
}

export interface SessionIndexInfo {
  baseKey: Buffer;
  baseKeyType: BaseKeyType;
  remoteIdentityKey: Buffer; // 33 bytes
  created: number;
  used: number;
  closed: number;
}

export interface PendingPreKey {
  signedKeyId: number;
  baseKey: Buffer;
  preKeyId?: number;
}

export interface SessionEntry {
  registrationId?: number;
  currentRatchet: SessionRatchet;
  indexInfo: SessionIndexInfo;
  pendingPreKey?: PendingPreKey;
  _chains: Record<string, Chain>;
}

export interface SessionRecordData {
  _sessions: Record<string, SessionEntry>;
  version: string;
}

export const keyToBase64 = (key: Buffer | Uint8Array): string =>
  Buffer.from(key).toString('base64');

export const base64ToKey = (b64: string): Buffer => Buffer.from(b64, 'base64');

export class SessionRecord {
  public sessions: Record<string, SessionEntry> = {};
  public version = 'v1';

  public static createEntry(): SessionEntry {
    return {
      currentRatchet: {
        rootKey: Buffer.alloc(0),
        ephemeralKeyPair: { privKey: Buffer.alloc(0), pubKey: Buffer.alloc(0) },
        lastRemoteEphemeralKey: Buffer.alloc(0),
        previousCounter: 0,
      },
      indexInfo: {
        baseKey: Buffer.alloc(0),
        baseKeyType: BaseKeyType.OURS,
        remoteIdentityKey: Buffer.alloc(0),
        created: 0,
        used: 0,
        closed: -1,
      },
      _chains: {},
    };
  }

  public getSessions(): SessionEntry[] {
    return Object.values(this.sessions).sort((a, b) => b.indexInfo.used - a.indexInfo.used);
  }

  public getSession(baseKey: Buffer): SessionEntry | undefined {
    return this.sessions[keyToBase64(baseKey)];
  }

  public getOpenSession(): SessionEntry | undefined {
    for (const session of this.getSessions()) {
      if (session.indexInfo.closed === -1) return session;
    }
    return undefined;
  }

  public setSession(session: SessionEntry): void {
    this.sessions[keyToBase64(session.indexInfo.baseKey)] = session;
  }

  public haveOpenSession(): boolean {
    return this.getOpenSession() !== undefined;
  }

  public closeSession(session: SessionEntry): void {
    if (this.isClosed(session)) return;
    session.indexInfo.closed = Date.now();
  }

  public isClosed(session: SessionEntry): boolean {
    return session.indexInfo.closed !== -1;
  }

  public removeOldSessions(): void {
    const sessions = Object.values(this.sessions);
    const oldest = sessions.filter((s) => s.indexInfo.closed !== -1).sort(
      (a, b) => a.indexInfo.closed - b.indexInfo.closed,
    );
    while (oldest.length > 40) {
      const s = oldest.shift()!;
      delete this.sessions[keyToBase64(s.indexInfo.baseKey)];
    }
  }

  public static deserialize(data: SessionRecordData): SessionRecord {
    const record = new SessionRecord();
    record.sessions = data._sessions || {};
    record.version = data.version || 'v1';
    return record;
  }

  public serialize(): SessionRecordData {
    return { _sessions: this.sessions, version: this.version };
  }
}

// Chain helpers on a session entry
export const sessionAddChain = (session: SessionEntry, key: Buffer, chain: Chain): void => {
  session._chains[keyToBase64(key)] = chain;
};

export const sessionGetChain = (session: SessionEntry, key: Buffer): Chain | undefined =>
  session._chains[keyToBase64(key)];

export const sessionDeleteChain = (session: SessionEntry, key: Buffer): void => {
  delete session._chains[keyToBase64(key)];
};
