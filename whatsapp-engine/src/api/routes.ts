import express, { Request, Response } from 'express';
import axios from 'axios';
import { requireInternalAuth } from './middleware/auth.js';
import { Session } from '../session/Session.js';
import { ISessionStore } from '../session/SessionStore.js';
import { KeyStore } from '../identity/KeyStore.js';
import { MessageManager } from '../message/MessageManager.js';
import { MessageRateLimiter } from '../safety/MessageRateLimiter.js';
import { ConsentManager } from '../safety/ConsentManager.js';
import { IdempotencyManager } from '../safety/IdempotencyManager.js';
import { HealthMonitor } from '../monitoring/HealthMonitor.js';
import { MetricsCollector } from '../monitoring/MetricsCollector.js';
import { EventBus } from '../events/EventBus.js';
import { MediaManager } from '../message/MediaManager.js';
import { FileCredsStore } from '../waweb/WaCreds.js';
import { logger } from '../config/Logger.js';

export interface RouteDependencies {
  store: ISessionStore;
  keyStore: KeyStore;
  activeSessions: Map<string, Session>;
  messageManager: MessageManager;
  rateLimiter: MessageRateLimiter;
  consentManager: ConsentManager;
  idempotencyManager: IdempotencyManager;
  healthMonitor: HealthMonitor;
  metrics: MetricsCollector;
  workerId: string;
}

export function createRoutes(deps: RouteDependencies): express.Router {
  const router = express.Router();
  const {
    store,
    keyStore,
    activeSessions,
    rateLimiter,
    consentManager,
    idempotencyManager,
    healthMonitor,
    metrics,
    workerId,
  } = deps;

  const eventBus = EventBus.getInstance();

  // Helper to get or create live Session instance
  function getOrCreateSession(sessionId: string, userId?: string): Session {
    let session = activeSessions.get(sessionId);
    if (!session) {
      session = new Session({
        sessionId,
        userId,
        store,
        keyStore,
        workerId,
      });
      session.onMessage(async (msg) => {
        const isGroup = (msg.jid || '').includes('@g.us');
        const raw = msg.raw as any;

        // Check for reaction message
        if (msg.type === 'reaction' || raw?.reactionMessage) {
          const reaction = raw?.reactionMessage;
          const targetMsgId = reaction?.key?.id;
          if (!targetMsgId) return;
          const emoji = reaction?.text || ''; // empty string means removed
          eventBus.emit('message.reaction', {
            event: 'message.reaction',
            session_id: sessionId,
            message_id: msg.id,
            from: msg.jid,
            to: session?.phoneNumber,
            timestamp: Math.floor(Date.now() / 1000),
            data: {
              senderPhone: (msg.senderPn || msg.sender || msg.jid || '').split('@')[0],
              targetMessageId: targetMsgId,
              emoji,
              isGroup,
            },
          });
          return;
        }

        const mediaType = msg.type || (msg.text ? 'text' : 'other');
        const body = msg.body ?? msg.text ?? '';
        const mimeType = msg.media?.mimetype;

        // Emit immediately — media download must not delay the webhook or the
        // CRM shows messages seconds late. The file lands via a follow-up
        // 'message.media' event once it decrypts.
        const mediaMeta = msg.media?.mediaKey
          ? {
              mediaKey: msg.media.mediaKey,
              directPath: msg.media.directPath,
              url: msg.media.url,
              type:
                mediaType === 'voice' || mediaType === 'audio'
                  ? 'audio'
                  : mediaType === 'sticker'
                    ? 'image'
                    : mediaType,
              mimetype: mimeType,
            }
          : null;

        if (mediaMeta) {
          const msgId = msg.id;
          void MediaManager.downloadAndSaveMedia({
            directPath: msg.media!.directPath,
            url: msg.media!.url,
            mediaKey: msg.media!.mediaKey as Buffer,
            type: mediaMeta.type as 'image' | 'video' | 'audio' | 'document' | 'voice',
            mimetype: mimeType,
          })
            .then((mediaUrl) => {
              if (mediaUrl) {
                eventBus.emit('message.media', {
                  event: 'message.media',
                  session_id: sessionId,
                  message_id: msgId,
                  timestamp: Math.floor(Date.now() / 1000),
                  data: { mediaUrl },
                });
              }
            })
            .catch(() => undefined);
        }

        eventBus.emit('message.received', {
          event: 'message.received',
          session_id: sessionId,
          message_id: msg.id,
          from: msg.jid,
          to: session?.phoneNumber,
          timestamp: Math.floor(Date.now() / 1000),
          data: {
            senderPhone: (
              msg.senderPn ||
              (msg.jid?.endsWith('@s.whatsapp.net') ? msg.jid : msg.sender?.endsWith('@lid') ? '' : msg.sender) ||
              ''
            ).split('@')[0],
            senderName: msg.notify,
            senderLid: msg.senderLid || (msg.sender?.endsWith('@lid') ? msg.sender : undefined),
            fromMe: msg.fromMe || false,
            body,
            type: mediaType,
            mediaUrl: null,
            mediaMeta,
            mimeType,
            isGroup,
            replyToId: msg.quotedMessageId,
            isForwarded: (msg as any).isForwarded || false,
            msgTimestamp: msg.timestamp,
          },
        });
      });

      // "Delete for everyone" — Laravel flags the stored row, never deletes.
      session.onRevoked((revoke) => {
        eventBus.emit('message.revoked', {
          event: 'message.revoked',
          session_id: sessionId,
          message_id: revoke.messageId,
          from: revoke.jid,
          timestamp: Math.floor(Date.now() / 1000),
          data: { fromMe: revoke.fromMe || false },
        });
      });

      // Receipts + chatstate arrive as raw stanzas — forward them so Laravel
      // can drive live ✓✓ ticks and "typing…" indicators in the CRM.
      session.onNode((node) => {
        try {
          if (node.tag === 'receipt') {
            const id = node.attrs.id;
            if (!id) return;
            const type = node.attrs.type;
            // 'retry' receipts ask us to re-encrypt — handled in WaClient; they
            // are not a delivery confirmation and must not flip message status.
            if (type === 'retry' || type === 'mediaretry' || type === 'server-error') return;
            const status =
              type === 'read' || type === 'played' ? 'read' : 'delivered';
            eventBus.emit('message.status', {
              event: 'message.status',
              session_id: sessionId,
              message_id: id,
              from: node.attrs.from,
              timestamp: Math.floor(Date.now() / 1000),
              data: { status },
            });
          } else if (node.tag === 'chatstate') {
            const children = Array.isArray(node.content) ? node.content : [];
            const child = children.find((c) => typeof c === 'object' && c !== null) as
              | { tag?: string }
              | undefined;
            const state = child?.tag;
            if (state !== 'composing' && state !== 'paused') return;
            eventBus.emit('chatstate', {
              event: 'chatstate',
              session_id: sessionId,
              timestamp: Math.floor(Date.now() / 1000),
              data: {
                from: node.attrs.from,
                participant: node.attrs.participant,
                state,
              },
            });
          }
        } catch {
          /* stanza fan-out must never throw */
        }
      });
      session.onHistorySync(async (payload) => {
        // Resolve LID conversation jids to PN identities before import — the
        // chunk's own lidMappings plus a usync lookup cover most accounts.
        const client = session?.getClient();
        const lidJids = payload.conversations
          .map((c) => c.jid)
          .filter((j) => j && j.endsWith('@lid'));
        const lidResolved = new Map<string, string>();
        if (client && lidJids.length) {
          try {
            const m = await client.resolveLidUsers(lidJids);
            for (const [lid, pn] of m) lidResolved.set(lid, pn);
          } catch { /* keep LID forms */ }
        }
        for (const m of payload.lidMappings || []) {
          if (m?.lid && m?.pn && !lidResolved.has(m.lid)) lidResolved.set(m.lid, m.pn);
        }

        // Bound the payload before it is fanned out to webhooks — each
        // conversation carries at most its most recent messages.
        const bounded = {
          ...payload,
          lidMappings: [
            ...(payload.lidMappings || []),
            ...[...lidResolved.entries()].map(([lid, pn]) => ({ lid, pn })),
          ],
          conversations: payload.conversations.map((c) => ({
            ...c,
            jid: lidResolved.get(c.jid) || c.jid,
            lidJid: c.jid.endsWith('@lid') ? c.jid : undefined,
            messages: c.messages.slice(-60).map((m) => ({
              ...m,
              media: m.media
                ? {
                    ...m.media,
                    mediaKey: m.media.mediaKey
                      ? Buffer.from(m.media.mediaKey).toString('base64')
                      : undefined,
                    fileSha256: m.media.fileSha256
                      ? Buffer.from(m.media.fileSha256).toString('base64')
                      : undefined,
                  }
                : undefined,
            })),
          })),
          // INITIAL_STATUS_V3 items — same normalization (Buffer mediaKey → b64).
          statusMessages: (payload.statusMessages || []).map((m) => ({
            ...m,
            media: m.media
              ? {
                  ...m.media,
                  mediaKey: m.media.mediaKey
                    ? Buffer.from(m.media.mediaKey).toString('base64')
                    : undefined,
                  fileSha256: m.media.fileSha256
                    ? Buffer.from(m.media.fileSha256).toString('base64')
                    : undefined,
                }
              : undefined,
          })),
        };
        eventBus.emit('history.sync', {
          event: 'history.sync',
          session_id: sessionId,
          timestamp: Math.floor(Date.now() / 1000),
          data: bounded as unknown as Record<string, unknown>,
        });
      });
      session.onConnected((info) => {
        // Enforce a single live client per phone number — two sockets on one
        // account trigger a stream conflict that logs both devices out.
        // retire() also wipes the older session's stored credentials so the
        // boot-restorer and reconnect timers can never resurrect it.
        if (info.phoneNumber) {
          for (const [otherId, other] of activeSessions.entries()) {
            if (otherId !== sessionId && other.phoneNumber === info.phoneNumber) {
              logger.warn(
                { sessionId, replacedSessionId: otherId, phoneNumber: info.phoneNumber },
                'Duplicate phone session detected — retiring older session',
              );
              void other.retire('replaced_by_new_login').catch(() => undefined);
            }
          }
        }
        eventBus.emit('session.connected', {
          event: 'session.connected',
          session_id: sessionId,
          timestamp: Math.floor(Date.now() / 1000),
          data: {
            phoneNumber: info.phoneNumber,
            name: info.name,
          },
        });
      });
      session.onDisconnected((info) => {
        eventBus.emit('session.disconnected', {
          event: 'session.disconnected',
          session_id: sessionId,
          timestamp: Math.floor(Date.now() / 1000),
          data: { reason: info.reason || 'disconnected', loggedOut: info.loggedOut },
        });
      });
      session.onQrCode((qr) => {
        eventBus.emit('qr.generated', {
          event: 'qr.generated',
          session_id: sessionId,
          timestamp: Math.floor(Date.now() / 1000),
          data: { qr: qr.dataUrl || qr.raw },
        });
      });
      activeSessions.set(sessionId, session);
    }
    return session;
  }

  // --- Public Health & Metrics ---
  router.get(['/health', '/api/health'], (_req: Request, res: Response) => {
    res.json({
      success: true,
      message: 'Wavixa WhatsApp Session Engine is running',
      engine: 'wavixa-independent-engine',
      cluster: healthMonitor.getClusterHealth(),
      workerId,
    });
  });

  router.get('/internal/health', (_req: Request, res: Response) => {
    res.json({
      success: true,
      status: 'UP',
      timestamp: Date.now(),
      cluster: healthMonitor.getClusterHealth(),
      activeMemorySessions: activeSessions.size,
    });
  });

  router.get('/internal/metrics', (_req: Request, res: Response) => {
    res.set('Content-Type', 'text/plain; version=0.0.4');
    res.send(metrics.toPrometheus());
  });

  // --- Protected Internal Routes (Item 23 in Spec) ---
  router.use(requireInternalAuth);

  // List all sessions
  router.get('/internal/sessions', async (_req: Request, res: Response) => {
    const list = await store.listSessions();
    res.json({ success: true, data: list });
  });

  // Create session slot
  router.post('/internal/sessions', async (req: Request, res: Response) => {
    const { sessionId, userId, phoneNumber } = req.body;
    if (!sessionId) {
      res.status(400).json({ success: false, message: 'sessionId is required' });
      return;
    }

    const session = getOrCreateSession(sessionId, userId);
    if (phoneNumber) session.phoneNumber = phoneNumber;

    res.status(201).json({
      success: true,
      message: 'Session slot initialized',
      data: {
        sessionId: session.sessionId,
        userId: session.userId,
        state: session.getState(),
      },
    });
  });

  // Get session status
  router.get(['/internal/sessions/:id', '/api/whatsapp/status/:id'], async (req: Request, res: Response) => {
    const sessionId = req.params.id!;
    const session = activeSessions.get(sessionId);

    if (session) {
      const state = session.getState();
      const qr = session.getQR();
      const pairing = session.getPairingCode();

      res.json({
        success: true,
        status: state === 'READY' ? 'connected' : state === 'AUTHENTICATING' ? 'pending' : state.toLowerCase(),
        mode: pairing ? 'phone' : qr ? 'qr' : null,
        phoneNumber: session.phoneNumber || null,
        name: session.pushName || null,
        qr: qr?.dataUrl || qr?.raw || null,
        pairingCode: pairing?.code || null,
      });
      return;
    }

    // Try store
    const stored = await store.loadSession(sessionId);
    if (stored) {
      res.json({
        success: true,
        status: stored.state === 'READY' ? 'connected' : stored.state.toLowerCase(),
        phoneNumber: stored.phoneNumber || null,
        name: stored.pushName || null,
      });
      return;
    }

    res.json({ success: true, status: 'disconnected' });
  });

  // Get QR directly
  router.get('/api/whatsapp/qr/:id', (req: Request, res: Response) => {
    const sessionId = req.params.id!;
    const session = activeSessions.get(sessionId);
    const qr = session?.getQR();

    if (qr) {
      res.json({ success: true, qr: qr.dataUrl || qr.raw });
    } else {
      res.status(404).json({ success: false, message: 'No QR code available' });
    }
  });

  // Connect session (Item 12 in Spec: Device Linking)
  router.post(['/internal/sessions/:id/connect', '/api/whatsapp/connect/:id'], async (req: Request, res: Response) => {
    const sessionId = req.params.id!;
    const phoneNumber = req.body.phone_number || req.body.phoneNumber || null;

    try {
      const session = getOrCreateSession(sessionId);

      if (session.getState() === 'READY') {
        res.json({
          success: true,
          message: 'Already connected',
          phoneNumber: session.phoneNumber,
        });
        return;
      }

      const connectionResult = await session.connect(phoneNumber);
      metrics.incConnected();

      res.json({
        success: true,
        message: 'Connection initialized',
        mode: connectionResult.mode,
        pairingCode: connectionResult.code || null,
        qr: connectionResult.qr || null,
      });
    } catch (err) {
      healthMonitor.recordError(sessionId);
      res.status(500).json({
        success: false,
        message: err instanceof Error ? err.message : 'Failed to connect session',
      });
    }
  });

  // Re-request a pairing code for an in-flight session
  router.post('/internal/sessions/:id/pairing-code', async (req: Request, res: Response) => {
    const sessionId = req.params.id!;
    const phoneNumber = req.body.phone_number || req.body.phoneNumber;
    const session = activeSessions.get(sessionId);
    if (!session || !session.getClient()) {
      res.status(409).json({ success: false, message: 'Session is not in an active handshake' });
      return;
    }
    if (!phoneNumber) {
      res.status(400).json({ success: false, message: 'phone_number is required' });
      return;
    }
    try {
      const code = await session.requestPairingCode(phoneNumber);
      res.json({ success: true, pairingCode: code });
    } catch (err) {
      res.status(500).json({ success: false, message: err instanceof Error ? err.message : 'pairing code failed' });
    }
  });

  // Disconnect session
  router.post(
    [
      '/internal/sessions/:id/disconnect',
      '/api/whatsapp/disconnect/:id',
      '/api/whatsapp/delete-session/:id',
    ],
    async (req: Request, res: Response) => {
      const sessionId = req.params.id!;
      const session = activeSessions.get(sessionId);

      if (session) {
        await session.disconnect('manual_disconnect');
        activeSessions.delete(sessionId);
      }

      await store.deleteSession(sessionId);
      metrics.incDisconnected();

      eventBus.emit('session.disconnected', {
        event: 'session.disconnected',
        session_id: sessionId,
        timestamp: Math.floor(Date.now() / 1000),
        data: { reason: 'manual_disconnect' },
      });

      res.json({ success: true, message: 'Session disconnected and cleaned up successfully' });
    },
  );

  /**
   * Resolve a media payload into a buffer. `content` may be an http(s) URL,
   * a data URI, or raw base64.
   */
  async function resolveMediaBuffer(
    content: unknown,
  ): Promise<{ buffer: Buffer; mimetype?: string }> {
    if (Buffer.isBuffer(content)) {
      return { buffer: content };
    }
    if (typeof content !== 'string' || !content) {
      throw new Error('Media content is missing');
    }

    // Mimetypes may carry params (audio/ogg; codecs=opus) — anchor on the
    // `;base64,` marker instead of the first semicolon.
    const dataUri = content.match(/^data:(.+?);base64,(.+)$/s);
    if (dataUri) {
      return { buffer: Buffer.from(dataUri[2]!, 'base64'), mimetype: dataUri[1] };
    }

    if (/^https?:\/\//i.test(content)) {
      if (!MediaManager.isSafeUrl(content)) {
        throw new Error('Media URL rejected by safety policy');
      }
      const resp = await axios.get(content, {
        responseType: 'arraybuffer',
        timeout: 60_000,
        maxContentLength: 100 * 1024 * 1024,
      });
      return {
        buffer: Buffer.from(resp.data),
        mimetype: typeof resp.headers['content-type'] === 'string'
          ? resp.headers['content-type'].split(';')[0]
          : undefined,
      };
    }

    // Treat anything else as raw base64.
    return { buffer: Buffer.from(content, 'base64') };
  }

  // Send message endpoint (Item 13, 14, 15, 17, 18 in Spec)
  router.post(['/internal/messages/send', '/api/whatsapp/send-message', '/api/whatsapp/send-media'], async (req: Request, res: Response) => {
    const {
      userId,
      sessionId: reqSessionId,
      to,
      message,
      content,
      clientMessageId,
      type,
      caption,
      fileName,
      mimeType,
      seconds,
      replyTo,
      forwarded,
    } = req.body;
    const sessionId = reqSessionId || String(userId);
    const msgType: string = type || 'text';

    if (!sessionId || !to) {
      res.status(400).json({ success: false, message: 'sessionId and destination "to" are required' });
      return;
    }

    // Real send requires a live, authenticated WhatsApp session.
    const session = activeSessions.get(sessionId);

    const sessionState = session?.getState();
    if (!session || !session.getClient() || (sessionState !== 'READY' && sessionState !== 'CONNECTED')) {
      res.status(409).json({
        success: false,
        message: `Session is not connected to WhatsApp (state: ${sessionState || 'missing'}) — pair a device first`,
      });
      return;
    }

    // 1. Opt-in consent enforcement (Spec item 17)
    const consent = consentManager.canSend(to);
    if (!consent.allowed) {
      res.status(403).json({ success: false, message: `Opt-in check rejected: ${consent.reason}` });
      return;
    }

    // 2. Duplicate protection / Idempotency (Spec item 18)
    const messageToken = clientMessageId || req.body.messageId || String(Date.now());
    const isNew = idempotencyManager.acquire(sessionId, messageToken);
    if (!isNew) {
      res.status(409).json({ success: false, message: 'Duplicate message submission rejected' });
      return;
    }

    // 3. Multi-dimensional Rate Limiting (Spec item 15)
    const rateCheck = rateLimiter.checkLimit(`device:${sessionId}`, {
      maxRequests: 30,
      windowMs: 60000,
    });

    if (!rateCheck.allowed) {
      res.status(429).json({
        success: false,
        message: 'Device sending rate limit reached. Delaying next message.',
        retryAfterMs: rateCheck.resetMs,
      });
      return;
    }

    // Quoted-reply / forwarded context — matches the proto.ContextInfo the
    // official client attaches inside each message type.
    const contextInfo: Record<string, unknown> = {};
    if (replyTo && typeof replyTo === 'object' && replyTo.stanzaId) {
      contextInfo.stanzaId = String(replyTo.stanzaId);
      if (replyTo.participant) contextInfo.participant = String(replyTo.participant);
      if (replyTo.remoteJid) contextInfo.remoteJid = String(replyTo.remoteJid);
      if (replyTo.quotedMessage && typeof replyTo.quotedMessage === 'object') {
        contextInfo.quotedMessage = replyTo.quotedMessage;
      }
    }
    if (forwarded) {
      contextInfo.isForwarded = true;
      contextInfo.forwardingScore = 1;
    }
    const hasCtx = Object.keys(contextInfo).length > 0;

    try {
      let result: { id: string };
      let messageLabel: string;

      if (msgType === 'contact') {
        const displayName =
          typeof content === 'object' && content !== null
            ? content.displayName || content.display_name || 'Contact'
            : String(content || 'Contact');
        const phone =
          typeof content === 'object' && content !== null
            ? String(content.phone || to)
            : String(to);
        result = await session.getClient()!.sendContactMessage(to, { displayName, phone });
        messageLabel = displayName;
      } else if (msgType === 'location') {
        const loc = typeof content === 'object' && content !== null ? content : {};
        result = await session.getClient()!.sendLocationMessage(to, {
          latitude: parseFloat(loc.latitude ?? loc.lat ?? 0),
          longitude: parseFloat(loc.longitude ?? loc.lng ?? 0),
          name: loc.name || undefined,
          address: loc.address || undefined,
        });
        messageLabel = loc.name || 'location';
      } else if (msgType === 'text' || !type) {
        const textContent = message || (typeof content === 'string' ? content : '') || '';
        result = await session.sendTextMessage(
          to,
          textContent,
          hasCtx ? contextInfo : undefined,
        );
        messageLabel = textContent;
      } else {
        const media = await resolveMediaBuffer(content);
        result = await session.sendMediaMessage(to, {
          type: msgType as 'image' | 'video' | 'document' | 'audio' | 'voice',
          buffer: media.buffer,
          mimetype: mimeType || media.mimetype,
          caption,
          fileName,
          seconds: typeof seconds === 'number' ? seconds : undefined,
          contextInfo: hasCtx ? contextInfo : undefined,
        });
        messageLabel = caption || `[${msgType}]`;
      }

      metrics.incSent();
      healthMonitor.recordActivity(sessionId);

      eventBus.emit('message.sent', {
        event: 'message.sent',
        session_id: sessionId,
        message_id: result.id,
        to,
        timestamp: Math.floor(Date.now() / 1000),
        data: {
          message: messageLabel,
          messageType: msgType,
        },
      });

      res.json({
        success: true,
        messageId: result.id,
        status: 'sent',
      });
    } catch (err) {
      res.status(500).json({
        success: false,
        message: err instanceof Error ? err.message : 'Message send failed',
      });
    }
  });

  // Send Reaction endpoint
  router.post(['/internal/messages/reaction', '/api/whatsapp/send-reaction'], async (req: Request, res: Response) => {
    const { userId, sessionId: reqSessionId, to, messageId, emoji, fromMe } = req.body;
    const sessionId = reqSessionId || String(userId);

    if (!sessionId || !to || !messageId) {
      res.status(400).json({ success: false, message: 'sessionId, to, and messageId are required' });
      return;
    }

    const session = activeSessions.get(sessionId);
    const sessionState = session?.getState();
    if (!session || !session.getClient() || (sessionState !== 'READY' && sessionState !== 'CONNECTED')) {
      res.status(409).json({
        success: false,
        message: `Session is not connected to WhatsApp (state: ${sessionState || 'missing'})`,
      });
      return;
    }

    try {
      const result = await session.sendReactionMessage(to, {
        messageId,
        emoji: emoji ?? '',
        fromMe: Boolean(fromMe),
      });

      res.json({
        success: true,
        messageId: result.id,
      });
    } catch (err) {
      res.status(500).json({
        success: false,
        message: err instanceof Error ? err.message : 'Reaction failed',
      });
    }
  });

  // "Delete for everyone" endpoint — sends a protocolMessage REVOKE.
  router.post(['/internal/messages/delete', '/api/whatsapp/delete-message'], async (req: Request, res: Response) => {
    const { userId, sessionId: reqSessionId, to, messageId } = req.body;
    const sessionId = reqSessionId || String(userId);

    if (!sessionId || !to || !messageId) {
      res.status(400).json({ success: false, message: 'sessionId, to, and messageId are required' });
      return;
    }

    const session = activeSessions.get(sessionId);
    const sessionState = session?.getState();
    if (!session || !session.getClient() || (sessionState !== 'READY' && sessionState !== 'CONNECTED')) {
      res.status(409).json({
        success: false,
        message: `Session is not connected to WhatsApp (state: ${sessionState || 'missing'})`,
      });
      return;
    }

    try {
      const result = await session.sendRevokeMessage(to, { messageId, fromMe: true });
      res.json({ success: true, messageId: result.id });
    } catch (err) {
      res.status(500).json({
        success: false,
        message: err instanceof Error ? err.message : 'Delete failed',
      });
    }
  });

  /**
   * Decrypt + store a WhatsApp CDN media blob on demand. Used by Laravel to
   * lazily resolve media for history-synced messages — the mediaKey arrives
   * base64-encoded.
   */
  router.post('/internal/media/download', async (req: Request, res: Response) => {
    const { mediaKey, directPath, url, type, mimetype } = req.body || {};
    if (!mediaKey || (!directPath && !url)) {
      res.status(400).json({ success: false, message: 'mediaKey and directPath/url are required' });
      return;
    }
    try {
      const mediaUrl = await MediaManager.downloadAndSaveMedia({
        directPath: directPath || undefined,
        url: url || undefined,
        mediaKey: Buffer.from(String(mediaKey), 'base64'),
        type: (['image', 'video', 'audio', 'document', 'voice'].includes(type) ? type : 'document') as 'image' | 'video' | 'audio' | 'document' | 'voice',
        mimetype,
      });
      if (!mediaUrl) {
        res.status(502).json({ success: false, message: 'Media download/decryption failed' });
        return;
      }
      res.json({ success: true, mediaUrl });
    } catch (err) {
      res.status(500).json({
        success: false,
        message: err instanceof Error ? err.message : 'Media download failed',
      });
    }
  });

  // Check Number existence — real usync query when a live session exists.
  router.post('/api/whatsapp/check-number', async (req: Request, res: Response) => {
    const to = String(req.body.to || '').replace(/\D/g, '');
    const sessionId = String(req.body.sessionId || req.body.userId || '');

    if (!to) {
      res.status(400).json({ success: false, message: '"to" is required' });
      return;
    }

    const session = activeSessions.get(sessionId);
    if (!session) {
      res.status(409).json({ success: false, message: 'WhatsApp session is not connected' });
      return;
    }

    const exists = await session.checkNumberOnWhatsApp(to);
    if (exists === null) {
      // Query unavailable — report success without a definitive answer so the
      // caller can fall back to its own policy.
      res.json({ success: true, registered: true, phone: to, verified: false });
      return;
    }

    res.json({ success: true, registered: exists, phone: to, verified: true });
  });

  // Resolve LID JIDs to phone-number JIDs via the usync 'lid' protocol.
  router.post('/internal/lid/resolve', async (req: Request, res: Response) => {
    const sessionId = String(req.body.sessionId || req.body.userId || '');
    const lids: string[] = Array.isArray(req.body.lids) ? req.body.lids : [];
    const session = activeSessions.get(sessionId);
    const sessionState = session?.getState();
    if (!session || (sessionState !== 'READY' && sessionState !== 'CONNECTED')) {
      res.status(409).json({
        success: false,
        message: `Session is not connected (state: ${sessionState || 'missing'})`,
      });
      return;
    }
    const client = session.getClient();
    if (!client) {
      res.status(409).json({ success: false, message: 'Client not initialized' });
      return;
    }
    try {
      const map = await client.resolveLidUsers(lids.slice(0, 50).map(String));
      res.json({ success: true, mappings: Object.fromEntries(map) });
    } catch (err) {
      res.status(500).json({
        success: false,
        message: err instanceof Error ? err.message : 'LID resolution failed',
      });
    }
  });

  // Resolve profile picture URLs for a batch of JIDs via usync 'picture'.
  router.post('/internal/picture/resolve', async (req: Request, res: Response) => {
    const sessionId = String(req.body.sessionId || req.body.userId || '');
    const jids = Array.isArray(req.body.jids) ? req.body.jids : [];
    const session = activeSessions.get(sessionId);
    const sessionState = session?.getState();
    if (!session || (sessionState !== 'READY' && sessionState !== 'CONNECTED')) {
      res.status(409).json({
        success: false,
        message: `Session is not connected (state: ${sessionState || 'missing'})`,
      });
      return;
    }
    const client = session.getClient();
    if (!client) {
      res.status(409).json({ success: false, message: 'Client not initialized' });
      return;
    }
    try {
      const map = await client.resolveProfilePics(jids.slice(0, 200).map(String));
      res.json({ success: true, pictures: Object.fromEntries(map) });
    } catch (err) {
      res.status(500).json({
        success: false,
        message: err instanceof Error ? err.message : 'Picture resolution failed',
      });
    }
  });

  // Trigger an on-demand full history sync — the phone streams chunks back
  // asynchronously and each arrives at Laravel via whatsapp/history-sync.
  router.post(['/internal/history/sync', '/api/whatsapp/history-sync'], async (req: Request, res: Response) => {
    const sessionId = String(req.body.sessionId || req.body.userId || '');
    const session = activeSessions.get(sessionId);
    const sessionState = session?.getState();
    if (!session || (sessionState !== 'READY' && sessionState !== 'CONNECTED')) {
      res.status(409).json({
        success: false,
        message: `Session is not connected (state: ${sessionState || 'missing'})`,
      });
      return;
    }
    try {
      const result = await session.requestFullHistorySync();
      res.json({ success: true, requestId: result.id });
    } catch (err) {
      res.status(500).json({
        success: false,
        message: err instanceof Error ? err.message : 'History sync request failed',
      });
    }
  });

  // Ask the phone to resend specific messages by key — recovers stanzas
  // that arrived but could not be decrypted (e.g. statuses posted before
  // sender-key support existed).
  router.post('/internal/messages/resend', async (req: Request, res: Response) => {
    const sessionId = String(req.body.sessionId || req.body.userId || '');
    const session = activeSessions.get(sessionId);
    const sessionState = session?.getState();
    if (!session || (sessionState !== 'READY' && sessionState !== 'CONNECTED')) {
      res.status(409).json({
        success: false,
        message: `Session is not connected (state: ${sessionState || 'missing'})`,
      });
      return;
    }
    const keys = Array.isArray(req.body.keys) ? req.body.keys : [];
    if (!keys.length) {
      res.status(400).json({ success: false, message: 'keys[] required' });
      return;
    }
    try {
      const result = await session.requestMessageResend(keys.slice(0, 20).map((k: any) => ({
        remoteJid: String(k.remoteJid || ''),
        id: String(k.id || ''),
        fromMe: Boolean(k.fromMe),
        participant: k.participant ? String(k.participant) : undefined,
      })));
      res.json({ success: true, requestId: result.id });
    } catch (err) {
      res.status(500).json({
        success: false,
        message: err instanceof Error ? err.message : 'Resend request failed',
      });
    }
  });

  // Ask the phone to regenerate a history-sync chunk — used to pull the
  // status@broadcast conversation (INITIAL_STATUS_V3, syncType 1) on demand.
  router.post('/internal/history/chunk-retry', async (req: Request, res: Response) => {
    const sessionId = String(req.body.sessionId || req.body.userId || '');
    const session = activeSessions.get(sessionId);
    const sessionState = session?.getState();
    if (!session || (sessionState !== 'READY' && sessionState !== 'CONNECTED')) {
      res.status(409).json({
        success: false,
        message: `Session is not connected (state: ${sessionState || 'missing'})`,
      });
      return;
    }
    try {
      const result = await session.requestHistoryChunkRetry(
        Number(req.body.syncType ?? 1),
        Number(req.body.chunkOrder ?? 0),
        req.body.regenerateChunk !== false,
      );
      res.json({ success: true, requestId: result.id });
    } catch (err) {
      res.status(500).json({
        success: false,
        message: err instanceof Error ? err.message : 'Chunk retry request failed',
      });
    }
  });

  // On-demand history for a single chat — anchored at the oldest known
  // message key; the phone streams the chunk back via whatsapp/history-sync.
  router.post(['/internal/history/chat', '/api/whatsapp/history-sync-chat'], async (req: Request, res: Response) => {
    const sessionId = String(req.body.sessionId || req.body.userId || '');
    const chatJid = String(req.body.chatJid || '');
    const oldestMsgId = String(req.body.oldestMsgId || '');
    if (!chatJid) {
      res.status(400).json({ success: false, message: 'chatJid is required' });
      return;
    }
    const session = activeSessions.get(sessionId);
    const sessionState = session?.getState();
    if (!session || (sessionState !== 'READY' && sessionState !== 'CONNECTED')) {
      res.status(409).json({
        success: false,
        message: `Session is not connected (state: ${sessionState || 'missing'})`,
      });
      return;
    }
    try {
      const result = await session.requestChatHistory(
        chatJid,
        oldestMsgId,
        Boolean(req.body.oldestMsgFromMe),
        Number(req.body.oldestMsgTimestampMs || 0),
        Number(req.body.count || 50),
      );
      res.json({ success: true, requestId: result.id });
    } catch (err) {
      res.status(500).json({
        success: false,
        message: err instanceof Error ? err.message : 'Chat history request failed',
      });
    }
  });

  // Resolve WhatsApp @usernames for a batch of chat jids — Laravel calls
  // this lazily for conversations that predate username capture, or after
  // history chunks that did not carry the field.
  router.post('/internal/usernames', async (req: Request, res: Response) => {
    const sessionId = String(req.body.sessionId || req.body.userId || '');
    const session = activeSessions.get(sessionId);
    const sessionState = session?.getState();
    if (!session || (sessionState !== 'READY' && sessionState !== 'CONNECTED')) {
      res.status(409).json({
        success: false,
        message: `Session is not connected (state: ${sessionState || 'missing'})`,
      });
      return;
    }
    const jids = Array.isArray(req.body.jids) ? req.body.jids.slice(0, 100).map(String) : [];
    try {
      const client = session.getClient();
      if (!client) throw new Error('Client not ready');
      const map = await client.fetchUsernames(jids);
      res.json({ success: true, usernames: Object.fromEntries(map) });
    } catch (err) {
      res.status(500).json({
        success: false,
        message: err instanceof Error ? err.message : 'Username query failed',
      });
    }
  });

  // Send Presence (typing indicators / availability)
  router.post('/api/whatsapp/send-presence', async (req: Request, res: Response) => {
    const sessionId = String(req.body.sessionId || req.body.userId || '');
    const session = activeSessions.get(sessionId);
    if (!session) {
      res.status(409).json({ success: false, message: 'WhatsApp session is not connected' });
      return;
    }
    const state = ['composing', 'paused', 'available', 'unavailable'].includes(req.body.state)
      ? req.body.state
      : 'available';
    await session.sendPresenceUpdate(state, req.body.to);
    res.json({ success: true });
  });

  // Read receipts — marks messages read on WhatsApp (sender sees blue ticks)
  router.post('/internal/messages/read', async (req: Request, res: Response) => {
    const sessionId = String(req.body.sessionId || req.body.userId || '');
    const session = activeSessions.get(sessionId);
    if (!session) {
      res.status(409).json({ success: false, message: 'WhatsApp session is not connected' });
      return;
    }
    const chatJid = String(req.body.chatJid || req.body.to || '');
    const ids = Array.isArray(req.body.messageIds) ? req.body.messageIds.map(String) : [];
    if (!chatJid || !ids.length) {
      res.status(422).json({ success: false, message: 'chatJid and messageIds are required' });
      return;
    }
    await session.sendReadReceipt(chatJid, ids.slice(0, 50), req.body.participant);
    res.json({ success: true });
  });

  // Restore persisted (paired) sessions on boot — reconnect them staggered
  void (async () => {
    const paired = new FileCredsStore().listPaired();
    for (const sessionId of paired) {
      try {
        const session = getOrCreateSession(sessionId);
        session.connect().catch((err) => {
          logger.warn({ sessionId, err }, 'Persisted session reconnect failed');
        });
        await new Promise((r) => setTimeout(r, 3000));
      } catch (err) {
        logger.warn({ sessionId, err }, 'Failed to restore persisted session');
      }
    }
  })();

  return router;
}
