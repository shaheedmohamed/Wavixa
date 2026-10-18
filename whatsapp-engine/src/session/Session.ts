import QRCode from 'qrcode';
import { DeviceIdentityManager } from '../identity/DeviceIdentityManager.js';
import { SessionStateMachine, SessionState } from './SessionStateMachine.js';
import { ISessionStore } from './SessionStore.js';
import { KeyStore } from '../identity/KeyStore.js';
import { BinaryNode } from '../protocol/binary/BinaryNode.js';
import { WaClient } from '../waweb/WaClient.js';
import { FileCredsStore, initAuthCreds, WaCreds } from '../waweb/WaCreds.js';

export interface SessionOptions {
  sessionId: string;
  userId?: string;
  store: ISessionStore;
  keyStore: KeyStore;
  endpointUrl?: string;
  workerId?: string;
  credsStore?: FileCredsStore;
}

export type NodeCallback = (node: BinaryNode) => void;
export type MessageCallback = (msg: {
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
  media?: import('../message/HistorySyncProcessor.js').NormalizedMedia;
  quotedMessageId?: string;
  quotedParticipant?: string;
  raw: unknown;
}) => void;
export type HistorySyncCallback = (
  payload: import('../message/HistorySyncProcessor.js').ParsedHistorySync,
) => void;
export type RevokedCallback = (revoke: {
  jid: string;
  messageId: string;
  fromMe?: boolean;
  revoker?: string;
}) => void;
export type ConnectedCallback = (info: { phoneNumber?: string; name?: string }) => void;
export type DisconnectedCallback = (info: { reason?: string; loggedOut?: boolean }) => void;
export type QrCallback = (qr: SessionQrState) => void;

export interface SessionQrState {
  raw: string;
  dataUrl: string | null;
  generatedAt: number;
}

export class Session {
  public readonly sessionId: string;
  public readonly userId?: string;
  public phoneNumber?: string;
  public pushName?: string;

  private stateMachine: SessionStateMachine;
  private identity: DeviceIdentityManager;
  private store: ISessionStore;
  private keyStore: KeyStore;
  private endpointUrl: string;
  private workerId: string;
  private credsStore: FileCredsStore;

  private waClient: WaClient | null = null;
  private creds: WaCreds | null = null;

  private currentQr: SessionQrState | null = null;
  private currentPairingCode: { code: string; generatedAt: number; phoneNumber?: string } | null = null;
  private qrWaiters: Array<(qr: SessionQrState) => void> = [];
  private openWaiters: Array<() => void> = [];

  private nodeCallbacks: NodeCallback[] = [];
  private messageCallbacks: MessageCallback[] = [];
  private connectedCallbacks: ConnectedCallback[] = [];
  private disconnectedCallbacks: DisconnectedCallback[] = [];
  private qrCallbacks: QrCallback[] = [];
  private historySyncCallbacks: HistorySyncCallback[] = [];
  private revokedCallbacks: RevokedCallback[] = [];
  private leaseTimer: NodeJS.Timeout | null = null;
  private reconnectTimer: NodeJS.Timeout | null = null;
  private reconnectAttempts = 0;
  private manualDisconnect = false;
  /** Once retired (replaced by a newer session for the same phone) the session must never reconnect. */
  private retired = false;

  constructor(options: SessionOptions) {
    this.sessionId = options.sessionId;
    this.userId = options.userId;
    this.store = options.store;
    this.keyStore = options.keyStore;
    this.endpointUrl = options.endpointUrl || 'wss://web.whatsapp.com/ws/chat';
    this.workerId = options.workerId || 'worker-1';
    this.credsStore = options.credsStore || new FileCredsStore();

    this.stateMachine = new SessionStateMachine('DISCONNECTED');
    this.identity = new DeviceIdentityManager();
  }

  public getState(): SessionState {
    return this.stateMachine.getState();
  }

  public getIdentity(): DeviceIdentityManager {
    return this.identity;
  }

  /** Real QR emitted by the WhatsApp server (already includes ref + keys). */
  public getQR(): SessionQrState | null {
    return this.currentQr;
  }

  /** Real pairing code issued by the WhatsApp linking flow. */
  public getPairingCode(): { code: string; generatedAt: number; phoneNumber?: string } | null {
    return this.currentPairingCode;
  }

  public onNode(callback: NodeCallback): void {
    this.nodeCallbacks.push(callback);
  }

  /** Subscribe to decrypted incoming WhatsApp messages. */
  public onMessage(callback: MessageCallback): void {
    this.messageCallbacks.push(callback);
  }

  /** Fires when a contact uses "delete for everyone" on a message. */
  public onRevoked(callback: RevokedCallback): void {
    this.revokedCallbacks.push(callback);
  }

  /** Fired once the WhatsApp login completes and the session is usable. */
  public onConnected(callback: ConnectedCallback): void {
    this.connectedCallbacks.push(callback);
  }

  /** Fired only on a terminal disconnect (logout or final close), not on reconnects. */
  public onDisconnected(callback: DisconnectedCallback): void {
    this.disconnectedCallbacks.push(callback);
  }

  /** Fired every time the server pushes a fresh pairing QR. */
  public onQrCode(callback: QrCallback): void {
    this.qrCallbacks.push(callback);
  }

  /** Fired when a WhatsApp history-sync chunk has been downloaded and decoded. */
  public onHistorySync(callback: HistorySyncCallback): void {
    this.historySyncCallbacks.push(callback);
  }

  /**
   * Connect to WhatsApp Web using the real wire protocol.
   * - QR mode: performs handshake + registration, waits for the server to
   *   push a pair-device ref, renders it, and returns a data URL.
   * - Phone mode: performs handshake + registration, then issues a real
   *   pairing code request to the server.
   */
  public async connect(
    phoneNumber?: string,
  ): Promise<{ mode: 'qr' | 'phone'; code?: string; qr?: string }> {
    const lockAcquired = await this.store.acquireLock(this.sessionId, this.workerId, 30000);
    if (!lockAcquired) {
      throw new Error(`Session ${this.sessionId} is currently locked by another active worker`);
    }

    this.startLeaseRenewal();
    this.cancelReconnectTimer();
    this.manualDisconnect = false;
    if (this.stateMachine.getState() !== 'CONNECTING') {
      this.stateMachine.transition('CONNECTING');
    }

    this.creds = this.credsStore.load(this.sessionId) ?? initAuthCreds();
    this.waClient = this.createClient(this.creds);

    await this.waClient.connect();
    this.stateMachine.transition('AUTHENTICATING');
    await this.saveCurrentState();

    // Already paired → server logs us in directly; wait for the 'open' signal.
    // Never send link_code_companion_reg on a registered device — the server
    // treats it as a fresh registration and invalidates the existing session.
    if (this.creds.me) {
      await this.waitForOpen(15_000).catch(() => undefined);
      return { mode: 'qr' };
    }

    if (phoneNumber) {
      this.phoneNumber = phoneNumber;
      const code = await this.waClient.requestPairingCode(phoneNumber);
      this.currentPairingCode = { code, generatedAt: Date.now(), phoneNumber };
      return { mode: 'phone', code };
    }

    // QR mode: wait for the server-pushed pairing ref
    const qr = await this.waitForQr(30_000);
    return { mode: 'qr', qr: qr.dataUrl || qr.raw };
  }

  private createClient(creds: WaCreds): WaClient {
    const client = new WaClient(creds, {
      url: this.endpointUrl,
      sessionId: this.sessionId,
      onCredsUpdate: (c) => {
        this.creds = c;
        this.credsStore.save(this.sessionId, c);
      },
    });

    client.on('qr', (qr) => {
      void this.onQr(qr);
    });

    client.on('pairing-code', (code) => {
      this.currentPairingCode = { code, generatedAt: Date.now() };
    });

    client.on('open', (me) => {
      this.reconnectAttempts = 0;
      this.currentQr = null;
      this.currentPairingCode = null;
      const waiters = this.openWaiters.splice(0);
      for (const w of waiters) w();
      if (me?.id) {
        const num = me.id.split('@')[0]?.split(':')[0];
        if (num) this.phoneNumber = num;
      }
      if (this.creds?.me?.name) {
        this.pushName = this.creds.me.name;
      }
      // Walk toward READY through valid transitions
      const walkTo = (target: SessionState) => {
        if (!this.stateMachine.canTransition(target)) {
          if (this.stateMachine.canTransition('CONNECTING')) {
            this.stateMachine.transition('CONNECTING');
          }
          if (this.stateMachine.canTransition('AUTHENTICATING')) {
            this.stateMachine.transition('AUTHENTICATING');
          }
        }
        if (this.stateMachine.canTransition(target)) {
          this.stateMachine.transition(target);
        }
      };
      walkTo('CONNECTED');
      walkTo('READY');
      void this.saveCurrentState();

      for (const cb of this.connectedCallbacks) {
        try {
          cb({ phoneNumber: this.phoneNumber, name: this.pushName });
        } catch {
          // isolate callback errors
        }
      }
    });

    client.on('close', (info) => {
      void this.onClientClose(info);
    });

    client.on('stanza', (node) => {
      for (const cb of this.nodeCallbacks) {
        try {
          cb(node);
        } catch {
          // isolate callback errors
        }
      }
    });

    client.on('message', (msg) => {
      for (const cb of this.messageCallbacks) {
        try {
          cb(msg);
        } catch {
          // isolate callback errors
        }
      }
    });

    client.on('message.revoked', (revoke) => {
      for (const cb of this.revokedCallbacks) {
        try {
          cb(revoke);
        } catch {
          // isolate callback errors
        }
      }
    });

    client.on('history.sync', (payload) => {
      for (const cb of this.historySyncCallbacks) {
        try {
          cb(payload);
        } catch {
          // isolate callback errors
        }
      }
    });

    return client;
  }

  private async onQr(rawQr: string): Promise<void> {
    let dataUrl: string | null = null;
    try {
      dataUrl = await QRCode.toDataURL(rawQr, { margin: 1, width: 300 });
    } catch {
      dataUrl = null;
    }

    this.currentQr = { raw: rawQr, dataUrl, generatedAt: Date.now() };

    for (const cb of this.qrCallbacks) {
      try {
        cb(this.currentQr);
      } catch {
        // isolate callback errors
      }
    }

    const waiters = this.qrWaiters.splice(0);
    for (const w of waiters) {
      w(this.currentQr);
    }
  }

  private waitForOpen(timeoutMs: number): Promise<void> {
    return new Promise((resolve, reject) => {
      const timer = setTimeout(() => {
        reject(new Error('Timed out waiting for WhatsApp login'));
      }, timeoutMs);
      this.openWaiters.push(() => {
        clearTimeout(timer);
        resolve();
      });
    });
  }

  private waitForQr(timeoutMs: number): Promise<SessionQrState> {
    if (this.currentQr) {
      return Promise.resolve(this.currentQr);
    }
    return new Promise((resolve, reject) => {
      const timer = setTimeout(() => {
        reject(new Error('Timed out waiting for WhatsApp pairing QR from server'));
      }, timeoutMs);
      this.qrWaiters.push((qr) => {
        clearTimeout(timer);
        resolve(qr);
      });
    });
  }

  private async onClientClose(info: {
    reason?: string;
    statusCode?: number;
    shouldReconnect: boolean;
  }): Promise<void> {
    const wasPairRestart = this.waClient?.wasPairedRestart === true;

    // Deliberate disconnect() must not bounce back into a reconnect loop.
    if (this.manualDisconnect) {
      this.manualDisconnect = false;
      if (this.stateMachine.canTransition('DISCONNECTED')) {
        this.stateMachine.transition('DISCONNECTED');
      }
      await this.saveCurrentState();
      return;
    }

    if (info.statusCode === 401 || info.statusCode === 403 || info.statusCode === 419) {
      if (this.stateMachine.canTransition('LOGGED_OUT')) {
        this.stateMachine.transition('LOGGED_OUT');
      }
      this.credsStore.delete(this.sessionId);
      await this.saveCurrentState();
      this.fireDisconnected(info.reason || 'logged_out', true);
      return;
    }

    if (info.shouldReconnect || wasPairRestart) {
      if (this.retired) {
        if (this.stateMachine.canTransition('DISCONNECTED')) {
          this.stateMachine.transition('DISCONNECTED');
        }
        await this.saveCurrentState();
        return;
      }
      if (this.stateMachine.canTransition('RECONNECTING')) {
        this.stateMachine.transition('RECONNECTING');
      }
      this.reconnectAttempts += 1;
      const delay = Math.min(1000 * Math.pow(2, this.reconnectAttempts), 30_000);
      this.reconnectTimer = setTimeout(() => {
        this.reconnectTimer = null;
        void this.reconnect().catch(() => {
          if (this.stateMachine.canTransition('ERROR')) {
            this.stateMachine.transition('ERROR');
          }
          this.fireDisconnected('reconnect_failed', false);
        });
      }, delay);
      return;
    }

    if (this.stateMachine.canTransition('DISCONNECTED')) {
      this.stateMachine.transition('DISCONNECTED');
    }
    await this.saveCurrentState();
    this.fireDisconnected(info.reason || 'disconnected', false);
  }

  private fireDisconnected(reason: string, loggedOut: boolean): void {
    for (const cb of this.disconnectedCallbacks) {
      try {
        cb({ reason, loggedOut });
      } catch {
        // isolate callback errors
      }
    }
  }

  private async reconnect(): Promise<void> {
    if (!this.creds || this.retired || this.manualDisconnect) {
      return;
    }
    if (
      this.stateMachine.getState() === 'DISCONNECTED' ||
      this.stateMachine.getState() === 'LOGGED_OUT'
    ) {
      return;
    }
    if (this.stateMachine.getState() === 'RECONNECTING' && this.stateMachine.canTransition('CONNECTING')) {
      this.stateMachine.transition('CONNECTING');
    }
    this.waClient = this.createClient(this.creds);
    await this.waClient.connect();
    if (this.stateMachine.canTransition('AUTHENTICATING')) {
      this.stateMachine.transition('AUTHENTICATING');
    }
  }

  /** Re-issue a pairing code or rotate the QR after connect() resolved. */
  public async requestPairingCode(phoneNumber: string): Promise<string> {
    if (!this.waClient) {
      throw new Error('Session is not connected');
    }
    if (this.creds?.me) {
      throw new Error('Session is already paired to a WhatsApp account');
    }
    const code = await this.waClient.requestPairingCode(phoneNumber);
    this.currentPairingCode = { code, generatedAt: Date.now(), phoneNumber };
    return code;
  }

  /**
   * Send a binary node over the live encrypted transport.
   */
  public async sendNode(node: BinaryNode): Promise<void> {
    if (this.stateMachine.getState() !== 'READY' && this.stateMachine.getState() !== 'CONNECTED') {
      throw new Error(`Cannot send node: Session is in ${this.stateMachine.getState()} state`);
    }
    if (!this.waClient) {
      throw new Error('WhatsApp client not initialized');
    }
    await this.waClient.sendNode(node);
  }

  /** Expose the underlying client for message-level operations. */
  public getClient(): WaClient | null {
    return this.waClient;
  }

  /**
   * Send a real WhatsApp text message through the authenticated session.
   * Resolves only when the server acknowledges the stanza.
   */
  public async sendTextMessage(
    to: string,
    text: string,
    contextInfo?: Record<string, unknown>,
  ): Promise<{ id: string }> {
    return this.sendWAMessage(to, { conversation: text }, contextInfo);
  }

  /**
   * Send an arbitrary proto.Message payload through the authenticated session.
   */
  public async sendWAMessage(
    to: string,
    message: Record<string, unknown>,
    contextInfo?: Record<string, unknown>,
  ): Promise<{ id: string }> {
    const state = this.stateMachine.getState();
    if (state !== 'READY' && state !== 'CONNECTED') {
      throw new Error(`Session is not connected (state: ${state})`);
    }
    if (!this.waClient) {
      throw new Error('WhatsApp client not initialized');
    }
    return this.waClient.sendWAMessage(to, message, undefined, contextInfo);
  }

  /**
   * Send an encrypted media message (image, video, document, audio/voice note).
   */
  public async sendMediaMessage(
    to: string,
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
    const state = this.stateMachine.getState();
    if (state !== 'READY' && state !== 'CONNECTED') {
      throw new Error(`Session is not connected (state: ${state})`);
    }
    if (!this.waClient) {
      throw new Error('WhatsApp client not initialized');
    }
    return this.waClient.sendMediaMessage(to, opts);
  }

  /**
   * Send an emoji reaction to a specific message.
   */
  public async sendReactionMessage(
    to: string,
    opts: { messageId: string; emoji: string; fromMe?: boolean },
  ): Promise<{ id: string }> {
    const state = this.stateMachine.getState();
    if (state !== 'READY' && state !== 'CONNECTED') {
      throw new Error(`Session is not connected (state: ${state})`);
    }
    if (!this.waClient) {
      throw new Error('WhatsApp client not initialized');
    }
    return this.waClient.sendReactionMessage(to, opts);
  }

  /** "Delete for everyone" — revoke one of our sent messages. */
  public async sendRevokeMessage(
    to: string,
    opts: { messageId: string; fromMe?: boolean },
  ): Promise<{ id: string }> {
    const state = this.stateMachine.getState();
    if (state !== 'READY' && state !== 'CONNECTED') {
      throw new Error(`Session is not connected (state: ${state})`);
    }
    if (!this.waClient) {
      throw new Error('WhatsApp client not initialized');
    }
    return this.waClient.sendRevokeMessage(to, opts);
  }

  /**
   * Ask the primary phone for an on-demand full history sync. The response
   * arrives asynchronously as 'history.sync' events.
   */
  public async requestFullHistorySync(): Promise<{ id: string }> {
    const state = this.stateMachine.getState();
    if (state !== 'READY' && state !== 'CONNECTED') {
      throw new Error(`Session is not connected (state: ${state})`);
    }
    if (!this.waClient) {
      throw new Error('WhatsApp client not initialized');
    }
    return this.waClient.requestFullHistorySync();
  }

  /** Ask the phone to resend specific messages by key. */
  public async requestMessageResend(
    keys: Array<{ remoteJid: string; id: string; fromMe?: boolean; participant?: string }>,
  ): Promise<{ id: string }> {
    const state = this.stateMachine.getState();
    if (state !== 'READY' && state !== 'CONNECTED') {
      throw new Error(`Session is not connected (state: ${state})`);
    }
    if (!this.waClient) {
      throw new Error('WhatsApp client not initialized');
    }
    return this.waClient.requestMessageResend(keys);
  }

  /** Ask the phone to regenerate one history-sync chunk (e.g. syncType 1 = statuses). */
  public async requestHistoryChunkRetry(
    syncType: number,
    chunkOrder = 0,
    regenerateChunk = true,
  ): Promise<{ id: string }> {
    const state = this.stateMachine.getState();
    if (state !== 'READY' && state !== 'CONNECTED') {
      throw new Error(`Session is not connected (state: ${state})`);
    }
    if (!this.waClient) {
      throw new Error('WhatsApp client not initialized');
    }
    return this.waClient.requestHistoryChunkRetry(syncType, chunkOrder, regenerateChunk);
  }

  /** On-demand history sync for a single known chat. */
  public async requestChatHistory(
    chatJid: string,
    oldestMsgId: string,
    oldestMsgFromMe: boolean,
    oldestMsgTimestampMs: number,
    count = 50,
  ): Promise<{ id: string }> {
    const state = this.stateMachine.getState();
    if (state !== 'READY' && state !== 'CONNECTED') {
      throw new Error(`Session is not connected (state: ${state})`);
    }
    if (!this.waClient) {
      throw new Error('WhatsApp client not initialized');
    }
    return this.waClient.requestChatHistory(
      chatJid,
      oldestMsgId,
      oldestMsgFromMe,
      oldestMsgTimestampMs,
      count,
    );
  }

  /**
   * Check whether a phone number is registered on WhatsApp.
   * Returns null when the session cannot perform the query right now.
   */
  public async checkNumberOnWhatsApp(phone: string): Promise<boolean | null> {
    if (!this.waClient || !this.waClient.isOpen) {
      return null;
    }
    try {
      const result = await this.waClient.onWhatsApp(phone);
      return result.exists;
    } catch {
      return null;
    }
  }

  /** Relay a presence/chatstate update through the live socket. */
  public async sendPresenceUpdate(
    type: 'available' | 'unavailable' | 'composing' | 'paused',
    toJid?: string,
  ): Promise<void> {
    if (!this.waClient || !this.waClient.isOpen) {
      return;
    }
    await this.waClient.sendPresenceUpdate(type, toJid);
  }

  /** Relay a WhatsApp read receipt for the given stanza ids. */
  public async sendReadReceipt(
    chatJid: string,
    messageIds: string[],
    participant?: string,
  ): Promise<void> {
    if (!this.waClient || !this.waClient.isOpen) {
      return;
    }
    await this.waClient.sendReadReceipt(chatJid, messageIds, participant);
  }

  /**
   * Graceful disconnection and lock release.
   */
  public async disconnect(reason = 'Normal closure'): Promise<void> {
    this.stopLeaseRenewal();
    this.cancelReconnectTimer();
    this.manualDisconnect = true;
    if (this.waClient) {
      await this.waClient.end(new Error(reason));
      this.waClient = null;
    }

    this.currentQr = null;
    this.currentPairingCode = null;

    if (this.stateMachine.canTransition('DISCONNECTED')) {
      this.stateMachine.transition('DISCONNECTED');
    }

    await this.store.releaseLock(this.sessionId, this.workerId);
    await this.saveCurrentState();
    this.fireDisconnected(reason, false);
  }

  /**
   * Permanently retire this session — used when a newer session claims the
   * same WhatsApp phone number. Disconnects, cancels every reconnect path,
   * and wipes the stored credentials so the boot-restorer never revives it.
   */
  public async retire(reason = 'replaced_by_new_login'): Promise<void> {
    this.retired = true;
    this.creds = null;
    await this.disconnect(reason);
    try {
      this.credsStore.delete(this.sessionId);
    } catch {
      // best effort — boot restore re-checks the file anyway
    }
  }

  private cancelReconnectTimer(): void {
    if (this.reconnectTimer) {
      clearTimeout(this.reconnectTimer);
      this.reconnectTimer = null;
    }
  }

  private async saveCurrentState(): Promise<void> {
    const keyBundle = this.keyStore.exportEncryptedBundle(this.sessionId, this.identity);
    await this.store.saveSession({
      sessionId: this.sessionId,
      userId: this.userId,
      phoneNumber: this.phoneNumber,
      pushName: this.pushName,
      state: this.stateMachine.getState(),
      keyBundle,
      workerId: this.workerId,
      lastSeenAt: Date.now(),
    });
  }

  private startLeaseRenewal(): void {
    this.stopLeaseRenewal();
    this.leaseTimer = setInterval(async () => {
      try {
        await this.store.renewLock(this.sessionId, this.workerId, 30000);
      } catch {
        // failed renewal will trigger failover on next attempt
      }
    }, 10000);
  }

  private stopLeaseRenewal(): void {
    if (this.leaseTimer) {
      clearInterval(this.leaseTimer);
      this.leaseTimer = null;
    }
  }
}
