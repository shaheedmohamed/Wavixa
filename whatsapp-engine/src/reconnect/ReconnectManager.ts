/**
 * Wavixa WhatsApp Engine - Reconnect Manager
 * Implements exponential backoff with jitter to eliminate reconnect storms.
 */

export interface ReconnectConfig {
  baseDelayMs: number;
  maxDelayMs: number;
  maxRetries: number;
}

export type ReconnectHandler = (attempt: number) => Promise<void>;

export class ReconnectManager {
  private attempts: Map<string, number> = new Map();
  private timers: Map<string, NodeJS.Timeout> = new Map();

  constructor(
    private readonly config: ReconnectConfig = {
      baseDelayMs: 2000,
      maxDelayMs: 60000,
      maxRetries: 15,
    },
  ) {}

  /**
   * Calculate backoff delay with jitter for a specific attempt
   */
  public calculateDelay(attempt: number): number {
    const rawDelay = Math.min(
      this.config.maxDelayMs,
      this.config.baseDelayMs * Math.pow(2, attempt),
    );
    // Add jitter: +/- 25%
    const jitter = 0.75 + Math.random() * 0.5;
    return Math.floor(rawDelay * jitter);
  }

  /**
   * Schedule next reconnect attempt
   */
  public schedule(sessionId: string, handler: ReconnectHandler): { scheduled: boolean; delayMs: number; attempt: number } {
    this.cancel(sessionId);

    const currentAttempt = (this.attempts.get(sessionId) || 0) + 1;
    if (currentAttempt > this.config.maxRetries) {
      return { scheduled: false, delayMs: 0, attempt: currentAttempt };
    }

    this.attempts.set(sessionId, currentAttempt);
    const delayMs = this.calculateDelay(currentAttempt);

    const timer = setTimeout(async () => {
      this.timers.delete(sessionId);
      try {
        await handler(currentAttempt);
      } catch {
        // next attempt will be scheduled if not connected
      }
    }, delayMs);

    this.timers.set(sessionId, timer);

    return { scheduled: true, delayMs, attempt: currentAttempt };
  }

  /**
   * Reset attempt counter upon successful connection
   */
  public reset(sessionId: string): void {
    this.cancel(sessionId);
    this.attempts.delete(sessionId);
  }

  /**
   * Cancel pending reconnect timer
   */
  public cancel(sessionId: string): void {
    const timer = this.timers.get(sessionId);
    if (timer) {
      clearTimeout(timer);
      this.timers.delete(sessionId);
    }
  }

  public getAttempt(sessionId: string): number {
    return this.attempts.get(sessionId) || 0;
  }
}
