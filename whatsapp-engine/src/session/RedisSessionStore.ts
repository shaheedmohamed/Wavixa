import Redis from 'ioredis';
import { ISessionStore, SessionData } from './SessionStore.js';

export class RedisSessionStore implements ISessionStore {
  private redis: Redis;
  private keyPrefix: string;

  private static readonly RELEASE_LOCK_SCRIPT = `
    if redis.call("get", KEYS[1]) == ARGV[1] then
      return redis.call("del", KEYS[1])
    else
      return 0
    end
  `;

  private static readonly RENEW_LOCK_SCRIPT = `
    if redis.call("get", KEYS[1]) == ARGV[1] then
      return redis.call("pexpire", KEYS[1], ARGV[2])
    else
      return 0
    end
  `;

  constructor(redisClient: Redis, keyPrefix: string = 'wavixa:wa:') {
    this.redis = redisClient;
    this.keyPrefix = keyPrefix;
  }

  public async saveSession(session: SessionData): Promise<void> {
    const key = `${this.keyPrefix}session:${session.sessionId}`;
    await this.redis.set(key, JSON.stringify(session));
    await this.redis.sadd(`${this.keyPrefix}active_sessions`, session.sessionId);
  }

  public async loadSession(sessionId: string): Promise<SessionData | null> {
    const key = `${this.keyPrefix}session:${sessionId}`;
    const raw = await this.redis.get(key);
    if (!raw) return null;
    try {
      return JSON.parse(raw) as SessionData;
    } catch {
      return null;
    }
  }

  public async deleteSession(sessionId: string): Promise<void> {
    const key = `${this.keyPrefix}session:${sessionId}`;
    await this.redis.del(key);
    await this.redis.srem(`${this.keyPrefix}active_sessions`, sessionId);
  }

  public async listSessions(): Promise<SessionData[]> {
    const sessionIds = await this.redis.smembers(`${this.keyPrefix}active_sessions`);
    if (sessionIds.length === 0) return [];

    const keys = sessionIds.map((id) => `${this.keyPrefix}session:${id}`);
    const results = await this.redis.mget(...keys);

    const sessions: SessionData[] = [];
    for (const raw of results) {
      if (raw) {
        try {
          sessions.push(JSON.parse(raw));
        } catch {
          // ignore corrupted entry
        }
      }
    }
    return sessions;
  }

  public async acquireLock(sessionId: string, workerId: string, ttlMs: number): Promise<boolean> {
    const lockKey = `${this.keyPrefix}lock:session:${sessionId}`;
    // SET key workerId PX ttlMs NX
    const result = await this.redis.set(lockKey, workerId, 'PX', ttlMs, 'NX');
    return result === 'OK';
  }

  public async releaseLock(sessionId: string, workerId: string): Promise<boolean> {
    const lockKey = `${this.keyPrefix}lock:session:${sessionId}`;
    const result = await this.redis.eval(
      RedisSessionStore.RELEASE_LOCK_SCRIPT,
      1,
      lockKey,
      workerId,
    );
    return result === 1;
  }

  public async renewLock(sessionId: string, workerId: string, ttlMs: number): Promise<boolean> {
    const lockKey = `${this.keyPrefix}lock:session:${sessionId}`;
    const result = await this.redis.eval(
      RedisSessionStore.RENEW_LOCK_SCRIPT,
      1,
      lockKey,
      workerId,
      ttlMs,
    );
    return result === 1;
  }
}
