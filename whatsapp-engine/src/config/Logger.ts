import pino from 'pino';

export interface LogContext {
  requestId?: string;
  sessionId?: string;
  userId?: string;
  messageId?: string;
  workerId?: string;
  event?: string;
}

export const logger = pino({
  level: process.env.LOG_LEVEL || 'info',
  formatters: {
    level(label) {
      return { level: label };
    },
  },
  redact: {
    paths: [
      '*.privateKey',
      '*.password',
      '*.secret',
      '*.token',
      '*.credentials',
      '*.mediaKey',
      '*.authTag',
      '*.keyBundle',
      'privateKey',
      'secret',
      'token',
      'ENGINE_MASTER_KEY',
    ],
    remove: true,
  },
  base: {
    service: 'wavixa-whatsapp-engine',
    workerId: process.env.ENGINE_WORKER_ID || 'worker-1',
  },
});

export function createChildLogger(context: LogContext): pino.Logger {
  return logger.child(context);
}
