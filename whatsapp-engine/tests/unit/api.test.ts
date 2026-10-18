import { describe, it, expect } from 'vitest';
import express from 'express';
import { createRoutes } from '../../src/api/routes.js';
import { MemorySessionStore } from '../../src/session/SessionStore.js';
import { KeyStore } from '../../src/identity/KeyStore.js';
import { Session } from '../../src/session/Session.js';
import { MessageManager } from '../../src/message/MessageManager.js';
import { MessageRateLimiter } from '../../src/safety/MessageRateLimiter.js';
import { ConsentManager } from '../../src/safety/ConsentManager.js';
import { IdempotencyManager } from '../../src/safety/IdempotencyManager.js';
import { HealthMonitor } from '../../src/monitoring/HealthMonitor.js';
import { MetricsCollector } from '../../src/monitoring/MetricsCollector.js';
import http from 'http';

describe('Internal REST API & Endpoint Contracts', () => {
  const store = new MemorySessionStore();
  const keyStore = new KeyStore('3'.repeat(64));
  const activeSessions = new Map<string, Session>();
  const messageManager = new MessageManager();
  const rateLimiter = new MessageRateLimiter();
  const consentManager = new ConsentManager();
  const idempotencyManager = new IdempotencyManager();
  const healthMonitor = new HealthMonitor();
  const metrics = MetricsCollector.getInstance();

  const app = express();
  app.use(express.json());
  const router = createRoutes({
    store,
    keyStore,
    activeSessions,
    messageManager,
    rateLimiter,
    consentManager,
    idempotencyManager,
    healthMonitor,
    metrics,
    workerId: 'test-worker',
  });
  app.use('/', router);

  let server: http.Server;
  let baseUrl: string;

  beforeAll(async () => {
    await new Promise<void>((resolve) => {
      server = app.listen(0, '127.0.0.1', () => {
        const addr = server.address() as any;
        baseUrl = `http://127.0.0.1:${addr.port}`;
        resolve();
      });
    });
  });

  afterAll(async () => {
    await new Promise<void>((resolve) => server.close(() => resolve()));
  });

  it('GET /health returns public cluster health', async () => {
    const res = await fetch(`${baseUrl}/health`);
    const data = await res.json();
    expect(res.status).toBe(200);
    expect(data.success).toBe(true);
    expect(data.engine).toBe('wavixa-independent-engine');
  });

  it('Protected routes reject requests without valid Bearer token', async () => {
    const res = await fetch(`${baseUrl}/internal/sessions`);
    expect(res.status).toBe(401);
  });

  it('POST /internal/sessions creates a session slot', async () => {
    const res = await fetch(`${baseUrl}/internal/sessions`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: 'Bearer wavixa-local-api-key-2026',
      },
      body: JSON.stringify({ sessionId: 'session_api_test_1', userId: '10' }),
    });

    const data = await res.json();
    expect(res.status).toBe(201);
    expect(data.success).toBe(true);
    expect(data.data.sessionId).toBe('session_api_test_1');
  });

  it('POST /internal/messages/send rejects send when no WhatsApp session is connected', async () => {
    const sendPayload = {
      sessionId: 'session_api_test_1',
      to: '201011679726',
      message: 'Hello API test',
      clientMessageId: 'unique_uuid_test_999',
    };

    // The engine must NOT report a send as successful without a live,
    // authenticated WhatsApp session — it must honestly refuse.
    const res1 = await fetch(`${baseUrl}/internal/messages/send`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: 'Bearer wavixa-local-api-key-2026',
      },
      body: JSON.stringify(sendPayload),
    });

    const data1 = await res1.json();
    expect(res1.status).toBe(409);
    expect(data1.success).toBe(false);
    expect(data1.message).toMatch(/not connected/i);
  });
});
