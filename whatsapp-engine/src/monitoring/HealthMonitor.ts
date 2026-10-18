/**
 * Wavixa WhatsApp Engine - Health Monitor
 * Classifies session and cluster operational states into HEALTHY, DEGRADED, UNHEALTHY, OFFLINE.
 */

export type HealthStatus = 'HEALTHY' | 'DEGRADED' | 'UNHEALTHY' | 'OFFLINE';

export interface SessionHealthRecord {
  sessionId: string;
  status: HealthStatus;
  pingLatencyMs: number;
  errorCount: number;
  reconnectCount: number;
  queueSize: number;
  lastActivityAt: number;
}

export class HealthMonitor {
  private sessions: Map<string, SessionHealthRecord> = new Map();

  public recordActivity(sessionId: string): void {
    const record = this.getOrCreate(sessionId);
    record.lastActivityAt = Date.now();
    this.updateStatus(record);
  }

  public recordPing(sessionId: string, latencyMs: number): void {
    const record = this.getOrCreate(sessionId);
    record.pingLatencyMs = latencyMs;
    this.updateStatus(record);
  }

  public recordError(sessionId: string): void {
    const record = this.getOrCreate(sessionId);
    record.errorCount += 1;
    this.updateStatus(record);
  }

  public recordReconnect(sessionId: string, count: number): void {
    const record = this.getOrCreate(sessionId);
    record.reconnectCount = count;
    this.updateStatus(record);
  }

  public updateQueueSize(sessionId: string, size: number): void {
    const record = this.getOrCreate(sessionId);
    record.queueSize = size;
    this.updateStatus(record);
  }

  public getSessionHealth(sessionId: string): SessionHealthRecord | null {
    const record = this.sessions.get(sessionId);
    return record ? { ...record } : null;
  }

  public getClusterHealth(): {
    total: number;
    healthy: number;
    degraded: number;
    unhealthy: number;
    offline: number;
  } {
    let healthy = 0;
    let degraded = 0;
    let unhealthy = 0;
    let offline = 0;

    for (const record of this.sessions.values()) {
      switch (record.status) {
        case 'HEALTHY':
          healthy++;
          break;
        case 'DEGRADED':
          degraded++;
          break;
        case 'UNHEALTHY':
          unhealthy++;
          break;
        case 'OFFLINE':
          offline++;
          break;
      }
    }

    return {
      total: this.sessions.size,
      healthy,
      degraded,
      unhealthy,
      offline,
    };
  }

  private getOrCreate(sessionId: string): SessionHealthRecord {
    let record = this.sessions.get(sessionId);
    if (!record) {
      record = {
        sessionId,
        status: 'HEALTHY',
        pingLatencyMs: 0,
        errorCount: 0,
        reconnectCount: 0,
        queueSize: 0,
        lastActivityAt: Date.now(),
      };
      this.sessions.set(sessionId, record);
    }
    return record;
  }

  private updateStatus(record: SessionHealthRecord): void {
    if (record.reconnectCount > 10 || record.errorCount > 20) {
      record.status = 'UNHEALTHY';
    } else if (record.reconnectCount > 3 || record.errorCount > 5 || record.pingLatencyMs > 2000) {
      record.status = 'DEGRADED';
    } else if (Date.now() - record.lastActivityAt > 300000 && record.pingLatencyMs === 0) {
      record.status = 'OFFLINE';
    } else {
      record.status = 'HEALTHY';
    }
  }
}
