import { EventEmitter } from 'events';
import crypto from 'crypto';
import fs from 'fs';
import path from 'path';
import axios from 'axios';
import { WebSocketTransport } from '../transport/WebSocketTransport.js';
import { WaNoise } from './WaNoise.js';
import { WaProto, type IKeyPair } from '../protocol/proto/WaProto.js';
import { BinaryEncoder } from '../protocol/binary/Encoder.js';
import {
  BinaryNode,
  getBinaryNodeChild,
  getBinaryNodeChildren,
} from '../protocol/binary/BinaryNode.js';
import { jidDecode, jidEncode } from '../protocol/binary/jid.js';
import { SignalStore, prefixKeyInPublicKey } from '../signal/SignalStore.js';
import { SessionBuilder, type DeviceBundle } from '../signal/SessionBuilder.js';
import { SessionCipher } from '../signal/SessionCipher.js';
import { SenderKeyStore } from '../signal/SenderKeyStore.js';
import { SenderKeyCipher } from '../signal/SenderKeyCipher.js';
import {
  KEEP_ALIVE_INTERVAL_MS,
  QR_TTL_MS,
  QR_TTL_SUBSEQUENT_MS,
  S_WHATSAPP_NET,
  WA_ORIGIN,
  WA_WEBSOCKET_URL,
} from './WaConstants.js';
import {
  buildPairingQRData,
  configureSuccessfulPairing,
  encodeBigEndian,
  generateLoginNode,
  generateMdTagPrefix,
  generateRegistrationNode,
  getCompanionPlatformDisplay,
  getCompanionPlatformId,
  xmppPreKey,
  xmppSignedPreKey,
} from './WaPayloads.js';
import { generateWaKeyPair, BufferJSON, type WaCreds } from './WaCreds.js';
import { MediaManager, type MediaType } from '../message/MediaManager.js';
import {
  normalizeMessageContent,
  processHistorySyncNotification,
  unwrapMessage,
  type NormalizedMedia,
  type ParsedHistorySync,
} from '../message/HistorySyncProcessor.js';
import { logger } from '../config/Logger.js';

const DEBUG_STANZAS = process.env.ENGINE_DEBUG_STANZAS === '1';

const stanzaSummary = (n: BinaryNode): unknown => ({
  tag: n.tag,
  attrs: n.attrs,
  children: Array.isArray(n.content)
    ? n.content.map((c) => ({
        tag: c.tag,
        attrs: c.attrs,
        kids: Array.isArray(c.content) ? c.content.map((g) => g.tag) : typeof c.content,
      }))
    : typeof n.content,
});

export type WaConnectionState = 'connecting' | 'qr' | 'paired' | 'open' | 'close';

export interface WaClientEvents {
  qr: (qr: string) => void;
  open: (me: { id: string; lid?: string }) => void;
  message: (msg: {
    jid: string;
    sender: string;
    senderPn?: string;
    senderLid?: string;
    notify?: string;
    id: string;
    timestamp?: number;
    fromMe?: boolean;
    edited?: boolean;
    text?: string;
    type?: string;
    body?: string;
    media?: NormalizedMedia;
    quotedMessageId?: string;
    quotedParticipant?: string;
    isForwarded?: boolean;
    raw: unknown;
  }) => void;
  'history.sync': (payload: ParsedHistorySync) => void;
  'message.revoked': (revoke: {
    jid: string;
    messageId: string;
    fromMe?: boolean;
    revoker?: string;
  }) => void;
  close: (info: { reason?: string; statusCode?: number; shouldReconnect: boolean }) => void;
  'creds.update': (update: Partial<WaCreds>) => void;
  'pairing-code': (code: string) => void;
  stanza: (node: BinaryNode) => void;
  error: (err: Error) => void;
  __raw_frame: (frame: Uint8Array | BinaryNode) => void;
}

export declare interface WaClient {
  on<E extends keyof WaClientEvents>(event: E, listener: WaClientEvents[E]): this;
  emit<E extends keyof WaClientEvents>(event: E, ...args: Parameters<WaClientEvents[E]>): boolean;
}

const aesEncryptCTR = (plaintext: Uint8Array, key: Uint8Array, iv: Uint8Array): Buffer => {
  const cipher = crypto.createCipheriv('aes-256-ctr', key, iv);
  return Buffer.concat([cipher.update(plaintext), cipher.final()]);
};

const derivePairingCodeKey = async (pairingCode: string, salt: Buffer): Promise<Buffer> => {
  return new Promise((resolve, reject) => {
    crypto.pbkdf2(
      pairingCode,
      salt,
      2 << 16,
      32,
      'sha256',
      (err, key) => (err ? reject(err) : resolve(key)),
    );
  });
};

const CROCKFORD_CHARACTERS = '123456789ABCDEFGHJKLMNPQRSTVWXYZ';
export const bytesToCrockford = (buffer: Buffer): string => {
  let value = 0;
  let bitCount = 0;
  const crockford: string[] = [];
  for (const element of buffer) {
    value = (value << 8) | (element & 0xff);
    bitCount += 8;
    while (bitCount >= 5) {
      crockford.push(CROCKFORD_CHARACTERS.charAt((value >>> (bitCount - 5)) & 31));
      bitCount -= 5;
    }
  }
  if (bitCount > 0) {
    crockford.push(CROCKFORD_CHARACTERS.charAt((value << (5 - bitCount)) & 31));
  }
  return crockford.join('');
};

/**
 * WaClient — a real WhatsApp Web multi-device client implementing the
 * live wire protocol: Noise_XX handshake, WABinary stanzas, QR pairing,
 * phone-number pairing, pre-key upload, keepalive, and stanza routing.
 */
export class WaClient extends EventEmitter {
  private transport: WebSocketTransport | null = null;
  private noise: WaNoise | null = null;
  private readonly encoder = new BinaryEncoder();
  private readonly uqTagId = generateMdTagPrefix();
  private epoch = 1;

  private pendingQueries = new Map<string, { resolve: (n: BinaryNode) => void; reject: (e: Error) => void; timer: NodeJS.Timeout }>();
  private keepAliveTimer: NodeJS.Timeout | null = null;
  private qrTimer: NodeJS.Timeout | null = null;
  private lastDateRecv = 0;
  private closed = true;
  private expectsRestartAfterPairing = false;

  public state: WaConnectionState = 'close';
  public readonly signalStore: SignalStore;
  public readonly senderKeyStore: SenderKeyStore;
  private readonly senderKeyCipher: SenderKeyCipher;

  constructor(
    private readonly creds: WaCreds,
    private readonly opts: {
      url?: string;
      connectTimeoutMs?: number;
      sessionId?: string;
      onCredsUpdate?: (creds: WaCreds) => void;
    } = {},
  ) {
    super();
    this.signalStore = new SignalStore(
      creds as WaCreds & { signalSessions?: Record<string, import('../signal/SessionTypes.js').SessionRecordData> },
      () => this.saveCreds(),
    );
    this.senderKeyStore = new SenderKeyStore(
      creds as WaCreds & { signalSenderKeys?: Record<string, import('../signal/SenderKeyStore.js').SenderKeyStateData> },
      () => this.saveCreds(),
    );
    this.senderKeyCipher = new SenderKeyCipher(this.senderKeyStore);
    this.loadSentCache();
  }

  public get isOpen(): boolean {
    return this.transport?.isConnected === true && !this.closed;
  }

  private generateMessageTag(): string {
    return `${this.uqTagId}${this.epoch++}`;
  }

  // ------------------------------------------------------------------
  // Connection lifecycle
  // ------------------------------------------------------------------

  public async connect(): Promise<void> {
    if (!this.closed) {
      return;
    }
    this.closed = false;
    this.state = 'connecting';

    const ephemeralKeyPair: IKeyPair = generateWaKeyPair();
    this.noise = new WaNoise(ephemeralKeyPair);
    const noise = this.noise;
    const eph = ephemeralKeyPair;

    this.transport = new WebSocketTransport({
      origin: WA_ORIGIN,
      pingIntervalMs: 0, // keepalive handled at stanza level
      connectTimeoutMs: this.opts.connectTimeoutMs ?? 20_000,
    });

    this.transport.onMessage((data: Buffer) => {
      this.lastDateRecv = Date.now();
      noise.decodeFrame(data, (frame) => void this.onFrame(frame));
    });

    this.transport.onClose((code, reason) => {
      void this.end(new Error(`Connection Terminated (${code}: ${reason})`));
    });

    this.transport.onError((err) => {
      this.emit('error', err);
    });

    await this.transport.connect(this.opts.url || WA_WEBSOCKET_URL);
    await this.validateConnection(eph);
  }

  private async validateConnection(ephemeralKeyPair: IKeyPair): Promise<void> {
    const noise = this.noise!;

    // -> e
    const helloBytes = await WaProto.encodeHandshakeMessage({
      clientHello: { ephemeral: ephemeralKeyPair.public },
    });

    const result = await this.awaitNextFrame(helloBytes);
    const handshake = await WaProto.decodeHandshakeMessage(Buffer.from(result));

    // <- e, ee, s, es + cert validation; -> keyEnc
    const keyEnc = await noise.processHandshake(handshake.serverHello, this.creds.noiseKey);

    // -> s, se, clientPayload
    const clientPayload = this.creds.me
      ? await generateLoginNode(this.creds.me.id)
      : await generateRegistrationNode(this.creds);

    const payloadEnc = noise.encrypt(clientPayload);
    await this.sendRawMessage(
      await WaProto.encodeHandshakeMessage({
        clientFinish: { static: keyEnc, payload: payloadEnc },
      }),
    );

    noise.finishInit();
    this.startKeepAlive();
  }

  private awaitNextFrame(sendMsg?: Uint8Array): Promise<Uint8Array> {
    const timeoutMs = this.opts.connectTimeoutMs ?? 20_000;
    return new Promise<Uint8Array>((resolve, reject) => {
      const timer = setTimeout(() => {
        this.off('__raw_frame', onFrame);
        reject(new Error('Timed out waiting for handshake response'));
      }, timeoutMs);

      const onFrame = (frame: Uint8Array | BinaryNode) => {
        clearTimeout(timer);
        this.off('__raw_frame', onFrame);
        resolve(frame as Uint8Array);
      };

      this.on('__raw_frame', onFrame);

      if (sendMsg) {
        this.sendRawMessage(sendMsg).catch((err) => {
          clearTimeout(timer);
          this.off('__raw_frame', onFrame);
          reject(err);
        });
      }
    });
  }

  private async onFrame(frame: Uint8Array | BinaryNode): Promise<void> {
    // Pre-transport frames (handshake) arrive as raw bytes
    if (frame instanceof Uint8Array || Buffer.isBuffer(frame)) {
      this.emit('__raw_frame', frame);
      return;
    }

    this.dispatchStanza(frame).catch((err) => {
      this.emit('error', err instanceof Error ? err : new Error(String(err)));
    });
  }

  private async dispatchStanza(node: BinaryNode): Promise<void> {
    const msgId = node.attrs.id;

    if (DEBUG_STANZAS) {
      logger.info({ me: this.creds.me?.id, in: stanzaSummary(node) }, 'stanza <<');
    }

    // resolve pending query
    if (msgId && this.pendingQueries.has(msgId)) {
      const q = this.pendingQueries.get(msgId)!;
      this.pendingQueries.delete(msgId);
      clearTimeout(q.timer);
      q.resolve(node);
      return;
    }

    const child0 = Array.isArray(node.content) ? node.content[0]?.tag : '';

    switch (node.tag) {
      case 'xmlstreamend':
        await this.end(new Error('Connection Terminated by Server'));
        return;

      case 'stream:error': {
        const [reasonNode] = getBinaryNodeChildren(node);
        const code = node.attrs.code ? +node.attrs.code : undefined;
        await this.end(
          Object.assign(new Error(`Stream Errored (${reasonNode?.tag || node.attrs.code || 'unknown'})`), {
            statusCode: code,
          }),
        );
        return;
      }

      case 'failure': {
        const reason = +(node.attrs.reason || 500);
        await this.end(Object.assign(new Error('Connection Failure'), { statusCode: reason }));
        return;
      }

      case 'success': {
        await this.onLoginSuccess(node);
        return;
      }

      case 'iq': {
        if (node.attrs.type === 'set' && child0 === 'pair-device') {
          await this.onPairDeviceIq(node);
        } else if (child0 === 'pair-success') {
          await this.onPairSuccess(node);
        }
        break;
      }

      case 'ib': {
        if (child0 === 'offline_preview') {
          // Echo the pending count back — the server keeps the socket in
          // "draining backlog" mode until the full queue is acked, and
          // won't push live messages while it thinks items remain.
          const preview = getBinaryNodeChild(node, 'offline_preview');
          const count = preview?.attrs?.count || '100';
          await this.sendNode({
            tag: 'ib',
            attrs: {},
            content: [{ tag: 'offline_batch', attrs: { count } }],
          });
        } else if (child0 === 'edge_routing') {
          const edgeRoutingNode = getBinaryNodeChild(node, 'edge_routing');
          const routingInfo = getBinaryNodeChild(edgeRoutingNode, 'routing_info');
          if (routingInfo?.content && Buffer.isBuffer(routingInfo.content)) {
            this.creds.routingInfo = Buffer.from(routingInfo.content);
            this.saveCreds();
            // The server expects the routing info echoed back to complete
            // edge-routing setup — without the echo it never marks the
            // socket fully live and holds message pushes for reconnect.
            await this.sendNode({
              tag: 'ib',
              attrs: {},
              content: [
                {
                  tag: 'edge_routing',
                  attrs: {},
                  content: [
                    {
                      tag: 'routing_info',
                      attrs: {},
                      content: routingInfo.content,
                    },
                  ],
                },
              ],
            });
          }
        } else if (child0 === 'dirty') {
          // Server tells us an app-state collection is dirty — resync so
          // the device is marked current and eligible for live pushes.
          void this.resyncAppState().catch(() => undefined);
        } else if (child0 === 'downgrade_webclient') {
          await this.end(new Error('Multi-device beta not joined'));
          return;
        }
        break;
      }

      case 'message': {
        this.emit('stanza', node);
        // Ack immediately — decrypt/LID-resolution must not hold up the
        // server ack or WhatsApp will re-queue and redeliver the stanza.
        void this.ackMessageNode(node).catch(() => undefined);
        // decrypt enc payloads and emit 'message' event
        const encNodes = getBinaryNodeChildren(node, 'enc');
        let decryptedAny = false;
        for (const encNode of encNodes) {
          try {
            const encType = encNode.attrs.type;
            const ciphertext = encNode.content as Buffer;
            if (!Buffer.isBuffer(ciphertext)) continue;
            const from = node.attrs.participant || node.attrs.from!;
            const cipher = new SessionCipher(this.signalStore, from);
            const plaintext =
              encType === 'pkmsg'
                ? await cipher.decryptPreKeyWhisperMessage(ciphertext)
                : encType === 'msg'
                  ? await cipher.decryptWhisperMessage(ciphertext)
                  : encType === 'skmsg'
                    ? await this.senderKeyCipher.decrypt(
                        ciphertext,
                        String(node.attrs.from),
                        from,
                      )
                    : undefined;
            if (!plaintext) continue;
            decryptedAny = true;
            // strip random pad (last byte = pad length)
            const padLen = plaintext[plaintext.length - 1]!;
            const unpadded =
              padLen > 0 && padLen <= plaintext.length
                ? plaintext.subarray(0, plaintext.length - padLen)
                : plaintext;
            const decoded = await WaProto.decodeMessage(Buffer.from(unpadded));

            // A pairwise-encrypted message may carry a sender-key distribution
            // for a group/broadcast the author posts to — store it so later
            // skmsg payloads from that author decrypt.
            void this.processSenderKeyDistribution(decoded, from).catch((e) =>
              logger.debug({ err: e }, 'sender-key distribution processing failed'),
            );

            // Protocol-level payloads (history sync, key shares, edits) are
            // not chat messages — route them to their own handlers.
            const protocol =
              decoded.protocolMessage || unwrapMessage(decoded).message?.protocolMessage;
            if (protocol?.historySyncNotification) {
              void this.handleHistorySyncNotification(protocol.historySyncNotification);
              continue;
            }
            // "Delete for everyone" arrives as protocolMessage{type: REVOKE,
            // key: <the deleted message's key>}. Surface it so Laravel can
            // flag the row instead of deleting it (anti-delete).
            if (
              protocol &&
              (protocol.type === 0 || protocol.type === 'REVOKE') &&
              protocol.key?.id
            ) {
              try {
                const revokedKey = protocol.key;
                const stripDeviceJid = (jid: string | undefined): string | undefined => {
                  if (!jid) return jid;
                  const d = jidDecode(jid);
                  return d ? jidEncode(d.user, d.server) : jid;
                };
                let revokeChat =
                  stripDeviceJid(revokedKey.remoteJid) ||
                  stripDeviceJid(node.attrs.from!)!;
                const senderPnAttr =
                  node.attrs.sender_pn || node.attrs.participant_pn;
                if (revokeChat.endsWith('@lid')) {
                  if (senderPnAttr) {
                    revokeChat = `${String(senderPnAttr).split('@')[0]}@s.whatsapp.net`;
                  } else {
                    try {
                      const pn = (await this.resolveLidUsers([revokeChat])).get(revokeChat);
                      if (pn) revokeChat = pn;
                    } catch { /* keep the LID form */ }
                  }
                }
                this.emit('message.revoked', {
                  jid: revokeChat,
                  messageId: revokedKey.id,
                  fromMe: !!revokedKey.fromMe,
                  revoker: stripDeviceJid(node.attrs.from),
                });
              } catch (revErr) {
                logger.debug({ err: revErr }, 'revoke handling failed');
              }
              continue;
            }
            // Edits arrive as protocolMessage.editedMessage — let them fall
            // through to normal content handling; all other protocol frames
            // (key shares, ephemeral settings) are dropped.
            if (protocol && !protocol.editedMessage) {
              const pdoResponse = protocol.peerDataOperationRequestResponseMessage;
              if (pdoResponse) {
                const results = (pdoResponse.peerDataOperationResult || []).map((r: any) => ({
                  fullHistoryResponseCode:
                    r?.fullHistorySyncOnDemandRequestResponse?.responseCode,
                  chunkRetry: r?.historySyncChunkRetryResponse
                    ? {
                        syncType: r.historySyncChunkRetryResponse.syncType,
                        chunkOrder: r.historySyncChunkRetryResponse.chunkOrder,
                        responseCode: r.historySyncChunkRetryResponse.responseCode,
                      }
                    : undefined,
                }));
                logger.info(
                  { requestType: pdoResponse.peerDataOperationRequestType, results },
                  'Peer data operation response received',
                );
              } else {
                logger.debug(
                  { protocolType: protocol.type, keys: Object.keys(protocol).filter((k) => protocol[k]) },
                  'Dropping non-chat protocol message',
                );
              }
              continue;
            }

            const { message: unwrapped, fromMe, destinationJid, edited } =
              unwrapMessage(decoded);
            const norm = normalizeMessageContent(unwrapped);
            // senderKeyDistributionMessage and other content-less frames must
            // not surface as empty chat bubbles.
            if (!norm) continue;

            // Strip the :device suffix so one contact maps to one chat —
            // 40141267726363:11@lid and :7@lid are the same conversation.
            const stripDevice = (jid: string | undefined): string | undefined => {
              if (!jid) return jid;
              const d = jidDecode(jid);
              return d ? jidEncode(d.user, d.server) : jid;
            };

            // Resolve LID-addressed 1:1 chats to the PN identity — sender_pn
            // when present, else a usync 'lid' lookup — so one contact stays
            // one conversation instead of splitting across PN/LID twins.
            const senderPnRaw = node.attrs.sender_pn || node.attrs.participant_pn;
            let chatJid = destinationJid || stripDevice(node.attrs.from!)!;
            let lidJid: string | undefined;
            if (chatJid.endsWith('@lid') && !chatJid.includes('@g.us')) {
              if (senderPnRaw) {
                lidJid = chatJid;
                chatJid = `${String(senderPnRaw).split('@')[0]}@s.whatsapp.net`;
              } else {
                try {
                  const pn = (await this.resolveLidUsers([chatJid])).get(chatJid);
                  if (pn) {
                    lidJid = chatJid;
                    chatJid = pn;
                  }
                } catch { /* keep the LID form */ }
              }
            }

            this.emit('message', {
              jid: chatJid,
              sender: stripDevice(from)!,
              senderPn: senderPnRaw,
              senderLid: lidJid,
              notify: node.attrs.notify,
              id: msgId || '',
              timestamp: +(node.attrs.t || 0) || undefined,
              fromMe,
              edited,
              text: norm.type === 'text' ? norm.body : undefined,
              type: norm.type,
              body: norm.body,
              media: norm.media,
              quotedMessageId: norm.quotedMessageId,
              quotedParticipant: norm.quotedParticipant,
              isForwarded: norm.isForwarded,
              raw: decoded,
            });
          } catch (decErr) {
            logger.debug(
              { from: node.attrs.from, id: node.attrs.id, err: decErr },
              'incoming message decrypt failed',
            );
          }
        }
        // Every enc payload failed (missing/stale session, consumed prekey):
        // ask the sender to re-encrypt with fresh keys. Without this the
        // message is silently lost — the sender sees it delivered, we see nothing.
        if (encNodes.length && !decryptedAny) {
          void this.sendRetryRequest(node).catch((err) =>
            logger.warn({ err, id: node.attrs.id }, 'failed to send retry request'),
          );
        }
        break;
      }

      case 'receipt':
        // A peer device that could not decrypt our message asks for a resend
        // with fresh key material attached. Without this the recipient sees
        // "Waiting for this message. Check your phone." forever.
        if (node.attrs.type === 'retry') {
          logger.info(
            {
              retryNode: JSON.stringify(node, (_k, v) =>
                Buffer.isBuffer(v) || v instanceof Uint8Array
                  ? `<b64:${Buffer.from(v).length}b>`
                  : v,
              ),
            },
            'retry receipt received',
          );
          void this.handleRetryReceipt(node).catch((err) =>
            logger.warn({ err }, 'retry receipt handling failed'),
          );
        }
        this.emit('stanza', node);
        break;

      case 'notification':
      case 'call':
      case 'ack':
      case 'presence':
      case 'chatstate':
        // emit for upper layers
        this.emit('stanza', node);
        break;
    }
  }

  // ------------------------------------------------------------------
  // Decrypt-failure retries
  // ------------------------------------------------------------------

  /** Recently sent payloads, kept so a retry receipt can trigger a resend. */
  private readonly sentMessageCache = new Map<
    string,
    {
      destinationJid: string;
      message: Record<string, unknown>;
      stanzaType: string;
      ts: number;
    }
  >();
  private readonly retryAttempts = new Map<string, number>();
  private sentCacheDirty = false;
  private sentCacheTimer: NodeJS.Timeout | null = null;
  private static readonly SENT_CACHE_TTL_MS = 24 * 60 * 60 * 1000;

  /** Retry receipts can arrive long after a restart — persist the resend payloads. */
  private sentCacheFile(): string | null {
    if (!this.opts.sessionId) return null;
    return path.join('data', 'sentcache', `${this.opts.sessionId}.json`);
  }

  private loadSentCache(): void {
    const file = this.sentCacheFile();
    if (!file || !fs.existsSync(file)) return;
    try {
      const raw = JSON.parse(
        fs.readFileSync(file, 'utf8'),
        BufferJSON.reviver,
      ) as Record<string, { destinationJid: string; message: Record<string, unknown>; stanzaType: string; ts: number }>;
      const cutoff = Date.now() - WaClient.SENT_CACHE_TTL_MS;
      for (const [id, entry] of Object.entries(raw)) {
        if (entry?.ts && entry.ts > cutoff && entry.message) {
          this.sentMessageCache.set(id, entry);
        }
      }
    } catch {
      // corrupt cache is not fatal — retries for those ids just won't resolve
    }
  }

  private saveSentCache(): void {
    const file = this.sentCacheFile();
    if (!file) return;
    try {
      fs.mkdirSync(path.dirname(file), { recursive: true });
      const cutoff = Date.now() - WaClient.SENT_CACHE_TTL_MS;
      const out: Record<string, unknown> = {};
      for (const [id, entry] of this.sentMessageCache) {
        if (entry.ts > cutoff) out[id] = entry;
      }
      const tmp = `${file}.tmp`;
      fs.writeFileSync(tmp, JSON.stringify(out, BufferJSON.replacer), { mode: 0o600 });
      fs.renameSync(tmp, file);
    } catch {
      // best-effort persistence
    }
  }

  private scheduleSentCacheSave(): void {
    if (this.sentCacheTimer) return;
    this.sentCacheTimer = setTimeout(() => {
      this.sentCacheTimer = null;
      if (this.sentCacheDirty) {
        this.sentCacheDirty = false;
        this.saveSentCache();
      }
    }, 3000);
  }

  private cacheSentMessage(
    msgId: string,
    destinationJid: string,
    message: Record<string, unknown>,
    stanzaType: string,
  ): void {
    this.sentMessageCache.set(msgId, {
      destinationJid,
      message,
      stanzaType,
      ts: Date.now(),
    });
    // Keep the cache bounded — entries are only useful while WhatsApp may
    // still issue retry receipts for them.
    if (this.sentMessageCache.size > 500) {
      for (const k of this.sentMessageCache.keys()) {
        if (this.sentMessageCache.size <= 400) break;
        this.sentMessageCache.delete(k);
      }
    }
    this.sentCacheDirty = true;
    this.scheduleSentCacheSave();
  }

  /**
   * A recipient (often one of our own devices) failed to decrypt a message we
   * sent. Its retry receipt carries the device's current key bundle — rebuild
   * the signal session from it and re-encrypt the cached payload.
   */
  private async handleRetryReceipt(node: BinaryNode): Promise<void> {
    const msgId = node.attrs.id;
    if (!msgId) return;

    // The requesting device shows up under `participant` (groups/peer), and
    // own-device retries carry it under `recipient` — fall back to `from`.
    const rawRequester = (node.attrs.participant ||
      node.attrs.recipient ||
      node.attrs.from ||
      '') as string;
    const decoded = jidDecode(rawRequester);
    if (!decoded) return;
    const requesterJid = jidEncode(decoded.user, decoded.server, decoded.device ?? 0);

    const attemptKey = `${msgId}|${requesterJid}`;
    const attempts = (this.retryAttempts.get(attemptKey) || 0) + 1;
    this.retryAttempts.set(attemptKey, attempts);
    if (attempts > 5) {
      logger.warn({ msgId, requesterJid }, 'retry receipt ignored — too many attempts');
      return;
    }

    const cached = this.sentMessageCache.get(msgId);
    if (!cached) {
      // Cached payload was evicted or lost to a restart — the recipient will
      // keep showing "Waiting for this message" until the message is resent.
      logger.warn({ msgId, requesterJid }, 'retry receipt for unknown/expired message');
      return;
    }

    // The <keys> node carries the requester's fresh bundle in the same shape
    // as an encrypt-IQ <user> entry. <registration> may sit on the receipt.
    const keysNode = getBinaryNodeChild(node, 'keys') || node;
    const extractKey = (n: BinaryNode | undefined) => {
      if (!n) return undefined;
      const idNode = getBinaryNodeChild(n, 'id');
      const valueNode = getBinaryNodeChild(n, 'value');
      if (!idNode?.content || !valueNode?.content) return undefined;
      return {
        keyId: (idNode.content as Buffer).readUIntBE(0, 3),
        publicKey: prefixKeyInPublicKey(valueNode.content as Buffer),
        signature: getBinaryNodeChild(n, 'signature')?.content as Buffer | undefined,
      };
    };

    const registration =
      (getBinaryNodeChild(node, 'registration')?.content as Buffer | undefined) ||
      (getBinaryNodeChild(keysNode, 'registration')?.content as Buffer | undefined);
    const identityContent = getBinaryNodeChild(keysNode, 'identity')?.content as
      | Buffer
      | undefined;
    const skeyParsed = extractKey(getBinaryNodeChild(keysNode, 'skey'));
    const keyParsed =
      extractKey(getBinaryNodeChild(keysNode, 'key')) ||
      extractKey(getBinaryNodeChild(keysNode, 'prekey'));

    if (registration && identityContent && skeyParsed?.signature) {
      const bundle: DeviceBundle = {
        registrationId: registration.readUInt32BE(0),
        identityKey: prefixKeyInPublicKey(identityContent),
        signedPreKey: {
          keyId: skeyParsed.keyId,
          publicKey: skeyParsed.publicKey,
          signature: skeyParsed.signature,
        },
        preKey: keyParsed ? { keyId: keyParsed.keyId, publicKey: keyParsed.publicKey } : undefined,
      };
      // Replace whatever stale session we had — that staleness is why the
      // device could not decrypt in the first place.
      await new SessionBuilder(this.signalStore, requesterJid).initOutgoing(bundle);
    } else {
      // No key material attached — the device is telling us our session is
      // stale without handing us the fix. Pull a fresh bundle from the
      // server and overwrite the stored session (force=true), otherwise
      // we'd resend with the same broken keys forever.
      const result = await this.query({
        tag: 'iq',
        attrs: { xmlns: 'encrypt', type: 'get', to: S_WHATSAPP_NET },
        content: [
          {
            tag: 'key',
            attrs: {},
            content: [{ tag: 'user', attrs: { jid: requesterJid } }],
          },
        ],
      });
      await this.injectE2ESessions(result, true);
      if (!this.signalStore.hasSession(requesterJid)) {
        logger.warn({ msgId, requesterJid }, 'retry receipt: session fetch yielded no usable bundle');
        return;
      }
    }

    const meUser = jidDecode(this.creds.me?.id || '')?.user;
    const meLidUser = jidDecode(this.creds.me?.lid || '')?.user;
    const isOwnDevice = decoded.user === meUser || decoded.user === meLidUser;
    const payload = isOwnDevice
      ? { deviceSentMessage: { destinationJid: cached.destinationJid, message: cached.message } }
      : cached.message;

    const bytes = await this.encodeWAMessage(payload);
    const cipher = new SessionCipher(this.signalStore, requesterJid);
    const { type, body } = await cipher.encrypt(bytes);

    const stanza: BinaryNode = {
      tag: 'message',
      attrs: {
        id: msgId,
        to: cached.destinationJid,
        type: cached.stanzaType,
      },
      content: [
        {
          tag: 'participants',
          attrs: {},
          content: [
            {
              tag: 'to',
              attrs: { jid: requesterJid },
              content: [{ tag: 'enc', attrs: { v: '2', type }, content: body }],
            },
          ],
        },
      ],
    };

    if (type === 'pkmsg' && this.creds.account) {
      const account = { ...(this.creds.account as Record<string, unknown>) };
      if (!account.accountSignatureKey || !(account.accountSignatureKey as Buffer).length) {
        account.accountSignatureKey = null;
      }
      (stanza.content as BinaryNode[]).push({
        tag: 'device-identity',
        attrs: {},
        content: await WaProto.encodeADVSignedDeviceIdentity(account),
      });
    }

    await this.sendNode(stanza);
    logger.info({ msgId, requesterJid, attempts }, 're-encrypted message after retry receipt');
  }

  /** How many retry requests we sent per message+device (loop guard). */
  private readonly retryRequestsSent = new Map<string, number>();

  /**
   * We failed to decrypt an incoming message — ask the sender's device to
   * re-encrypt. The receipt carries our registration id and (periodically)
   * our current key bundle so the peer can rebuild a session from scratch.
   */
  /**
   * Store sender keys that arrive piggybacked on pairwise messages. A contact
   * who posts to a group or their status first sends each of our devices a
   * SenderKeyDistributionMessage inside a msg/pkmsg payload; every subsequent
   * group/status message then arrives as a single `skmsg` ciphertext. The
   * distribution message may sit on the top-level Message or inside a
   * deviceSentMessage wrapper.
   */
  private async processSenderKeyDistribution(decoded: any, authorJid: string): Promise<void> {
    const candidates = [
      decoded,
      decoded?.deviceSentMessage?.message,
      decoded?.protocolMessage?.editedMessage,
    ];
    for (const m of candidates) {
      for (const field of ['senderKeyDistributionMessage', 'fastRatchetKeySenderKeyDistributionMessage']) {
        const skdm = m?.[field];
        const groupJid = skdm?.groupId;
        const axolotl = skdm?.axolotlSenderKeyDistributionMessage;
        if (!groupJid || !axolotl) continue;
        const buf = Buffer.isBuffer(axolotl) ? axolotl : Buffer.from(axolotl);
        try {
          await this.senderKeyCipher.processDistribution(String(groupJid), authorJid, buf);
          logger.info({ groupJid, authorJid }, 'stored sender key distribution');
        } catch (e) {
          logger.warn(
            {
              err: e, groupJid, authorJid, len: buf.length,
              b64: buf.toString('base64'),
              field, keys: Object.keys(m || {}),
            },
            'sender-key distribution decode failed',
          );
        }
      }
    }
  }

  private async sendRetryRequest(node: BinaryNode, forceIncludeKeys = false): Promise<void> {
    const msgId = node.attrs.id;
    const from = node.attrs.from;
    if (!msgId || !from || !this.creds.me) return;

    const rawSender = (node.attrs.participant as string) || from;
    const sender = jidDecode(rawSender);
    if (!sender) return;

    const key = `${msgId}|${rawSender}`;
    const count = (this.retryRequestsSent.get(key) || 0) + 1;
    this.retryRequestsSent.set(key, count);
    if (count > 5 || this.retryRequestsSent.size > 500) {
      if (this.retryRequestsSent.size > 500) this.retryRequestsSent.clear();
      if (count > 5) return;
    }

    const includeKeys = forceIncludeKeys || count === 1 || count % 5 === 0;

    const content: BinaryNode[] = [
      {
        tag: 'retry',
        attrs: {
          count: String(count),
          id: msgId,
          t: node.attrs.t || String(Math.floor(Date.now() / 1000)),
          v: '1',
        },
      },
      {
        tag: 'registration',
        attrs: {},
        content: encodeBigEndian(this.creds.registrationId),
      },
    ];

    if (includeKeys) {
      // Upload fresh one-time prekey so the peer's new pkmsg session resolves —
      // store it locally as well or we still won't be able to decrypt the resend.
      const preKeyId = this.creds.nextPreKeyId++;
      const preKey = generateWaKeyPair();
      this.creds.preKeys = this.creds.preKeys || {};
      this.creds.preKeys[preKeyId.toString()] = preKey;
      this.saveCreds();

      const keysChildren: BinaryNode[] = [
        { tag: 'type', attrs: {}, content: Buffer.from([5]) },
        { tag: 'identity', attrs: {}, content: this.creds.signedIdentityKey.public },
        xmppPreKey(preKey, preKeyId),
        xmppSignedPreKey(this.creds.signedPreKey),
      ];
      if (this.creds.account) {
        const account = { ...(this.creds.account as Record<string, unknown>) };
        if (!account.accountSignatureKey || !(account.accountSignatureKey as Buffer).length) {
          account.accountSignatureKey = null;
        }
        keysChildren.push({
          tag: 'device-identity',
          attrs: {},
          content: await WaProto.encodeADVSignedDeviceIdentity(account),
        });
      }
      content.push({ tag: 'keys', attrs: {}, content: keysChildren });
    }

    const receipt: BinaryNode = {
      tag: 'receipt',
      attrs: { to: from, id: msgId, type: 'retry' },
      content,
    };
    if (node.attrs.participant) receipt.attrs.participant = node.attrs.participant;
    if (node.attrs.recipient) receipt.attrs.recipient = node.attrs.recipient;

    await this.sendNode(receipt);
    logger.info({ msgId, from, count }, 'sent decryption retry request');
  }

  /** Dedupes history-sync notifications so repeated deliveries skip the download. */
  private readonly handledHistorySync = new Set<string>();

  private async handleHistorySyncNotification(notif: any): Promise<void> {
    const dedupeKey = `${notif?.syncType}:${notif?.chunkOrder}:${notif?.originalMessageId || notif?.directPath || 'inline'}`;
    if (this.handledHistorySync.has(dedupeKey)) return;
    if (this.handledHistorySync.size > 200) this.handledHistorySync.clear();
    this.handledHistorySync.add(dedupeKey);

    try {
      logger.info(
        {
          syncType: notif.syncType,
          chunkOrder: notif.chunkOrder,
          progress: notif.progress,
          hasInlinePayload: !!notif.initialHistBootstrapInlinePayload,
          directPath: notif.directPath,
          fileLength: notif.fileLength != null ? Number(notif.fileLength) : undefined,
        },
        'History sync notification received',
      );
      const parsed = await processHistorySyncNotification(notif);
      if (!parsed) return;
      logger.info(
        {
          syncType: parsed.syncType,
          chunkOrder: parsed.chunkOrder,
          progress: parsed.progress,
          conversations: parsed.conversations.length,
          pushnames: parsed.pushnames.length,
        },
        'History sync chunk processed',
      );
      this.emit('history.sync', parsed);
    } catch (err) {
      logger.warn({ err }, 'History sync notification processing failed');
    }
  }

  private async ackMessageNode(node: BinaryNode): Promise<void> {
    const msgId = node.attrs.id;
    if (!msgId || !node.attrs.from) return;
    const ack: BinaryNode = {
      tag: 'ack',
      attrs: { id: msgId, to: node.attrs.from, class: 'message' },
    };
    if (node.attrs.participant) ack.attrs.participant = node.attrs.participant;
    if (node.attrs.recipient) ack.attrs.recipient = node.attrs.recipient;
    await this.sendNode(ack).catch(() => undefined);
  }

  private async onPairDeviceIq(stanza: BinaryNode): Promise<void> {
    // acknowledge the iq
    await this.sendNode({
      tag: 'iq',
      attrs: { to: S_WHATSAPP_NET, type: 'result', id: stanza.attrs.id! },
    });

    const pairDeviceNode = getBinaryNodeChild(stanza, 'pair-device');
    const refNodes = getBinaryNodeChildren(pairDeviceNode, 'ref');

    const noiseKeyB64 = Buffer.from(this.creds.noiseKey.public).toString('base64');
    const identityKeyB64 = Buffer.from(this.creds.signedIdentityKey.public).toString('base64');
    const advB64 = this.creds.advSecretKey;

    let qrMs = QR_TTL_MS;
    const genPairQR = () => {
      if (!this.isOpen) return;
      const refNode = refNodes.shift();
      if (!refNode) {
        void this.end(new Error('QR refs attempts ended'));
        return;
      }
      const ref = (refNode.content as Buffer).toString('utf-8');
      const qr = buildPairingQRData(ref, noiseKeyB64, identityKeyB64, advB64);
      this.state = 'qr';
      this.emit('qr', qr);
      this.qrTimer = setTimeout(genPairQR, qrMs);
      qrMs = QR_TTL_SUBSEQUENT_MS;
    };

    genPairQR();
  }

  private async onPairSuccess(stanza: BinaryNode): Promise<void> {
    try {
      const { reply, credsUpdate } = await configureSuccessfulPairing(stanza, this.creds);
      Object.assign(this.creds, credsUpdate);
      this.creds.registered = true;
      this.saveCreds();

      this.state = 'paired';
      await this.sendNode(reply);

      // The server now restarts the connection; on reconnect we log in
      // with the registered identity.
      this.expectsRestartAfterPairing = true;
    } catch (error) {
      await this.end(error instanceof Error ? error : new Error(String(error)));
    }
  }

  private async onLoginSuccess(node: BinaryNode): Promise<void> {
    if (this.qrTimer) {
      clearTimeout(this.qrTimer);
      this.qrTimer = null;
    }

    if (node.attrs.lid && this.creds.me) {
      this.creds.me.lid = node.attrs.lid;
      this.saveCreds();
    }

    this.state = 'open';
    this.emit('open', { id: this.creds.me!.id, lid: this.creds.me?.lid });

    // Post-login housekeeping (best-effort; must not block the open event)
    void (async () => {
      try {
        await this.uploadPreKeysToServerIfRequired();
        await this.sendPassiveIq('active');
        // Mark the device online — without this the server treats us as
        // offline and queues incoming messages as reconnect backlog
        // instead of pushing them live.
        await this.sendPresenceUpdate('available');
        await this.digestKeyBundle().catch(() => undefined);
        await this.sendUnifiedSession().catch(() => undefined);
        await this.resyncAppState().catch(() => undefined);
      } catch {
        // non-fatal
      }
    })();
  }

  // ------------------------------------------------------------------
  // I/O primitives
  // ------------------------------------------------------------------

  private async sendRawMessage(data: Uint8Array | Buffer): Promise<void> {
    if (!this.transport?.isConnected) {
      throw new Error('Connection Closed');
    }
    await this.transport.send(this.noise!.encodeFrame(Buffer.from(data)));
  }

  public async sendNode(node: BinaryNode): Promise<void> {
    if (DEBUG_STANZAS) {
      logger.info({ me: this.creds.me?.id, out: stanzaSummary(node) }, 'stanza >>');
    }
    const buff = this.encoder.encode(node);
    await this.sendRawMessage(buff);
  }

  public async query(node: BinaryNode, timeoutMs = 60_000): Promise<BinaryNode> {
    if (!node.attrs.id) {
      node.attrs.id = this.generateMessageTag();
    }
    const msgId = node.attrs.id;

    return new Promise<BinaryNode>((resolve, reject) => {
      const timer = setTimeout(() => {
        this.pendingQueries.delete(msgId);
        reject(new Error(`Query ${msgId} timed out`));
      }, timeoutMs);

      this.pendingQueries.set(msgId, {
        resolve: (n) => {
          // surface stream errors inside iq results and message acks
          const errAttr = n.attrs.error || n.attrs.code;
          const errChild = Array.isArray(n.content)
            ? n.content.find((c) => c.tag === 'error')
            : undefined;
          if (n.attrs.type === 'error' || errChild || (errAttr && n.tag === 'ack')) {
            reject(
              Object.assign(
                new Error(`Stanza error (${errAttr || errChild?.attrs.code || 'unknown'})`),
                { statusCode: +(errAttr || errChild?.attrs.code || 500) },
              ),
            );
            return;
          }
          resolve(n);
        },
        reject,
        timer,
      });

      this.sendNode(node).catch((err) => {
        this.pendingQueries.delete(msgId);
        clearTimeout(timer);
        reject(err);
      });
    });
  }

  // ------------------------------------------------------------------
  // Pairing code (phone-number linking)
  // ------------------------------------------------------------------

  public async requestPairingCode(phoneNumber: string, customPairingCode?: string): Promise<string> {
    const pairingCode = customPairingCode ?? bytesToCrockford(crypto.randomBytes(5));
    if (customPairingCode && customPairingCode.length !== 8) {
      throw new Error('Custom pairing code must be exactly 8 chars');
    }

    this.creds.pairingCode = pairingCode;
    this.creds.me = {
      id: jidEncode(phoneNumber, 's.whatsapp.net'),
      name: '~',
    };
    this.saveCreds();

    const generatePairingKey = async () => {
      const salt = crypto.randomBytes(32);
      const randomIv = crypto.randomBytes(16);
      const key = await derivePairingCodeKey(pairingCode, salt);
      const ciphered = aesEncryptCTR(this.creds.pairingEphemeralKeyPair.public, key, randomIv);
      return Buffer.concat([salt, randomIv, ciphered]);
    };

    await this.sendNode({
      tag: 'iq',
      attrs: {
        to: S_WHATSAPP_NET,
        type: 'set',
        id: this.generateMessageTag(),
        xmlns: 'md',
      },
      content: [
        {
          tag: 'link_code_companion_reg',
          attrs: {
            jid: this.creds.me.id,
            stage: 'companion_hello',
            should_show_push_notification: 'true',
          },
          content: [
            {
              tag: 'link_code_pairing_wrapped_companion_ephemeral_pub',
              attrs: {},
              content: await generatePairingKey(),
            },
            {
              tag: 'companion_server_auth_key_pub',
              attrs: {},
              content: this.creds.noiseKey.public,
            },
            {
              tag: 'companion_platform_id',
              attrs: {},
              content: getCompanionPlatformId(),
            },
            {
              tag: 'companion_platform_display',
              attrs: {},
              content: getCompanionPlatformDisplay(),
            },
            {
              tag: 'link_code_pairing_nonce',
              attrs: {},
              content: '0',
            },
          ],
        },
      ],
    });

    const display = pairingCode.match(/.{1,4}/g)?.join('-') || pairingCode;
    this.emit('pairing-code', display);
    return display;
  }

  // ------------------------------------------------------------------
  // Post-login operations
  // ------------------------------------------------------------------

  private sendPassiveIq(tag: 'passive' | 'active'): Promise<BinaryNode> {
    return this.query({
      tag: 'iq',
      attrs: { to: S_WHATSAPP_NET, xmlns: 'passive', type: 'set' },
      content: [{ tag, attrs: {} }],
    });
  }

  /**
   * Issue the app-state collection handshake WhatsApp Web performs on
   * connect. The server only marks a companion 'synced' — and starts
   * pushing live messages instead of queueing them for reconnect — once
   * the collections have been requested. Patch payloads are encrypted
   * app-state data we do not persist; requesting them is what matters.
   */
  private resyncAppState(): Promise<BinaryNode> {
    const collections = [
      'critical_block',
      'critical_unblock_low',
      'regular_high',
      'regular_low',
      'regular',
    ];
    return this.query({
      tag: 'iq',
      attrs: { to: S_WHATSAPP_NET, type: 'set', xmlns: 'w:sync:app:state' },
      content: [
        {
          tag: 'sync',
          attrs: {},
          content: collections.map((name) => ({
            tag: 'collection',
            attrs: { name, version: '0', return_snapshot: 'false' },
          })),
        },
      ],
    });
  }

  private async getAvailablePreKeysOnServer(): Promise<number> {
    const result = await this.query({
      tag: 'iq',
      attrs: {
        id: this.generateMessageTag(),
        xmlns: 'encrypt',
        type: 'get',
        to: S_WHATSAPP_NET,
      },
      content: [{ tag: 'count', attrs: {} }],
    });
    const countChild = getBinaryNodeChild(result, 'count');
    return +(countChild?.attrs.value || 0);
  }

  private async uploadPreKeys(count = 30): Promise<void> {
    const startId = this.creds.nextPreKeyId;
    const preKeys: BinaryNode[] = [];
    this.creds.preKeys = this.creds.preKeys || {};
    for (let i = startId; i < startId + count; i++) {
      const kp = generateWaKeyPair();
      this.creds.preKeys[i.toString()] = kp;
      preKeys.push(xmppPreKey({ public: kp.public }, i));
    }

    const node: BinaryNode = {
      tag: 'iq',
      attrs: {
        id: this.generateMessageTag(),
        xmlns: 'encrypt',
        type: 'set',
        to: S_WHATSAPP_NET,
      },
      content: [
        { tag: 'registration', attrs: {}, content: this.encodeRegId() },
        { tag: 'type', attrs: {}, content: Buffer.from([5]) },
        { tag: 'identity', attrs: {}, content: this.creds.signedIdentityKey.public },
        {
          tag: 'list',
          attrs: {},
          content: preKeys,
        },
        xmppSignedPreKey(this.creds.signedPreKey),
      ],
    };

    await this.query(node);
    this.creds.nextPreKeyId = startId + count;
    this.creds.firstUnuploadedPreKeyId = this.creds.nextPreKeyId;
    this.saveCreds();
  }

  private encodeRegId(): Buffer {
    const a = Buffer.alloc(4);
    let e = this.creds.registrationId;
    for (let i = 3; i >= 0; i--) {
      a[i] = 255 & e;
      e >>>= 8;
    }
    return a;
  }

  private async uploadPreKeysToServerIfRequired(): Promise<void> {
    try {
      const preKeyCount = await this.getAvailablePreKeysOnServer();
      if (preKeyCount <= 5) {
        await this.uploadPreKeys(preKeyCount === 0 ? 812 : 30);
      }
    } catch {
      // non-fatal
    }
  }

  private async digestKeyBundle(): Promise<void> {
    const res = await this.query({
      tag: 'iq',
      attrs: { to: S_WHATSAPP_NET, type: 'get', xmlns: 'encrypt' },
      content: [{ tag: 'digest', attrs: {} }],
    });
    const digestNode = getBinaryNodeChild(res, 'digest');
    if (!digestNode) {
      throw new Error('encrypt/get digest returned no digest node');
    }
  }

  private serverTimeOffsetMs = 0;
  private async sendUnifiedSession(): Promise<void> {
    const weekMs = 7 * 24 * 60 * 60 * 1000;
    const now = Date.now() + this.serverTimeOffsetMs;
    const id = ((now + 3 * 24 * 60 * 60 * 1000) % weekMs).toString();
    await this.sendNode({
      tag: 'ib',
      attrs: {},
      content: [{ tag: 'unified_session', attrs: { id } }],
    });
  }

  // ------------------------------------------------------------------
  // Messaging — real Signal-encrypted sends
  // ------------------------------------------------------------------

  private readonly lidToPnCache = new Map<string, string | null>();

  /**
   * Resolve LID JIDs (4014...@lid) to their phone-number JIDs via a usync
   * 'lid' protocol query. Results are cached — unknown/unresolvable LIDs
   * cache as null so repeated lookups stay cheap.
   */
  public async resolveLidUsers(lidJids: string[]): Promise<Map<string, string>> {
    const resolved = new Map<string, string>();
    const toFetch: string[] = [];
    for (const jid of lidJids) {
      // Skip malformed local-format jids (e.g. 01024520482@s.whatsapp.net) —
      // the server silently drops queries for them, wasting the full timeout.
      const user = jid.split('@')[0]?.split(':')[0] ?? '';
      if (!/^\d+$/.test(user) || user.startsWith('0')) continue;
      const cached = this.lidToPnCache.get(jid);
      if (cached) {
        resolved.set(jid, cached);
      } else if (cached === undefined) {
        toFetch.push(jid);
      }
    }
    if (!toFetch.length) return resolved;

    try {
      const result = await this.query({
        tag: 'iq',
        attrs: { to: S_WHATSAPP_NET, type: 'get', xmlns: 'usync' },
        content: [
          {
            tag: 'usync',
            attrs: {
              context: 'interactive',
              mode: 'query',
              sid: this.generateMessageTag(),
              last: 'true',
              index: '0',
            },
            content: [
              {
                tag: 'query',
                attrs: {},
                content: [
                  { tag: 'devices', attrs: { version: '2' } },
                  { tag: 'lid', attrs: {} },
                ],
              },
              {
                tag: 'list',
                attrs: {},
                content: toFetch.map((jid) => ({ tag: 'user', attrs: { jid } })),
              },
            ],
          },
        ],
      });

      if (DEBUG_STANZAS) {
        logger.info(
          {
            lidResolveResult: JSON.stringify(result, (_k, v) =>
              Buffer.isBuffer(v) || v instanceof Uint8Array
                ? `<b64:${Buffer.from(v).toString('base64').slice(0, 24)}>`
                : v,
            ),
          },
          'lid usync result',
        );
      }

      const usync = getBinaryNodeChild(result, 'usync');
      const list = getBinaryNodeChild(usync, 'list');
      const users = getBinaryNodeChildren(list, 'user');
      const byLid = new Map<string, string>();
      const byPn = new Map<string, string>();
      for (const userNode of users) {
        const lidNode = getBinaryNodeChild(userNode, 'lid');
        const lidVal = lidNode?.attrs?.val || lidNode?.attrs?.jid;
        const userJid = userNode.attrs.jid;
        if (lidVal && userJid && !userJid.endsWith('@lid')) {
          const dLid = jidDecode(lidVal);
          const dPn = jidDecode(userJid);
          if (dLid && dPn) {
            const lidJid = jidEncode(dLid.user, dLid.server);
            const pnJid = jidEncode(dPn.user, dPn.server);
            byLid.set(lidJid, pnJid);
            byPn.set(pnJid, lidJid);
          }
        }
      }
      for (const jid of toFetch) {
        const counterpart = (jid.endsWith('@lid') ? byLid : byPn).get(jid) || null;
        this.lidToPnCache.set(jid, counterpart);
        if (counterpart) {
          resolved.set(jid, counterpart);
          this.lidToPnCache.set(counterpart, jid);
        }
      }
    } catch (e) {
      logger.warn({ err: e, lids: toFetch }, 'LID resolution query failed');
    }
    return resolved;
  }

  /**
   * Fetch WhatsApp @usernames for the given JIDs via a usync 'username'
   * query — the protocol WhatsApp Web uses to render handles in place of
   * phone numbers. Contacts without a username simply return no node.
   */
  public async fetchUsernames(jids: string[]): Promise<Map<string, string>> {
    const resolved = new Map<string, string>();
    const toFetch = jids
      .map((j) => String(j || ''))
      .filter((j) => {
        if (!j.endsWith('@s.whatsapp.net') && !j.endsWith('@lid')) return false;
        const user = j.split('@')[0]?.split(':')[0] ?? '';
        return /^\d+$/.test(user) && !user.startsWith('0');
      })
      .slice(0, 100);
    if (!toFetch.length) return resolved;

    try {
      const result = await this.query(
        {
          tag: 'iq',
          attrs: { to: S_WHATSAPP_NET, type: 'get', xmlns: 'usync' },
          content: [
            {
              tag: 'usync',
              attrs: {
                context: 'interactive',
                mode: 'query',
                sid: this.generateMessageTag(),
                last: 'true',
                index: '0',
              },
              content: [
                {
                  tag: 'query',
                  attrs: {},
                  content: [{ tag: 'username', attrs: {} }],
                },
                {
                  tag: 'list',
                  attrs: {},
                  content: toFetch.map((jid) => ({ tag: 'user', attrs: { jid } })),
                },
              ],
            },
          ],
        },
        25_000,
      );

      if (DEBUG_STANZAS) {
        logger.info(
          {
            usernamesResult: JSON.stringify(result, (_k, v) =>
              Buffer.isBuffer(v) || v instanceof Uint8Array
                ? `<b64:${Buffer.from(v).toString('base64').slice(0, 24)}>`
                : v,
            ),
          },
          'usernames usync result',
        );
      }

      const usync = getBinaryNodeChild(result, 'usync');
      const list = getBinaryNodeChild(usync, 'list');
      for (const userNode of getBinaryNodeChildren(list, 'user')) {
        const un = getBinaryNodeChild(userNode, 'username');
        // The handle rides as the node's binary payload — attrs only carry
        // a state flag, so decode Buffer/Uint8Array/string contents too.
        let val: string | undefined =
          (un?.attrs?.val as string | undefined) ||
          (un?.attrs?.username as string | undefined);
        const c = un?.content;
        if (!val && c) {
          if (typeof c === 'string') val = c;
          else if (Buffer.isBuffer(c)) val = c.toString();
          else if (c instanceof Uint8Array) val = Buffer.from(c).toString();
        }
        if (userNode.attrs?.jid && val) {
          resolved.set(String(userNode.attrs.jid), val);
        }
      }
    } catch (e) {
      logger.warn({ err: e, jids: toFetch }, 'Username usync query failed');
    }
    return resolved;
  }

  /**
   * Fetch profile picture URLs for the given JIDs. WhatsApp serves these
   * through a per-user `w:profile:picture` IQ (same call WhatsApp Web makes
   * when rendering the chat list), so we fan out bounded queries.
   */
  public async resolveProfilePics(jids: string[]): Promise<Map<string, string>> {
    const out = new Map<string, string>();
    const fetchList = jids
      .filter((j) => {
        if (!j || j.endsWith('@broadcast')) return false;
        const user = j.split('@')[0] ?? '';
        // WhatsApp JIDs are full international format — a leading 0 marks a
        // malformed local-format number that the server will never answer.
        return /^\d+$/.test(user) && !user.startsWith('0');
      })
      .slice(0, 60);

    const fetchOne = async (jid: string) => {
      try {
        const result = await this.query({
          tag: 'iq',
          attrs: {
            to: S_WHATSAPP_NET,
            target: jid,
            type: 'get',
            xmlns: 'w:profile:picture',
          },
          content: [
            {
              tag: 'picture',
              attrs: { query: 'url', type: 'image' },
            },
          ],
        }, 10_000);
        const pic = getBinaryNodeChild(result, 'picture');
        const url = pic?.attrs?.url;
        if (url) out.set(jid, String(url));
      } catch {
        // 401/404 = no picture or privacy-blocked — cache nothing.
      }
    };

    // Small batches keep the socket responsive for message traffic.
    for (let i = 0; i < fetchList.length; i += 10) {
      await Promise.all(fetchList.slice(i, i + 10).map(fetchOne));
    }
    return out;
  }

  /** Fetch the device list for the given users via a usync query. */
  /** Short-lived device-list cache — a usync roundtrip per send adds ~300-800ms. */
  private deviceListCache = new Map<string, { devices: string[]; expires: number }>();

  private async getDeviceList(jids: string[]): Promise<string[]> {
    const key = jids.slice().sort().join(',');
    const cached = this.deviceListCache.get(key);
    if (cached && cached.expires > Date.now()) return cached.devices;

    const result = await this.query({
      tag: 'iq',
      attrs: {
        to: S_WHATSAPP_NET,
        type: 'get',
        xmlns: 'usync',
      },
      content: [
        {
          tag: 'usync',
          attrs: {
            context: 'interactive',
            mode: 'query',
            sid: this.generateMessageTag(),
            last: 'true',
            index: '0',
          },
          content: [
            {
              tag: 'query',
              attrs: {},
              content: [{ tag: 'devices', attrs: { version: '2' } }],
            },
            {
              tag: 'list',
              attrs: {},
              content: jids.map((jid) => ({ tag: 'user', attrs: { jid } })),
            },
          ],
        },
      ],
    });

    if (DEBUG_STANZAS) {
      logger.info({ usyncResult: JSON.stringify(result, (_k, v) => Buffer.isBuffer(v) || v instanceof Uint8Array ? `<b64:${Buffer.from(v).toString('base64').slice(0, 24)}>` : v) }, 'usync result');
    }

    const usync = getBinaryNodeChild(result, 'usync');
    const list = getBinaryNodeChild(usync, 'list');
    const users = getBinaryNodeChildren(list, 'user');
    const deviceJids: string[] = [];

    const meId = this.creds.me?.id;
    const meLid = this.creds.me?.lid;
    const meUser = meId ? jidDecode(meId)?.user : undefined;
    const meDevice = meId ? jidDecode(meId)?.device : undefined;
    const meLidUser = meLid ? jidDecode(meLid)?.user : undefined;

    for (const userNode of users) {
      const id = userNode.attrs.jid;
      const decoded = jidDecode(id);
      if (!decoded) continue;
      const devices = getBinaryNodeChild(userNode, 'devices');
      const deviceList = getBinaryNodeChild(devices, 'device-list');
      const deviceNodes = getBinaryNodeChildren(deviceList, 'device');
      for (const d of deviceNodes) {
        const device = +(d.attrs.id || 0);
        const isMe = decoded.user === meUser || decoded.user === meLidUser;
        if (isMe && device === meDevice) continue; // skip our own exact device
        if (device !== 0 && d.attrs['key-index'] === undefined) continue;
        const isHosted = d.attrs.is_hosted === 'true';
        const server = isHosted
          ? decoded.server === 'lid'
            ? 'hosted.lid'
            : 'hosted'
          : (decoded.server as 's.whatsapp.net');
        deviceJids.push(jidEncode(decoded.user, server, device));
      }
    }

    this.deviceListCache.set(key, { devices: deviceJids, expires: Date.now() + 5 * 60_000 });
    if (this.deviceListCache.size > 500) this.deviceListCache.clear();
    return deviceJids;
  }

  /** Parse an encrypt IQ result and build signal sessions for each user. */
  private async injectE2ESessions(node: BinaryNode, force = false): Promise<void> {
    if (DEBUG_STANZAS) {
      logger.info({ encryptResult: JSON.stringify(node, (_k, v) => Buffer.isBuffer(v) || v instanceof Uint8Array ? `<b64:${Buffer.from(v).toString('base64').slice(0, 24)}>` : v) }, 'encrypt result');
    }
    const list = getBinaryNodeChild(node, 'list');
    const users = getBinaryNodeChildren(list, 'user');

    for (const user of users) {
      const jid = user.attrs.jid!;
      const errorNode = getBinaryNodeChild(user, 'error');
      if (errorNode) continue;

      const registration = getBinaryNodeChild(user, 'registration');
      const identity = getBinaryNodeChild(user, 'identity');
      const skey = getBinaryNodeChild(user, 'skey');
      const key = getBinaryNodeChild(user, 'key');
      if (!registration?.content || !identity?.content || !skey) continue;

      const extract = (n: BinaryNode | undefined) => {
        if (!n) return undefined;
        const idNode = getBinaryNodeChild(n, 'id');
        const valueNode = getBinaryNodeChild(n, 'value');
        if (!idNode?.content || !valueNode?.content) return undefined;
        return {
          keyId: (idNode.content as Buffer).readUIntBE(0, 3),
          publicKey: prefixKeyInPublicKey(valueNode.content as Buffer),
          signature: getBinaryNodeChild(n, 'signature')?.content as Buffer | undefined,
        };
      };

      const skeyParsed = extract(skey);
      const keyParsed = extract(key);
      if (!skeyParsed?.signature) continue;

      const bundle: DeviceBundle = {
        registrationId: (registration.content as Buffer).readUInt32BE(0),
        identityKey: prefixKeyInPublicKey(identity.content as Buffer),
        signedPreKey: {
          keyId: skeyParsed.keyId,
          publicKey: skeyParsed.publicKey,
          signature: skeyParsed.signature,
        },
        preKey: keyParsed ? { keyId: keyParsed.keyId, publicKey: keyParsed.publicKey } : undefined,
      };

      if (force || !this.signalStore.hasSession(jid)) {
        await new SessionBuilder(this.signalStore, jid).initOutgoing(bundle);
      }
    }
  }

  /** Ensure signal sessions exist for the given device jids. */
  private async assertSessions(jids: string[]): Promise<void> {
    const missing = jids.filter((j) => !this.signalStore.hasSession(j));
    if (!missing.length) return;

    const result = await this.query({
      tag: 'iq',
      attrs: { xmlns: 'encrypt', type: 'get', to: S_WHATSAPP_NET },
      content: [
        {
          tag: 'key',
          attrs: {},
          content: missing.map((jid) => ({ tag: 'user', attrs: { jid } })),
        },
      ],
    });
    await this.injectE2ESessions(result);
  }

  private generateMessageId(): string {
    const data = Buffer.alloc(8 + 20 + 16);
    data.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 1000)));
    const me = this.creds.me?.id;
    if (me) {
      const user = jidDecode(me)?.user;
      if (user) {
        data.write(user, 8);
        data.write('@c.us', 8 + user.length);
      }
    }
    crypto.randomBytes(16).copy(data, 28);
    return '3EB0' + crypto.createHash('sha256').update(data).digest('hex').toUpperCase().substring(0, 18);
  }

  private encodeWAMessage(message: Record<string, unknown>): Promise<Buffer> {
    return WaProto.encodeMessage(message).then((encoded) => {
      const pad = crypto.randomBytes(1)[0]! & 0x0f;
      const padLength = pad + 1;
      return Buffer.concat([encoded, Buffer.alloc(padLength, padLength)]);
    });
  }

  /**
   * Send a 1:1 text message — fetches devices, builds signal sessions,
   * encrypts per device, relays the stanza, and resolves on server ack.
   */
  public async sendTextMessage(
    jid: string,
    text: string,
    contextInfo?: Record<string, unknown>,
  ): Promise<{ id: string }> {
    return this.sendWAMessage(jid, { conversation: text }, 'text', contextInfo);
  }

  /**
   * Send an arbitrary proto.Message payload (text, media, contact, location)
   * to a 1:1 chat. Encrypts for every recipient device and resolves on ack.
   */
  /**
   * Attach a proto.ContextInfo (quote / forwarded flag) to the innermost
   * message-type key. Plain `conversation` texts have no contextInfo field,
   * so they are promoted to extendedTextMessage — exactly what official
   * clients send for quoted replies.
   */
  private injectContextInfo(
    message: Record<string, unknown>,
    ctx: Record<string, unknown>,
  ): Record<string, unknown> {
    if (!ctx || !Object.keys(ctx).length) return message;
    if (typeof message.conversation === 'string') {
      return {
        extendedTextMessage: { text: message.conversation, contextInfo: ctx },
      };
    }
    for (const key of Object.keys(message)) {
      if (
        /Message$/.test(key) &&
        typeof message[key] === 'object' &&
        message[key] !== null
      ) {
        return {
          ...message,
          [key]: { ...(message[key] as Record<string, unknown>), contextInfo: ctx },
        };
      }
    }
    return message;
  }

  public async sendWAMessage(
    jid: string,
    message: Record<string, unknown>,
    stanzaType?: 'text' | 'media',
    contextInfo?: Record<string, unknown>,
  ): Promise<{ id: string }> {
    if (!this.creds.me) {
      throw new Error('Not authenticated — pair the device first');
    }
    if (contextInfo) {
      message = this.injectContextInfo(message, contextInfo);
    }

    const destinationJid = jid.includes('@')
      ? jid
      : jidEncode(jid.replace(/\D/g, ''), 's.whatsapp.net');
    const meId = this.creds.me.id;
    const meUser = jidDecode(meId)!.user;

    // enumerate devices for target + self — usync expects the bare account
    // JID; passing our own device-qualified id returns no own devices
    const meJid = jidEncode(meUser, 's.whatsapp.net');

    // LID-migrated contacts key their signal sessions under the @lid
    // identity — a PN-addressed cipher lands as "Waiting for this message".
    // Encrypt for BOTH address forms (same physical devices either way) and
    // keep the stanza `to` on the PN form, which the server always accepts.
    const meLidUser = jidDecode(this.creds.me.lid || '')?.user;
    const meLidJid = meLidUser ? jidEncode(meLidUser, 'lid') : undefined;
    let stanzaTo = destinationJid;
    let destCounterpart: string | undefined;
    const deviceQueryJids = [destinationJid, meJid];
    try {
      destCounterpart = (await this.resolveLidUsers([destinationJid])).get(
        destinationJid,
      );
      if (destCounterpart) {
        deviceQueryJids.push(destCounterpart);
        // A migrated account is addressed by its @lid form end-to-end:
        // stanza `to` AND participant jids must share one identity form,
        // mixing PN and LID in one stanza gets ack error 400.
        if (!destinationJid.endsWith('@lid')) stanzaTo = destCounterpart;
      }
    } catch { /* resolution is best-effort */ }
    if (meLidJid) deviceQueryJids.push(meLidJid);

    const deviceSet = new Set<string>();
    for (const q of deviceQueryJids) {
      try {
        for (const d of await this.getDeviceList([q])) deviceSet.add(d);
      } catch { /* one form failing must not kill the other */ }
    }
    const devices = [...deviceSet];
    // Exactly one address form per account in <participants>: a stanza
    // listing the same device under BOTH its PN and LID jid is rejected
    // (ack error 400). LID-migrated accounts take the @lid form (their
    // signal sessions live there), everyone else keeps PN.
    const destIsLidMigrated = !!destCounterpart;
    const meIsLidMigrated = !!meLidUser;
    const meRecipients: string[] = [];
    const otherRecipients: string[] = [];
    for (const dJid of devices) {
      const d = jidDecode(dJid)!;
      const isMe = d.user === meUser || d.user === meLidUser;
      const isLidForm = d.server === 'lid' || d.server === 'hosted.lid';
      const preferLid = isMe ? meIsLidMigrated : destIsLidMigrated;
      if (preferLid !== isLidForm) continue;
      (isMe ? meRecipients : otherRecipients).push(dJid);
    }

    const allRecipients = [...meRecipients, ...otherRecipients];
    if (!allRecipients.length) {
      throw new Error('No devices found for recipient');
    }

    await this.assertSessions(allRecipients);

    const meMsg = {
      deviceSentMessage: { destinationJid: stanzaTo, message },
    };

    let shouldIncludeDeviceIdentity = false;
    const encErrors: string[] = [];
    const encryptFor = async (deviceJid: string, msg: Record<string, unknown>): Promise<BinaryNode | null> => {
      try {
        const bytes = await this.encodeWAMessage(msg);
        const cipher = new SessionCipher(this.signalStore, deviceJid);
        const { type, body } = await cipher.encrypt(bytes);
        if (type === 'pkmsg') shouldIncludeDeviceIdentity = true;
        return {
          tag: 'to',
          attrs: { jid: deviceJid },
          content: [{ tag: 'enc', attrs: { v: '2', type }, content: body }],
        };
      } catch (e) {
        encErrors.push(`${deviceJid}: ${e instanceof Error ? e.message : String(e)}`);
        return null;
      }
    };

    const meNodes = (
      await Promise.all(meRecipients.map((j) => encryptFor(j, meMsg)))
    ).filter((n): n is BinaryNode => n !== null);
    const otherNodes = (
      await Promise.all(otherRecipients.map((j) => encryptFor(j, message)))
    ).filter((n): n is BinaryNode => n !== null);

    if (encErrors.length) {
      logger.warn(
        { destinationJid, encErrors, meOk: meNodes.length, otherOk: otherNodes.length },
        'some recipient devices failed encryption',
      );
    }
    if (stanzaType === 'media' || message.audioMessage || message.imageMessage || message.documentMessage || message.videoMessage) {
      logger.info(
        { destinationJid, meRecipients, otherRecipients, meOk: meNodes.length, otherOk: otherNodes.length },
        'media send recipients',
      );
    }
    const participants = [...meNodes, ...otherNodes];
    if (!participants.length) {
      throw new Error(
        `Failed to encrypt message for recipient devices (devices=${allRecipients.length}): ${encErrors.join(' | ') || 'no recipient devices'}`,
      );
    }
    const msgId = this.generateMessageId();
    this.cacheSentMessage(
      msgId,
      stanzaTo,
      message,
      stanzaType ??
        (message.conversation || message.extendedTextMessage ? 'text' : 'media'),
    );
    const stanza: BinaryNode = {
      tag: 'message',
      attrs: {
        id: msgId,
        to: stanzaTo,
        type:
          stanzaType ??
          (message.conversation || message.extendedTextMessage ? 'text' : 'media'),
      },
      content: [{ tag: 'participants', attrs: {}, content: participants }],
    };

    if (shouldIncludeDeviceIdentity && this.creds.account) {
      const account = { ...(this.creds.account as Record<string, unknown>) };
      if (!account.accountSignatureKey || !(account.accountSignatureKey as Buffer).length) {
        account.accountSignatureKey = null;
      }
      (stanza.content as BinaryNode[]).push({
        tag: 'device-identity',
        attrs: {},
        content: await WaProto.encodeADVSignedDeviceIdentity(account),
      });
    }

    // await the server ack for this message id
    await this.query(stanza, 25_000);
    return { id: msgId };
  }

  /**
   * Send a peer-data-operation protocol message to our own account. This is
   * how a companion asks the primary phone for history syncs, app-state
   * resends and similar peer traffic — encrypted for every OTHER device on
   * the account (the primary phone among them).
   */
  private async sendPeerDataOperation(
    pdoMessage: Record<string, unknown>,
  ): Promise<{ id: string }> {
    if (!this.creds.me) {
      throw new Error('Not authenticated — pair the device first');
    }

    const meId = this.creds.me.id;
    const meUser = jidDecode(meId)!.user;
    const meLid = this.creds.me.lid;
    const meLidUser = meLid ? jidDecode(meLid)?.user : undefined;

    const message = {
      protocolMessage: {
        peerDataOperationRequestMessage: pdoMessage,
        type: 16, // PEER_DATA_OPERATION_REQUEST_MESSAGE
      },
    };
    const bytes = await this.encodeWAMessage(message);

    // Peer messages target the primary device (device 0) directly — the
    // server does not enumerate own-account devices via usync. LID-migrated
    // accounts may only accept the request on the LID identity, so when a
    // LID is known we address that; otherwise the PN identity.
    const targets: { user: string; server: 'lid' | 's.whatsapp.net' }[] = [];
    if (meLidUser) targets.push({ user: meLidUser, server: 'lid' });
    targets.push({ user: meUser, server: 's.whatsapp.net' });

    let lastId = '';
    let sent = false;
    for (const t of targets) {
      const primaryJid = jidEncode(t.user, t.server, 0);
      try {
        await this.assertSessions([primaryJid]);
        const cipher = new SessionCipher(this.signalStore, primaryJid);
        const { type, body } = await cipher.encrypt(bytes);

        const msgId = this.generateMessageId();
        await this.query(
          {
            tag: 'message',
            attrs: {
              id: msgId,
              to: jidEncode(t.user, t.server),
              type: 'text',
              category: 'peer',
              push_priority: 'high_force',
            },
            content: [
              // Peer messages carry the bare <enc> node — no <participants> wrap.
              { tag: 'enc', attrs: { v: '2', type }, content: body },
              { tag: 'meta', attrs: { appdata: 'default' } },
            ],
          },
          25_000,
        );
        lastId = msgId;
        sent = true;
      } catch (e) {
        logger.warn({ target: primaryJid, err: e }, 'Peer data request to target failed');
      }
    }
    if (!sent) {
      throw new Error('Failed to deliver peer data request to any own identity');
    }
    return { id: lastId };
  }

  /**
   * Ask the primary phone for an on-demand FULL history sync. The phone
   * streams history chunks back as history-sync notifications, which the
   * regular notification handler decodes and emits as 'history.sync'.
   */
  public async requestFullHistorySync(): Promise<{ id: string }> {
    const requestId = crypto.randomBytes(8).toString('hex').toUpperCase();
    return this.sendPeerDataOperation({
      peerDataOperationRequestType: 6, // FULL_HISTORY_SYNC_ON_DEMAND
      fullHistorySyncOnDemandRequest: {
        requestMetadata: { requestId },
        historySyncConfig: {
          fullSyncDaysLimit: 3650,
          fullSyncSizeMbLimit: 100,
          storageQuotaMb: 10240,
          inlineInitialPayloadInE2EeMsg: true,
          supportRecentSyncChunkMessageCountTuning: true,
          supportMessageAssociation: true,
          supportCagReactionsAndPolls: true,
          onDemandReady: true,
        },
      },
    });
  }

  /**
   * Ask the primary phone for an on-demand history sync of ONE chat. Works on
   * regular accounts (unlike the full-history pull) but requires anchoring to
   * an existing message key in that chat.
   */
  public async requestChatHistory(
    chatJid: string,
    oldestMsgId: string,
    oldestMsgFromMe: boolean,
    oldestMsgTimestampMs: number,
    count = 50,
  ): Promise<{ id: string }> {
    return this.sendPeerDataOperation({
      peerDataOperationRequestType: 3, // HISTORY_SYNC_ON_DEMAND
      historySyncOnDemandRequest: {
        chatJid,
        oldestMsgId,
        oldestMsgFromMe,
        oldestMsgTimestampMs,
        onDemandMsgCount: count,
      },
    });
  }

  /**
   * Ask the primary phone to resend a specific message by its key —
   * PLACEHOLDER_MESSAGE_RESEND. Works for stanzas that arrived but could not
   * be decrypted (e.g. statuses pushed before sender-key support existed).
   */
  public async requestMessageResend(
    keys: Array<{ remoteJid: string; id: string; fromMe?: boolean; participant?: string }>,
  ): Promise<{ id: string }> {
    return this.sendPeerDataOperation({
      peerDataOperationRequestType: 4, // PLACEHOLDER_MESSAGE_RESEND
      placeholderMessageResendRequest: keys.map((k) => ({
        messageKey: {
          remoteJid: k.remoteJid,
          id: k.id,
          fromMe: k.fromMe ?? false,
          ...(k.participant ? { participant: k.participant } : {}),
        },
      })),
    });
  }

  /**
   * Ask the primary phone to regenerate and resend a specific history-sync
   * chunk — e.g. INITIAL_STATUS_V3 (syncType 1) carries the status
   * conversation, so this pulls current stories without a full resync.
   */
  public async requestHistoryChunkRetry(
    syncType: number,
    chunkOrder = 0,
    regenerateChunk = true,
  ): Promise<{ id: string }> {
    return this.sendPeerDataOperation({
      peerDataOperationRequestType: 10, // HISTORY_SYNC_CHUNK_RETRY
      historySyncChunkRetryRequest: {
        syncType,
        chunkOrder,
        regenerateChunk,
      },
    });
  }

  /**
   * Check whether a phone number is registered on WhatsApp. Uses the usync
   * devices query (same call used for message routing): a registered number
   * always reports at least one device, an unregistered one reports none.
   */
  public async onWhatsApp(phone: string): Promise<{ exists: boolean | null; jid?: string }> {
    const userJid = jidEncode(phone.replace(/\D/g, ''), 's.whatsapp.net');
    try {
      const devices = await this.getDeviceList([userJid]);
      return { exists: devices.length > 0, jid: userJid };
    } catch {
      return { exists: null };
    }
  }

  /**
   * Presence updates: 'composing'/'paused' are per-chat chatstate stanzas,
   * 'available'/'unavailable' are global presence stanzas.
   */
  public async sendPresenceUpdate(
    type: 'available' | 'unavailable' | 'composing' | 'paused',
    toJid?: string,
  ): Promise<void> {
    if (!this.isOpen) return;

    if (type === 'composing' || type === 'paused') {
      if (!toJid) return;
      const jid = toJid.includes('@')
        ? toJid
        : jidEncode(toJid.replace(/\D/g, ''), 's.whatsapp.net');
      await this.sendNode({
        tag: 'chatstate',
        attrs: { to: jid },
        content: [{ tag: type, attrs: {} }],
      });
      return;
    }

    await this.sendNode({
      tag: 'presence',
      attrs: { to: S_WHATSAPP_NET, type },
    });
  }

  /**
   * Mark messages read on WhatsApp — sends <receipt type='read'> so the
   * sender's ticks turn blue. `messageIds` are WhatsApp stanza ids; the last
   * one goes in the top-level id attr, the rest inside <list>.
   */
  public async sendReadReceipt(
    chatJid: string,
    messageIds: string[],
    participant?: string,
  ): Promise<void> {
    if (!this.isOpen || !messageIds.length) return;
    const jid = chatJid.includes('@')
      ? chatJid
      : jidEncode(chatJid.replace(/\D/g, ''), 's.whatsapp.net');
    const [last, ...rest] = [...messageIds].reverse();
    const attrs: Record<string, string> = { to: jid, id: last!, type: 'read' };
    if (participant) attrs.participant = participant;
    await this.sendNode({
      tag: 'receipt',
      attrs,
      content: rest.length
        ? [
            {
              tag: 'list',
              attrs: {},
              content: rest.map((id) => ({ tag: 'item', attrs: { id } })),
            },
          ]
        : undefined,
    });
  }

  /** Fetch a media upload ticket (auth + upload hosts) from the server. */
  private async getMediaConn(): Promise<{ auth: string; hosts: string[] }> {
    const res = await this.query({
      tag: 'iq',
      attrs: { to: S_WHATSAPP_NET, type: 'set', xmlns: 'w:m' },
      content: [{ tag: 'media_conn', attrs: {} }],
    });
    const mediaConn = getBinaryNodeChild(res, 'media_conn');
    const auth = mediaConn?.attrs?.auth;
    const hosts = getBinaryNodeChildren(mediaConn, 'host')
      .map((h) => h.attrs.hostname)
      .filter((h): h is string => !!h);
    if (!auth || !hosts.length) {
      throw new Error('media_conn query returned no auth/host');
    }
    return { auth, hosts };
  }

  /** Upload encrypted media bytes to a WhatsApp media host. */
  private async uploadMedia(
    ciphertext: Buffer,
    fileEncSha256: Buffer,
    mediaPath: string,
    conn: { auth: string; hosts: string[] },
  ): Promise<{ url: string; directPath: string }> {
    const token = fileEncSha256
      .toString('base64')
      .replace(/\+/g, '-')
      .replace(/\//g, '_')
      .replace(/=+$/, '');

    let lastError: unknown;
    for (const host of conn.hosts) {
      try {
        const resp = await axios.post(
          `https://${host}/mms/${mediaPath}/${token}?auth=${encodeURIComponent(conn.auth)}&token=${token}`,
          ciphertext,
          {
            headers: {
              'Content-Type': 'application/octet-stream',
              Origin: WA_ORIGIN,
            },
            timeout: 60_000,
            maxContentLength: Infinity,
            maxBodyLength: Infinity,
          },
        );
        const data = resp.data as { url?: string; direct_path?: string };
        if (data?.url && data?.direct_path) {
          return { url: data.url, directPath: data.direct_path };
        }
        lastError = new Error(`unexpected upload response from ${host}`);
      } catch (err) {
        lastError = err;
      }
    }
    throw lastError instanceof Error ? lastError : new Error('Media upload failed on all hosts');
  }

  /**
   * Send a media message (image, video, document, audio/voice note).
   * Encrypts the bytes, uploads them to the WhatsApp media servers, then
   * relays the media message stanza through the encrypted session.
   */
  public async sendMediaMessage(
    jid: string,
    opts: {
      type: 'image' | 'video' | 'document' | 'audio' | 'voice';
      buffer: Buffer;
      mimetype?: string;
      caption?: string;
      fileName?: string;
      seconds?: number;
      contextInfo?: Record<string, unknown>;
    },
  ): Promise<{ id: string }> {
    const waType: MediaType = opts.type === 'voice' ? 'voice' : (opts.type as MediaType);
    const mediaPath = waType === 'voice' || waType === 'audio' ? 'audio' : waType;

    const enc = MediaManager.encryptMedia(opts.buffer, waType);
    const conn = await this.getMediaConn();
    const uploaded = await this.uploadMedia(enc.ciphertext, enc.fileEncSha256, mediaPath, conn);

    const base = {
      url: uploaded.url,
      directPath: uploaded.directPath,
      mediaKey: enc.mediaKey,
      mediaKeyTimestamp: Math.floor(Date.now() / 1000),
      fileSha256: enc.fileSha256,
      fileEncSha256: enc.fileEncSha256,
      fileLength: enc.fileLength,
      mimetype: opts.mimetype || 'application/octet-stream',
    };

    let message: Record<string, unknown>;
    switch (opts.type) {
      case 'image':
        message = { imageMessage: { ...base, caption: opts.caption || undefined } };
        break;
      case 'video':
        message = { videoMessage: { ...base, caption: opts.caption || undefined } };
        break;
      case 'document':
        message = {
          documentMessage: {
            ...base,
            fileName: opts.fileName || 'document',
            caption: opts.caption || undefined,
          },
        };
        break;
      default:
        message = {
          audioMessage: {
            ...base,
            ptt: opts.type === 'voice',
            // WhatsApp refuses/ptt-renders badly without a duration.
            seconds: opts.seconds || undefined,
          },
        };
        break;
    }

    return this.sendWAMessage(jid, message, 'text', opts.contextInfo);
  }

  /** Send a contact card (vCard) message. */
  public async sendContactMessage(
    jid: string,
    contact: { displayName: string; phone: string },
  ): Promise<{ id: string }> {
    const cleanPhone = contact.phone.replace(/\D/g, '');
    const vcard =
      'BEGIN:VCARD\n' +
      'VERSION:3.0\n' +
      `FN:${contact.displayName}\n` +
      `TEL;type=CELL;type=VOICE;waid=${cleanPhone}:+${cleanPhone}\n` +
      'END:VCARD';
    return this.sendWAMessage(
      jid,
      { contactMessage: { displayName: contact.displayName, vcard } },
      'text',
    );
  }

  /** Send a location pin message. */
  public async sendLocationMessage(
    jid: string,
    location: { latitude: number; longitude: number; name?: string; address?: string },
  ): Promise<{ id: string }> {
    return this.sendWAMessage(
      jid,
      {
        locationMessage: {
          degreesLatitude: location.latitude,
          degreesLongitude: location.longitude,
          name: location.name || undefined,
          address: location.address || undefined,
        },
      },
      'text',
    );
  }

  /** Send a reaction emoji to a message. */
  public async sendReactionMessage(
    jid: string,
    opts: {
      messageId: string;
      emoji: string;
      fromMe?: boolean;
    },
  ): Promise<{ id: string }> {
    const destinationJid = jid.includes('@')
      ? jid
      : jidEncode(jid.replace(/\D/g, ''), 's.whatsapp.net');
    const forms = [destinationJid];
    try {
      const other = (await this.resolveLidUsers([destinationJid])).get(
        destinationJid,
      );
      if (other) forms.push(other);
    } catch { /* best-effort */ }

    let lastId = { id: '' };
    for (const form of forms) {
      const message = {
        reactionMessage: {
          key: {
            remoteJid: form,
            fromMe: opts.fromMe ?? false,
            id: opts.messageId,
          },
          text: opts.emoji,
          senderTimestampMs: Date.now(),
        },
      };
      lastId = await this.sendWAMessage(form, message, 'text');
    }
    return lastId;
  }

  /**
   * "Delete for everyone" — sends protocolMessage{type: REVOKE} referencing
   * the original stanza key so every recipient wipes that message.
   */
  public async sendRevokeMessage(
    jid: string,
    opts: { messageId: string; fromMe?: boolean },
  ): Promise<{ id: string }> {
    const destinationJid = jid.includes('@')
      ? jid
      : jidEncode(jid.replace(/\D/g, ''), 's.whatsapp.net');

    // A migrated account is addressed by its @lid form end-to-end: stanza `to`
    // must share the identity form the recipient sessions live under (same
    // rule sendWAMessage applies internally — mixing PN and LID in one stanza
    // gets ack error 400).
    let stanzaTo = destinationJid;
    let counterpart: string | undefined;
    try {
      counterpart = (await this.resolveLidUsers([destinationJid])).get(
        destinationJid,
      );
      if (counterpart && !destinationJid.endsWith('@lid')) {
        stanzaTo = counterpart;
      }
    } catch { /* resolution is best-effort */ }

    // The original message may have been keyed under EITHER the PN or the LID
    // remoteJid (pre- vs post-migration) — send a revoke for every known key
    // form; a non-matching key is silently ignored by the recipient.
    const keyForms = new Set<string>([destinationJid]);
    if (counterpart) keyForms.add(counterpart);

    let lastId = { id: '' };
    let lastErr: unknown;
    for (const keyForm of keyForms) {
      try {
        const message = {
          protocolMessage: {
            key: {
              remoteJid: keyForm,
              fromMe: opts.fromMe ?? true,
              id: opts.messageId,
            },
            type: 'REVOKE',
          },
        };
        lastId = await this.sendWAMessage(stanzaTo, message, 'text');
      } catch (err) {
        lastErr = err;
      }
    }
    if (!lastId.id && lastErr) throw lastErr;
    logger.info(
      { to: stanzaTo, keyForms: [...keyForms], messageId: opts.messageId },
      'revoke sent',
    );
    return lastId;
  }

  // ------------------------------------------------------------------
  // Keepalive / shutdown
  // ------------------------------------------------------------------

  private startKeepAlive(): void {
    this.stopKeepAlive();
    this.keepAliveTimer = setInterval(() => {
      if (!this.lastDateRecv) {
        this.lastDateRecv = Date.now();
      }
      const diff = Date.now() - this.lastDateRecv;
      if (diff > KEEP_ALIVE_INTERVAL_MS + 5000) {
        void this.end(new Error('Connection was lost'));
      } else if (this.isOpen) {
        this.query({
          tag: 'iq',
          attrs: {
            id: this.generateMessageTag(),
            to: S_WHATSAPP_NET,
            type: 'get',
            xmlns: 'w:p',
          },
          content: [{ tag: 'ping', attrs: {} }],
        }).catch(() => undefined);
      }
    }, KEEP_ALIVE_INTERVAL_MS);
  }

  private stopKeepAlive(): void {
    if (this.keepAliveTimer) {
      clearInterval(this.keepAliveTimer);
      this.keepAliveTimer = null;
    }
  }

  private saveCreds(): void {
    this.opts.onCredsUpdate?.(this.creds);
    this.emit('creds.update', this.creds);
  }

  public async end(error?: Error): Promise<void> {
    if (this.closed) {
      return;
    }
    this.closed = true;
    this.state = 'close';

    this.stopKeepAlive();
    if (this.qrTimer) {
      clearTimeout(this.qrTimer);
      this.qrTimer = null;
    }

    for (const [, q] of this.pendingQueries) {
      clearTimeout(q.timer);
      q.reject(error ?? new Error('Connection Closed'));
    }
    this.pendingQueries.clear();

    try {
      await this.transport?.disconnect(1000, 'Normal Closure');
    } catch {
      // ignore
    }
    this.transport = null;
    this.noise = null;

    const statusCode = (error as { statusCode?: number })?.statusCode;
    const shouldReconnect =
      this.expectsRestartAfterPairing || (statusCode !== 401 && statusCode !== 403 && statusCode !== 419);

    this.emit('close', {
      reason: error?.message,
      statusCode,
      shouldReconnect,
    });
  }

  public get wasPairedRestart(): boolean {
    return this.expectsRestartAfterPairing;
  }

  public clearRestartFlag(): void {
    this.expectsRestartAfterPairing = false;
  }
}
