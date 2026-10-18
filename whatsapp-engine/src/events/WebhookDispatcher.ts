import crypto from 'crypto';
import axios from 'axios';
import { EventBus, WebhookEventPayload } from './EventBus.js';
import { logger } from '../config/Logger.js';

export interface WebhookConfig {
  laravelBaseUrl: string;
  // Additional Laravel apps that receive the same webhook stream (e.g. the
  // separated CRM deployment). Failures are isolated per target.
  extraLaravelBaseUrls?: string[];
  serviceApiKey: string;
  timeoutMs?: number;
}

export class WebhookDispatcher {
  private config: WebhookConfig;

  // A reconnect dumps hundreds of receipt/status stanzas at once. Unbounded
  // concurrent POSTs saturate PHP-FPM so every request — including
  // interactive CRM sends — times out. Cap in-flight calls and queue the rest.
  private static readonly MAX_INFLIGHT = 6;
  private inFlight = 0;
  private readonly waitQueue: Array<() => void> = [];

  // Delivered/read receipts are batched so a backlog becomes a handful of
  // requests instead of hundreds.
  private readonly statusBuffers = new Map<
    string,
    { ids: string[]; sessionId: string; status: string; timestamp: number; timer?: NodeJS.Timeout }
  >();

  constructor(config: WebhookConfig) {
    this.config = config;
    this.setupListeners();
  }

  private acquireSlot(): Promise<void> {
    if (this.inFlight < WebhookDispatcher.MAX_INFLIGHT) {
      this.inFlight++;
      return Promise.resolve();
    }
    return new Promise((resolve) => this.waitQueue.push(resolve));
  }

  private releaseSlot(): void {
    const next = this.waitQueue.shift();
    if (next) next();
    else this.inFlight--;
  }

  private enqueueStatusReceipt(p: WebhookEventPayload): void {
    const status = p.data.status;
    const key = `${p.session_id}|${status}`;
    let buf = this.statusBuffers.get(key);
    if (!buf) {
      buf = { ids: [], sessionId: p.session_id, status, timestamp: p.timestamp };
      this.statusBuffers.set(key, buf);
    }
    if (p.message_id) buf.ids.push(p.message_id);
    if (buf.ids.length >= 40) {
      this.flushStatusBuffer(key);
      return;
    }
    if (!buf.timer) {
      buf.timer = setTimeout(() => this.flushStatusBuffer(key), 1200);
    }
  }

  private flushStatusBuffer(key: string): void {
    const buf = this.statusBuffers.get(key);
    if (!buf) return;
    if (buf.timer) clearTimeout(buf.timer);
    this.statusBuffers.delete(key);
    if (!buf.ids.length) return;
    const endpoint =
      buf.status === 'delivered' ? 'whatsapp/message-delivered' : 'whatsapp/message-read';
    void this.notifyLaravel(endpoint, {
      userId: buf.sessionId,
      messageId: buf.ids[0],
      messageIds: buf.ids,
      timestamp: buf.timestamp,
    });
  }

  private setupListeners(): void {
    const bus = EventBus.getInstance();

    bus.on('session.connected', async (p) => {
      await this.notifyLaravel('whatsapp/connected', {
        userId: p.session_id,
        phoneNumber: p.data.phoneNumber,
        name: p.data.name,
      });
      await this.dispatchUnifiedWebhook(p);
    });

    bus.on('qr.generated', async (p) => {
      await this.notifyLaravel('whatsapp/qr-generated', {
        userId: p.session_id,
        qr: p.data.qr,
      });
      await this.dispatchUnifiedWebhook(p);
    });

    bus.on('session.disconnected', async (p) => {
      await this.notifyLaravel('whatsapp/disconnected', {
        userId: p.session_id,
        reason: p.data.reason || 'disconnected',
      });
      await this.dispatchUnifiedWebhook(p);
    });

    bus.on('message.received', async (p) => {
      await this.notifyLaravel('whatsapp/message-received', {
        userId: p.session_id,
        from: p.from,
        senderPhone: p.data.senderPhone,
        senderName: p.data.senderName,
        senderLid: p.data.senderLid,
        fromMe: p.data.fromMe || false,
        to: p.to,
        body: p.data.body || '',
        type: p.data.type || 'text',
        mediaUrl: p.data.mediaUrl || null,
        mediaMeta: WebhookDispatcher.encodeMediaMeta(p.data.mediaMeta),
        mimeType: p.data.mimeType || null,
        messageId: p.message_id,
        isGroup: p.data.isGroup || false,
        replyToId: p.data.replyToId || null,
        isForwarded: p.data.isForwarded || false,
        msgTimestamp: p.data.msgTimestamp || null,
        timestamp: p.timestamp,
      });
      await this.dispatchUnifiedWebhook(p);
    });

    bus.on('history.sync', async (p) => {
      const conversations = Array.isArray(p.data.conversations) ? p.data.conversations : [];
      // mediaKey arrives as a raw Buffer inside history messages — JSON would
      // serialize it as {type:'Buffer',data:[…]} which /internal/media/download
      // cannot decrypt. Normalize just the key to base64, keep other fields.
      for (const conv of conversations) {
        for (const m of conv?.messages || []) {
          const k = m?.media?.mediaKey;
          if (Buffer.isBuffer(k) || k instanceof Uint8Array) {
            m.media.mediaKey = Buffer.from(k).toString('base64');
          }
        }
      }
      // Fan large history chunks out into multiple requests so each POST
      // stays well under web-server body limits.
      const CHUNK = 15;
      for (let i = 0; i < Math.max(conversations.length, 1); i += CHUNK) {
        await this.notifyLaravel('whatsapp/history-sync', {
          userId: p.session_id,
          syncType: p.data.syncType,
          chunkOrder: p.data.chunkOrder ?? null,
          progress: p.data.progress ?? null,
          requestId: p.data.requestId ?? null,
          pushnames: p.data.pushnames || [],
          lidMappings: p.data.lidMappings || [],
          statusMessages: p.data.statusMessages || [],
          conversations: conversations.slice(i, i + CHUNK),
          chunkIndex: Math.floor(i / CHUNK),
          totalChunks: Math.max(Math.ceil(conversations.length / CHUNK), 1),
          timestamp: p.timestamp,
        });
      }
      await this.dispatchUnifiedWebhook(p);
    });

    bus.on('message.reaction', async (p) => {
      await this.notifyLaravel('whatsapp/message-reaction', {
        userId: p.session_id,
        from: p.from,
        senderPhone: p.data.senderPhone,
        targetMessageId: p.data.targetMessageId,
        emoji: p.data.emoji,
        messageId: p.message_id,
        isGroup: p.data.isGroup || false,
        timestamp: p.timestamp,
      });
      await this.dispatchUnifiedWebhook(p);
    });

    // Anti-delete: a contact revoked a message — Laravel keeps the row and
    // only stamps revoked_at so the CRM shows a "deleted by contact" badge.
    bus.on('message.revoked', async (p) => {
      await this.notifyLaravel('whatsapp/message-revoked', {
        userId: p.session_id,
        messageId: p.message_id,
        from: p.from,
        fromMe: p.data.fromMe || false,
        timestamp: p.timestamp,
      });
      await this.dispatchUnifiedWebhook(p);
    });

    bus.on('message.sent', async (p) => {
      await this.notifyLaravel('whatsapp/message-sent', {
        userId: p.session_id,
        messageId: p.message_id,
        to: p.to,
        message: p.data.message,
        messageType: p.data.messageType || 'text',
        status: 'sent',
        timestamp: p.timestamp,
      });
      await this.dispatchUnifiedWebhook(p);
    });

    bus.on('message.status', (p) => {
      if (p.data.status === 'delivered' || p.data.status === 'read') {
        this.enqueueStatusReceipt(p);
      }
      void this.dispatchUnifiedWebhook(p);
    });

    // Media that finished decrypting after the message row was created —
    // Laravel patches media_url so the CRM can render the file.
    bus.on('message.media', async (p) => {
      await this.notifyLaravel('whatsapp/message-media', {
        userId: p.session_id,
        messageId: p.message_id,
        mediaUrl: p.data.mediaUrl,
        timestamp: p.timestamp,
      });
    });

    // Typing indicators — forwarded so the CRM can show "يكتب الآن…".
    bus.on('chatstate', async (p) => {
      await this.notifyLaravel('whatsapp/chatstate', {
        userId: p.session_id,
        from: p.data.from,
        participant: p.data.participant,
        state: p.data.state,
        timestamp: p.timestamp,
      });
    });
  }

  public async notifyLaravel(endpoint: string, payload: Record<string, any>): Promise<void> {
    const targets = [
      this.config.laravelBaseUrl,
      ...(this.config.extraLaravelBaseUrls || []),
    ];
    await Promise.all(targets.map((base) => this.notifyOne(base, endpoint, payload)));
  }

  // mediaKey travels as a raw Buffer in the event payload — JSON.stringify
  // would emit {type:'Buffer',data:[…]} which Laravel cannot feed back to
  // /internal/media/download (it expects base64). Normalize here so the
  // stored media_meta stays usable for lazy decryption even when the
  // follow-up 'message.media' webhook is lost.
  private static encodeMediaMeta(meta: any): Record<string, any> | null {
    if (!meta || typeof meta !== 'object') return null;
    const k = meta.mediaKey;
    let mediaKey: string | null = null;
    if (Buffer.isBuffer(k) || k instanceof Uint8Array) {
      mediaKey = Buffer.from(k).toString('base64');
    } else if (k && typeof k === 'object' && Array.isArray((k as any).data)) {
      mediaKey = Buffer.from((k as any).data).toString('base64');
    } else if (typeof k === 'string') {
      mediaKey = k;
    }
    if (!mediaKey) return null;
    return {
      mediaKey,
      directPath: meta.directPath || null,
      url: meta.url || null,
      type: meta.type || null,
      mimetype: meta.mimetype || null,
      fileName: meta.fileName || null,
    };
  }

  private async notifyOne(base: string, endpoint: string, payload: Record<string, any>): Promise<void> {
    const url = `${base.replace(/\/$/, '')}/${endpoint.replace(/^\//, '')}`;
    const bodyStr = JSON.stringify(payload);

    const signature = this.config.serviceApiKey
      ? crypto.createHmac('sha256', this.config.serviceApiKey).update(bodyStr).digest('hex')
      : '';

    // A dropped webhook is permanent data loss for some events (message.media
    // carries the only copy of the downloaded file's URL). Retry transient
    // failures — timeouts and 5xx — but not 4xx, which is a dead route.
    let lastErr: unknown = null;
    for (let attempt = 1; attempt <= 3; attempt++) {
      await this.acquireSlot();
      try {
        await axios.post(url, bodyStr, {
          headers: {
            'Content-Type': 'application/json',
            'X-Service-Signature': signature,
          },
          timeout: this.config.timeoutMs || 8000,
        });
        logger.debug({ endpoint, url }, 'Successfully notified Laravel');
        return;
      } catch (err) {
        lastErr = err;
        const status = (err as any)?.response?.status as number | undefined;
        const retryable = !status || status >= 500;
        logger.warn(
          { endpoint, attempt, error: err instanceof Error ? err.message : String(err) },
          'Failed to notify Laravel webhook',
        );
        if (!retryable || attempt === 3) return;
      } finally {
        this.releaseSlot();
      }
      await new Promise((r) => setTimeout(r, 1500 * attempt));
    }
    void lastErr;
  }

  public async dispatchUnifiedWebhook(event: WebhookEventPayload): Promise<void> {
    // Optional unified event channel — only fired when Laravel exposes
    // POST {LARAVEL_API_URL}/webhooks/whatsapp and ENGINE_UNIFIED_WEBHOOK=1.
    if (process.env.ENGINE_UNIFIED_WEBHOOK !== '1') return;
    await this.notifyLaravel('webhooks/whatsapp', event);
  }
}
