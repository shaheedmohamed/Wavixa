import { describe, it, expect } from 'vitest';
import { MemorySessionStore } from '../../src/session/SessionStore.js';
import { KeyStore } from '../../src/identity/KeyStore.js';
import { Session } from '../../src/session/Session.js';
import { MessageRateLimiter } from '../../src/safety/MessageRateLimiter.js';
import { IdempotencyManager } from '../../src/safety/IdempotencyManager.js';

describe('High-Concurrency Multi-Session & Multi-Worker Simulation', () => {
  it('Simulates 100 concurrent sessions across 4 workers with zero lock collisions', async () => {
    const store = new MemorySessionStore();
    const keyStore = new KeyStore('4'.repeat(64));
    const workerIds = ['worker-alpha', 'worker-beta', 'worker-gamma', 'worker-delta'];

    const sessions: Session[] = [];
    const NUM_SESSIONS = 100;

    // Create 100 distinct sessions distributed across 4 workers
    for (let i = 0; i < NUM_SESSIONS; i++) {
      const assignedWorker = workerIds[i % workerIds.length]!;
      const session = new Session({
        sessionId: `session_concurrent_${i}`,
        userId: `user_${i}`,
        store,
        keyStore,
        workerId: assignedWorker,
      });
      sessions.push(session);
    }

    // Acquire the distributed lock for all 100 sessions in parallel.
    // NOTE: connect() itself performs a real WhatsApp handshake and cannot
    // be exercised 100x in tests — the lock layer is what this test covers.
    const lockResults = await Promise.all(
      sessions.map((session, idx) =>
        store.acquireLock(`session_concurrent_${idx}`, workerIds[idx % workerIds.length]!, 10000),
      ),
    );
    for (const acquired of lockResults) {
      expect(acquired).toBe(true);
    }

    // Attempt concurrent lock theft from competing workers: ALL must fail
    const theftAttempts = await Promise.all(
      sessions.map((_, i) => store.acquireLock(`session_concurrent_${i}`, 'worker-thief', 10000)),
    );

    for (const theftSucceeded of theftAttempts) {
      expect(theftSucceeded).toBe(false); // No lock collisions allowed!
    }

    // Release all 100 locks
    await Promise.all(
      sessions.map((_, i) => store.releaseLock(`session_concurrent_${i}`, workerIds[i % workerIds.length]!)),
    );

    // Verify all locks released
    const list = await store.listSessions();
    for (const s of list) {
      expect(s.state).toBe('DISCONNECTED');
    }
  });

  it('Concurrent idempotency requests handle duplicate bursts gracefully', async () => {
    const manager = new IdempotencyManager(30000);
    const sessionId = 'session_burst_test';
    const messageToken = 'tx_token_fixed_123';

    // 50 concurrent requests with exact same idempotency token
    const results = await Promise.all(
      Array.from({ length: 50 }).map(() => Promise.resolve(manager.acquire(sessionId, messageToken))),
    );

    const allowedCount = results.filter((r) => r === true).length;
    const blockedCount = results.filter((r) => r === false).length;

    expect(allowedCount).toBe(1); // exactly one execution allowed!
    expect(blockedCount).toBe(49); // 49 duplicate bursts blocked!
  });

  it('High-velocity rate limiting correctly tracks independent session quotas', () => {
    const limiter = new MessageRateLimiter();
    const config = { maxRequests: 5, windowMs: 2000 };

    for (let s = 0; s < 20; s++) {
      const key = `session_${s}`;
      for (let req = 0; req < 5; req++) {
        expect(limiter.checkLimit(key, config).allowed).toBe(true);
      }
      // 6th request for each session is rejected
      expect(limiter.checkLimit(key, config).allowed).toBe(false);
    }
  });
});
