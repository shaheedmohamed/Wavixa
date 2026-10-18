/**
 * Wavixa WhatsApp Engine - Multi-Dimensional Sliding Window Rate Limiter
 * Protects destination numbers, devices, and WhatsApp infrastructure from burst overloads.
 */

export interface RateLimitConfig {
  maxRequests: number;
  windowMs: number;
}

export class MessageRateLimiter {
  private windows: Map<string, number[]> = new Map();

  /**
   * Check if action is allowed under the rate limit for a given key.
   * If allowed, records the action timestamp and returns true.
   * If rate limited, returns false.
   */
  public checkLimit(key: string, config: RateLimitConfig): { allowed: boolean; remaining: number; resetMs: number } {
    const now = Date.now();
    const windowStart = now - config.windowMs;

    let timestamps = this.windows.get(key) || [];
    // Discard timestamps outside current sliding window
    timestamps = timestamps.filter((t) => t > windowStart);

    if (timestamps.length >= config.maxRequests) {
      const oldest = timestamps[0] || now;
      const resetMs = Math.max(0, oldest + config.windowMs - now);
      this.windows.set(key, timestamps);
      return { allowed: false, remaining: 0, resetMs };
    }

    timestamps.push(now);
    this.windows.set(key, timestamps);

    const remaining = config.maxRequests - timestamps.length;
    return { allowed: true, remaining, resetMs: 0 };
  }

  /**
   * Reset rate limit state for a key
   */
  public reset(key: string): void {
    this.windows.delete(key);
  }
}
