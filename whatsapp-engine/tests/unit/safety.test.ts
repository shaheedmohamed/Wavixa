import { describe, it, expect } from 'vitest';
import { MessageRateLimiter } from '../../src/safety/MessageRateLimiter.js';
import { ConsentManager } from '../../src/safety/ConsentManager.js';
import { IdempotencyManager } from '../../src/safety/IdempotencyManager.js';
import { RiskEngine } from '../../src/safety/RiskEngine.js';

describe('Safety Engine, Rate Limiting & Anti-Abuse Primitives', () => {
  it('MessageRateLimiter enforces sliding window limits accurately', () => {
    const limiter = new MessageRateLimiter();
    const config = { maxRequests: 3, windowMs: 1000 };

    expect(limiter.checkLimit('user_123', config).allowed).toBe(true);
    expect(limiter.checkLimit('user_123', config).allowed).toBe(true);
    expect(limiter.checkLimit('user_123', config).allowed).toBe(true);
    // 4th request in the same window must be blocked
    const fourth = limiter.checkLimit('user_123', config);
    expect(fourth.allowed).toBe(false);
    expect(fourth.remaining).toBe(0);
    expect(fourth.resetMs).toBeGreaterThan(0);
  });

  it('ConsentManager blocks messages to OPTED_OUT or BLOCKED recipients', () => {
    const consent = new ConsentManager();
    const phone = '+201011679726';

    expect(consent.canSend(phone).allowed).toBe(true);

    consent.setConsent(phone, 'OPTED_OUT');
    expect(consent.canSend(phone).allowed).toBe(false);
    expect(consent.canSend(phone).reason).toContain('opted out');

    consent.setConsent(phone, 'BLOCKED');
    expect(consent.canSend(phone).allowed).toBe(false);
    expect(consent.canSend(phone).reason).toContain('blocked');

    consent.setConsent(phone, 'OPTED_IN');
    expect(consent.canSend(phone).allowed).toBe(true);
  });

  it('IdempotencyManager rejects duplicate dispatches within TTL window', () => {
    const idem = new IdempotencyManager(60000);

    const first = idem.acquire('session_1', 'client_msg_uuid_99');
    expect(first).toBe(true);

    const duplicate = idem.acquire('session_1', 'client_msg_uuid_99');
    expect(duplicate).toBe(false); // rejected

    const differentMsg = idem.acquire('session_1', 'client_msg_uuid_100');
    expect(differentMsg).toBe(true);
  });

  it('RiskEngine computes score and triggers appropriate safety actions', () => {
    // Normal healthy metrics
    const safe = RiskEngine.evaluate({
      totalSent: 100,
      totalFailed: 2,
      totalOptOuts: 1,
      recentDuplicateCount: 0,
      burstCountLastMinute: 10,
    });
    expect(safe.score).toBeLessThanOrEqual(20);
    expect(safe.level).toBe('SAFE');
    expect(safe.action).toBe('proceed');

    // Abusive metrics (high failure, opt-outs, duplicate loop, burst)
    const critical = RiskEngine.evaluate({
      totalSent: 100,
      totalFailed: 45, // 35 pts
      totalOptOuts: 12, // 25 pts
      recentDuplicateCount: 20, // 20 pts
      burstCountLastMinute: 50, // 20 pts
    });
    expect(critical.score).toBeGreaterThanOrEqual(81);
    expect(critical.level).toBe('BLOCK_SEND');
    expect(critical.action).toBe('block');
  });
});
