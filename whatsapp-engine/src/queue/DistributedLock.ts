import { ISessionStore } from '../session/SessionStore.js';

export class DistributedLock {
  private active = false;
  private renewalTimer: NodeJS.Timeout | null = null;

  constructor(
    private readonly store: ISessionStore,
    private readonly sessionId: string,
    private readonly workerId: string,
    private readonly ttlMs: number = 30000,
  ) {}

  public async acquire(): Promise<boolean> {
    const success = await this.store.acquireLock(this.sessionId, this.workerId, this.ttlMs);
    if (success) {
      this.active = true;
      this.startRenewal();
    }
    return success;
  }

  public async release(): Promise<boolean> {
    this.stopRenewal();
    if (!this.active) return true;
    const released = await this.store.releaseLock(this.sessionId, this.workerId);
    this.active = false;
    return released;
  }

  public isHeld(): boolean {
    return this.active;
  }

  private startRenewal(): void {
    this.stopRenewal();
    const interval = Math.max(1000, Math.floor(this.ttlMs / 3));
    this.renewalTimer = setInterval(async () => {
      if (!this.active) return;
      const renewed = await this.store.renewLock(this.sessionId, this.workerId, this.ttlMs);
      if (!renewed) {
        this.active = false;
        this.stopRenewal();
      }
    }, interval);
  }

  private stopRenewal(): void {
    if (this.renewalTimer) {
      clearInterval(this.renewalTimer);
      this.renewalTimer = null;
    }
  }
}
