/**
 * Wavixa WhatsApp Engine - Idempotency & Deduplication Manager
 * Prevents duplicate message dispatches using unique client tokens.
 */

export class IdempotencyManager {
  private cache: Map<string, number> = new Map();
  private ttlMs: number;

  constructor(ttlMs: number = 24 * 60 * 60 * 1000) {
    this.ttlMs = ttlMs;
  }

  /**
   * Acquire idempotency key.
   * Returns true if newly acquired, false if already executed (duplicate).
   */
  public acquire(sessionId: string, clientMessageId: string): boolean {
    const key = `message:${sessionId}:${clientMessageId}`;
    const now = Date.now();

    const existingExpiry = this.cache.get(key);
    if (existingExpiry && existingExpiry > now) {
      return false; // duplicate!
    }

    this.cache.set(key, now + this.ttlMs);
    return true;
  }

  public release(sessionId: string, clientMessageId: string): void {
    const key = `message:${sessionId}:${clientMessageId}`;
    this.cache.delete(key);
  }

  public cleanup(): void {
    const now = Date.now();
    for (const [key, expiry] of this.cache.entries()) {
      if (expiry <= now) {
        this.cache.delete(key);
      }
    }
  }
}
