import { StoredKeyBundle } from '../identity/KeyStore.js';
import { SessionState } from './SessionStateMachine.js';

export interface SessionData {
  sessionId: string;
  userId?: string;
  phoneNumber?: string;
  pushName?: string;
  state: SessionState;
  keyBundle?: StoredKeyBundle;
  workerId?: string;
  lastSeenAt?: number;
  metadata?: Record<string, any>;
}

export interface ISessionStore {
  saveSession(session: SessionData): Promise<void>;
  loadSession(sessionId: string): Promise<SessionData | null>;
  deleteSession(sessionId: string): Promise<void>;
  listSessions(): Promise<SessionData[]>;
  acquireLock(sessionId: string, workerId: string, ttlMs: number): Promise<boolean>;
  releaseLock(sessionId: string, workerId: string): Promise<boolean>;
  renewLock(sessionId: string, workerId: string, ttlMs: number): Promise<boolean>;
}

export class MemorySessionStore implements ISessionStore {
  private sessions: Map<string, SessionData> = new Map();
  private locks: Map<string, { workerId: string; expiresAt: number }> = new Map();

  public async saveSession(session: SessionData): Promise<void> {
    this.sessions.set(session.sessionId, { ...session });
  }

  public async loadSession(sessionId: string): Promise<SessionData | null> {
    const data = this.sessions.get(sessionId);
    return data ? { ...data } : null;
  }

  public async deleteSession(sessionId: string): Promise<void> {
    this.sessions.delete(sessionId);
    this.locks.delete(sessionId);
  }

  public async listSessions(): Promise<SessionData[]> {
    return Array.from(this.sessions.values()).map((s) => ({ ...s }));
  }

  public async acquireLock(sessionId: string, workerId: string, ttlMs: number): Promise<boolean> {
    const existing = this.locks.get(sessionId);
    const now = Date.now();

    if (existing && existing.expiresAt > now && existing.workerId !== workerId) {
      return false; // locked by someone else
    }

    this.locks.set(sessionId, {
      workerId,
      expiresAt: now + ttlMs,
    });
    return true;
  }

  public async releaseLock(sessionId: string, workerId: string): Promise<boolean> {
    const existing = this.locks.get(sessionId);
    if (!existing) return true;
    if (existing.workerId === workerId) {
      this.locks.delete(sessionId);
      return true;
    }
    return false;
  }

  public async renewLock(sessionId: string, workerId: string, ttlMs: number): Promise<boolean> {
    const existing = this.locks.get(sessionId);
    if (!existing || existing.workerId !== workerId) {
      return false;
    }
    existing.expiresAt = Date.now() + ttlMs;
    return true;
  }
}
