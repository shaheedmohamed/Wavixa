import { describe, it, expect } from 'vitest';
import { MessageQueue } from '../../src/queue/MessageQueue.js';
import { DistributedLock } from '../../src/queue/DistributedLock.js';
import { MemorySessionStore } from '../../src/session/SessionStore.js';

describe('Message Queue & Distributed Concurrency Locks', () => {
  it('DistributedLock acquires lock exclusively and rejects second worker', async () => {
    const store = new MemorySessionStore();
    const lock1 = new DistributedLock(store, 'session_lock_test', 'worker-1', 5000);
    const lock2 = new DistributedLock(store, 'session_lock_test', 'worker-2', 5000);

    const acquired1 = await lock1.acquire();
    expect(acquired1).toBe(true);
    expect(lock1.isHeld()).toBe(true);

    const acquired2 = await lock2.acquire();
    expect(acquired2).toBe(false);
    expect(lock2.isHeld()).toBe(false);

    await lock1.release();
    expect(lock1.isHeld()).toBe(false);

    const acquired2After = await lock2.acquire();
    expect(acquired2After).toBe(true);
    await lock2.release();
  });

  it('MessageQueue enqueues and processes jobs in FIFO order', async () => {
    const queue = new MessageQueue();
    const processed: string[] = [];

    queue.setHandler(async (job) => {
      processed.push(job.payload.content);
      return '3EB0_MOCK';
    });

    await queue.enqueue('session_fifo', { to: '201011679726', type: 'text', content: 'msg-1' });
    await queue.enqueue('session_fifo', { to: '201011679726', type: 'text', content: 'msg-2' });
    await queue.enqueue('session_fifo', { to: '201011679726', type: 'text', content: 'msg-3' });

    // Wait short delay for async event loop processing
    await new Promise((r) => setTimeout(r, 100));

    expect(processed).toEqual(['msg-1', 'msg-2', 'msg-3']);
    expect(queue.getQueueDepth('session_fifo')).toBe(0);
  });

  it('MessageQueue retries failed jobs and moves to dead letter on exhaustion', async () => {
    const queue = new MessageQueue();
    let attempts = 0;

    queue.setHandler(async () => {
      attempts++;
      throw new Error('Simulated network disconnect');
    });

    const job = await queue.enqueue(
      'session_retry',
      { to: '201011679726', type: 'text', content: 'failing-msg' },
      2, // max attempts: 2
    );

    // Initial failure
    await new Promise((r) => setTimeout(r, 50));
    expect(job.attempts).toBe(1);

    // Trigger scheduled retry immediately
    job.scheduledAt = Date.now() - 1;
    (queue as any).processNext('session_retry');

    await new Promise((r) => setTimeout(r, 50));
    expect(job.attempts).toBe(2);

    // Dead letter queue should contain job
    expect(queue.getDeadLetters().length).toBe(1);
    expect(queue.getDeadLetters()[0]?.id).toBe(job.id);
  });
});
