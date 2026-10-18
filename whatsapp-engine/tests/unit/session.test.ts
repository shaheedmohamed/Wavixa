import { describe, it, expect } from 'vitest';
import { SessionStateMachine } from '../../src/session/SessionStateMachine.js';
import { MemorySessionStore } from '../../src/session/SessionStore.js';
import { KeyStore } from '../../src/identity/KeyStore.js';
import { Session } from '../../src/session/Session.js';

describe('Multi-Device Session Management & State Machine', () => {
  it('SessionStateMachine enforces valid transitions and rejects illegal jumps', () => {
    const sm = new SessionStateMachine('DISCONNECTED');

    expect(sm.getState()).toBe('DISCONNECTED');
    expect(sm.canTransition('CONNECTING')).toBe(true);
    expect(sm.canTransition('READY')).toBe(false);

    sm.transition('CONNECTING');
    expect(sm.getState()).toBe('CONNECTING');

    sm.transition('AUTHENTICATING');
    expect(sm.getState()).toBe('AUTHENTICATING');

    sm.transition('CONNECTED');
    expect(sm.getState()).toBe('CONNECTED');

    sm.transition('SYNCING');
    expect(sm.getState()).toBe('SYNCING');

    sm.transition('READY');
    expect(sm.getState()).toBe('READY');

    expect(() => sm.transition('AUTHENTICATING')).toThrow();
  });

  it('Session acquires lock and initializes QR code or pairing code representation', async () => {
    const store = new MemorySessionStore();
    const keyStore = new KeyStore('1'.repeat(64));

    const session = new Session({
      sessionId: 'test_session_user_42',
      userId: '42',
      store,
      keyStore,
      workerId: 'worker-1',
    });

    // 1. Connect with QR
    const qrResult = await session.connect();
    expect(qrResult.mode).toBe('qr');
    expect(qrResult.qr).toBeTruthy();
    expect(session.getState()).toBe('AUTHENTICATING');

    // Verify lock is acquired by worker-1
    const secondLock = await store.acquireLock('test_session_user_42', 'worker-2', 10000);
    expect(secondLock).toBe(false); // worker-2 cannot acquire

    // Disconnect releases lock
    await session.disconnect();
    expect(session.getState()).toBe('DISCONNECTED');

    const worker2Lock = await store.acquireLock('test_session_user_42', 'worker-2', 10000);
    expect(worker2Lock).toBe(true);
  });

  it('Session handles phone pairing code generation and expiration', async () => {
    const store = new MemorySessionStore();
    const keyStore = new KeyStore('2'.repeat(64));

    const session = new Session({
      sessionId: 'test_session_phone',
      store,
      keyStore,
    });

    const result = await session.connect('201011679726');
    expect(result.mode).toBe('phone');
    expect(result.code).toMatch(/^[2-9A-Z]{4}-[2-9A-Z]{4}$/);

    const activePairing = session.getPairingCode();
    expect(activePairing?.code).toBe(result.code);
    expect(activePairing?.phoneNumber).toBe('201011679726');

    await session.disconnect();
    expect(session.getPairingCode()).toBeNull();
  });
});
