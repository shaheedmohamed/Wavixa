import { describe, it, expect } from 'vitest';
import { ReconnectManager } from '../../src/reconnect/ReconnectManager.js';
import { HealthMonitor } from '../../src/monitoring/HealthMonitor.js';
import { MetricsCollector } from '../../src/monitoring/MetricsCollector.js';

describe('Reconnect, Health Monitoring & Metrics', () => {
  it('ReconnectManager calculates exponential backoff with jitter and respects maxRetries', () => {
    const rm = new ReconnectManager({
      baseDelayMs: 1000,
      maxDelayMs: 10000,
      maxRetries: 3,
    });

    const d1 = rm.calculateDelay(1);
    expect(d1).toBeGreaterThanOrEqual(1000 * 2 * 0.75);
    expect(d1).toBeLessThanOrEqual(1000 * 2 * 1.25);

    const s1 = rm.schedule('s1', async () => {});
    expect(s1.scheduled).toBe(true);
    expect(s1.attempt).toBe(1);

    const s2 = rm.schedule('s1', async () => {});
    expect(s2.attempt).toBe(2);

    const s3 = rm.schedule('s1', async () => {});
    expect(s3.attempt).toBe(3);

    // 4th exceeds maxRetries (3)
    const s4 = rm.schedule('s1', async () => {});
    expect(s4.scheduled).toBe(false);

    rm.reset('s1');
    expect(rm.getAttempt('s1')).toBe(0);
  });

  it('HealthMonitor transitions status based on errors and latency', () => {
    const monitor = new HealthMonitor();
    const sessionId = 'session_health_test';

    monitor.recordActivity(sessionId);
    expect(monitor.getSessionHealth(sessionId)?.status).toBe('HEALTHY');

    // High latency degrades status
    monitor.recordPing(sessionId, 2500);
    expect(monitor.getSessionHealth(sessionId)?.status).toBe('DEGRADED');

    // Many errors marks unhealthy
    for (let i = 0; i < 25; i++) {
      monitor.recordError(sessionId);
    }
    expect(monitor.getSessionHealth(sessionId)?.status).toBe('UNHEALTHY');
  });

  it('MetricsCollector generates valid OpenMetrics / Prometheus output', () => {
    const metrics = MetricsCollector.getInstance();
    metrics.incConnected();
    metrics.incSent();
    metrics.incReceived();

    const output = metrics.toPrometheus();
    expect(output).toContain('wavixa_sessions_connected_total');
    expect(output).toContain('wavixa_messages_sent_total');
    expect(output).toContain('process_resident_memory_bytes');
  });
});
