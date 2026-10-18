/**
 * ReconnectQueue
 *
 * Decouples WhatsApp session reconnect requests from their execution so a
 * mass-disconnect (reconnect storm) cannot spawn hundreds of Chromium instances
 * at once.
 *
 * Supports two backends:
 *   1. In-process memory queue (default, single worker).
 *   2. Redis list per worker (set REDIS_URL and WHATSAPP_WORKER_ID to enable
 *      horizontal scaling: multiple Node workers each consume their own list).
 */

const EventEmitter = require('events');

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

class SimpleMutex {
  constructor() {
    this._locked = false;
    this._queue = [];
  }

  async acquire() {
    if (!this._locked) {
      this._locked = true;
      return () => this.release();
    }
    return new Promise(resolve => this._queue.push(resolve));
  }

  release() {
    if (this._queue.length > 0) {
      const next = this._queue.shift();
      next(() => this.release());
    } else {
      this._locked = false;
    }
  }
}

class ReconnectQueue extends EventEmitter {
  constructor({ workerId, concurrency = 3, intervalMs = 2000, handler, redisUrl = null }) {
    super();
    this.workerId = workerId;
    this.concurrency = Math.max(1, concurrency);
    this.intervalMs = Math.max(0, intervalMs);
    this.handler = handler;
    this.redisUrl = redisUrl;

    this.pending = new Set();
    this.inProgress = new Set();
    this.waiters = [];
    this.stopped = true;
    this.redisClient = null;
    this.queueName = `wa:reconnect:${workerId}`;
    this.lastStartAt = 0;
    this.startMutex = new SimpleMutex();

    this._initRedis();
  }

  async _initRedis() {
    if (!this.redisUrl) return;

    let redis;
    try {
      redis = require('redis');
    } catch (err) {
      console.warn('[ReconnectQueue] redis package not installed, using in-memory queue. Run `npm install redis` to enable Redis backend.');
      return;
    }

    try {
      this.redisClient = redis.createClient({ url: this.redisUrl });
      this.redisClient.on('error', (err) => console.error('[ReconnectQueue] Redis error:', err.message));
      await this.redisClient.connect();
      console.log(`[ReconnectQueue] Redis connected on worker ${this.workerId}`);
    } catch (err) {
      console.error('[ReconnectQueue] Failed to connect to Redis, using in-memory queue:', err.message);
      this.redisClient = null;
    }
  }

  push(userId) {
    const id = String(userId);
    if (this.inProgress.has(id) || this.pending.has(id)) return;
    this.pending.add(id);

    if (this.redisClient) {
      this.redisClient.lPush(this.queueName, id).catch(err => {
        console.error(`[ReconnectQueue] Failed to push ${id} to Redis:`, err.message);
        this.pending.delete(id);
      });
    } else {
      const waiter = this.waiters.shift();
      if (waiter) waiter.resolve();
    }
  }

  remove(userId) {
    const id = String(userId);
    this.pending.delete(id);
    this.inProgress.delete(id);
  }

  async _next() {
    if (this.redisClient) {
      try {
        const result = await this.redisClient.bLPop(0, this.queueName);
        return result?.element || null;
      } catch (err) {
        console.error('[ReconnectQueue] Redis bLPop error:', err.message);
        await sleep(5000);
        return null;
      }
    }

    if (this.pending.size === 0) {
      await new Promise(resolve => this.waiters.push({ resolve }));
    }

    const [id] = this.pending;
    this.pending.delete(id);
    return id;
  }

  async _acquireStartSlot() {
    const release = await this.startMutex.acquire();
    const now = Date.now();
    const wait = this.lastStartAt + this.intervalMs - now;
    if (wait > 0) await sleep(wait);
    this.lastStartAt = Date.now();
    return release;
  }

  async _runWorker(index) {
    const tag = this.concurrency > 1 ? `[worker-${index}]` : '[worker]';
    while (!this.stopped) {
      try {
        const id = await this._next();
        if (!id) continue;

        if (this.inProgress.has(id)) continue;
        this.inProgress.add(id);

        const release = await this._acquireStartSlot();
        try {
          release();
          await this.handler(id);
        } finally {
          this.inProgress.delete(id);
        }
      } catch (err) {
        console.error(`${tag} Reconnect queue worker error:`, err.message);
        await sleep(2000);
      }
    }
  }

  async start() {
    if (!this.stopped) return;
    this.stopped = false;

    for (let i = 0; i < this.concurrency; i++) {
      this._runWorker(i + 1);
    }

    if (!this.redisClient) {
      console.log(`[ReconnectQueue] Started in-memory queue (worker: ${this.workerId}, concurrency: ${this.concurrency}, interval: ${this.intervalMs}ms)`);
    }
  }

  stop() {
    this.stopped = true;
    if (this.redisClient) {
      this.redisClient.quit().catch(() => {});
    }
    for (const waiter of this.waiters) waiter.resolve();
    this.waiters = [];
  }

  stats() {
    return {
      worker_id: this.workerId,
      pending: this.pending.size,
      in_progress: this.inProgress.size,
      concurrency: this.concurrency,
      interval_ms: this.intervalMs,
      redis: !!this.redisClient,
    };
  }
}

module.exports = ReconnectQueue;
