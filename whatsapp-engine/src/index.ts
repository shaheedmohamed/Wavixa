import dotenv from 'dotenv';
dotenv.config();

import express from 'express';
import { MemorySessionStore, ISessionStore } from './session/SessionStore.js';
import { RedisSessionStore } from './session/RedisSessionStore.js';
import { KeyStore } from './identity/KeyStore.js';
import { Session } from './session/Session.js';
import { MessageManager } from './message/MessageManager.js';
import { MessageRateLimiter } from './safety/MessageRateLimiter.js';
import { ConsentManager } from './safety/ConsentManager.js';
import { IdempotencyManager } from './safety/IdempotencyManager.js';
import { HealthMonitor } from './monitoring/HealthMonitor.js';
import { MetricsCollector } from './monitoring/MetricsCollector.js';
import { WebhookDispatcher } from './events/WebhookDispatcher.js';
import { createRoutes } from './api/routes.js';
import { logger } from './config/Logger.js';
import Redis from 'ioredis';

const PORT = parseInt(process.env.ENGINE_PORT || '3005', 10);
const HOST = process.env.ENGINE_HOST || '127.0.0.1';
const WORKER_ID = process.env.ENGINE_WORKER_ID || 'worker-1';

async function bootstrap() {
  logger.info({ workerId: WORKER_ID, port: PORT }, 'Initializing Wavixa WhatsApp Session Engine');

  let store: ISessionStore;
  if (process.env.REDIS_HOST) {
    try {
      const redis = new Redis({
        host: process.env.REDIS_HOST,
        port: parseInt(process.env.REDIS_PORT || '6379', 10),
        password: process.env.REDIS_PASSWORD || undefined,
        db: parseInt(process.env.REDIS_DB || '0', 10),
        lazyConnect: true,
      });
      await redis.connect();
      store = new RedisSessionStore(redis, process.env.REDIS_KEY_PREFIX || 'wavixa:wa:');
      logger.info('Connected to Redis for distributed session storage and locking');
    } catch (err) {
      logger.warn({ error: err }, 'Redis connection failed, falling back to MemorySessionStore');
      store = new MemorySessionStore();
    }
  } else {
    store = new MemorySessionStore();
  }

  const keyStore = new KeyStore(process.env.ENGINE_MASTER_KEY);
  const activeSessions = new Map<string, Session>();
  const messageManager = new MessageManager();
  const rateLimiter = new MessageRateLimiter();
  const consentManager = new ConsentManager();
  const idempotencyManager = new IdempotencyManager();
  const healthMonitor = new HealthMonitor();
  const metrics = MetricsCollector.getInstance();

  // Initialize Webhook Dispatcher to Laravel core
  new WebhookDispatcher({
    laravelBaseUrl: process.env.LARAVEL_API_URL || 'https://wavixa.net/api',
    // Comma-separated extra Laravel targets — the separated CRM backend
    // (own DB) receives a copy of every webhook through here.
    extraLaravelBaseUrls: (process.env.LARAVEL_API_URLS_EXTRA || '')
      .split(',')
      .map((u) => u.trim())
      .filter(Boolean),
    serviceApiKey: process.env.WHATSAPP_SERVICE_API_KEY || 'wavixa-local-api-key-2026',
    timeoutMs: parseInt(process.env.LARAVEL_WEBHOOK_TIMEOUT_MS || '8000', 10),
  });

  const app = express();
  app.use(express.json({ limit: '50mb' }));
  app.use(express.urlencoded({ extended: true, limit: '50mb' }));

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
    workerId: WORKER_ID,
  });

  app.use('/', router);

  const server = app.listen(PORT, HOST, () => {
    logger.info(`Wavixa WhatsApp Engine listening on http://${HOST}:${PORT}`);
  });

  // Graceful shutdown handling
  const shutdown = async (signal: string) => {
    logger.info({ signal }, 'Shutting down WhatsApp Session Engine gracefully');
    server.close();
    for (const [id, session] of activeSessions.entries()) {
      try {
        await session.disconnect('engine_shutdown');
      } catch (err) {
        logger.error({ sessionId: id, error: err }, 'Error disconnecting session during shutdown');
      }
    }
    process.exit(0);
  };

  process.on('SIGTERM', () => shutdown('SIGTERM'));
  process.on('SIGINT', () => shutdown('SIGINT'));
}

bootstrap().catch((err) => {
  logger.fatal({ error: err }, 'Fatal error during engine startup');
  process.exit(1);
});
