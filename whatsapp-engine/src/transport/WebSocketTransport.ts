import WebSocket from 'ws';
import { ITransport, MessageCallback, CloseCallback, ErrorCallback } from './ITransport.js';

export interface WebSocketTransportOptions {
  connectTimeoutMs?: number;
  pingIntervalMs?: number;
  headers?: Record<string, string>;
  origin?: string;
}

export class WebSocketTransport implements ITransport {
  private socket: WebSocket | null = null;
  private messageCallbacks: MessageCallback[] = [];
  private closeCallbacks: CloseCallback[] = [];
  private errorCallbacks: ErrorCallback[] = [];
  private pingTimer: NodeJS.Timeout | null = null;
  private isAlive = false;

  constructor(private readonly options: WebSocketTransportOptions = {}) {}

  public get isConnected(): boolean {
    return this.socket !== null && this.socket.readyState === WebSocket.OPEN;
  }

  public async connect(url: string): Promise<void> {
    if (this.isConnected) {
      return;
    }

    return new Promise<void>((resolve, reject) => {
      const timeoutMs = this.options.connectTimeoutMs ?? 15000;
      let settled = false;

      const timer = setTimeout(() => {
        if (!settled) {
          settled = true;
          this.disconnect(1006, 'Connection timeout');
          reject(new Error(`WebSocket connection timeout after ${timeoutMs}ms`));
        }
      }, timeoutMs);

      try {
        this.socket = new WebSocket(url, {
          headers: this.options.headers,
          origin: this.options.origin || 'https://web.whatsapp.com',
          handshakeTimeout: timeoutMs,
        });

        this.socket.binaryType = 'nodebuffer';

        this.socket.on('open', () => {
          if (!settled) {
            settled = true;
            clearTimeout(timer);
            this.isAlive = true;
            this.startHeartbeat();
            resolve();
          }
        });

        this.socket.on('message', (data: WebSocket.RawData) => {
          let buffer: Buffer;
          if (Buffer.isBuffer(data)) {
            buffer = data;
          } else if (Array.isArray(data)) {
            buffer = Buffer.concat(data);
          } else if (data instanceof ArrayBuffer) {
            buffer = Buffer.from(data);
          } else {
            buffer = Buffer.from(String(data));
          }

          for (const cb of this.messageCallbacks) {
            try {
              cb(buffer);
            } catch (err) {
              this.notifyError(err instanceof Error ? err : new Error(String(err)));
            }
          }
        });

        this.socket.on('pong', () => {
          this.isAlive = true;
        });

        this.socket.on('close', (code: number, reason: Buffer) => {
          this.stopHeartbeat();
          const reasonStr = reason.toString('utf8');
          if (!settled) {
            settled = true;
            clearTimeout(timer);
            reject(new Error(`WebSocket closed before open: ${code} ${reasonStr}`));
          }
          for (const cb of this.closeCallbacks) {
            try {
              cb(code, reasonStr);
            } catch (err) {
              this.notifyError(err instanceof Error ? err : new Error(String(err)));
            }
          }
        });

        this.socket.on('error', (err: Error) => {
          if (!settled) {
            settled = true;
            clearTimeout(timer);
            reject(err);
          }
          this.notifyError(err);
        });
      } catch (err) {
        clearTimeout(timer);
        reject(err instanceof Error ? err : new Error(String(err)));
      }
    });
  }

  public async disconnect(code = 1000, reason = 'Normal Closure'): Promise<void> {
    this.stopHeartbeat();
    if (this.socket) {
      try {
        this.socket.close(code, reason);
      } catch {
        // ignore socket already closing
      }
      this.socket = null;
    }
  }

  public async send(data: Buffer): Promise<void> {
    if (!this.isConnected || !this.socket) {
      throw new Error('Cannot send data: WebSocket is not open');
    }

    return new Promise<void>((resolve, reject) => {
      this.socket!.send(data, (err) => {
        if (err) {
          reject(err);
        } else {
          resolve();
        }
      });
    });
  }

  public onMessage(callback: MessageCallback): void {
    this.messageCallbacks.push(callback);
  }

  public onClose(callback: CloseCallback): void {
    this.closeCallbacks.push(callback);
  }

  public onError(callback: ErrorCallback): void {
    this.errorCallbacks.push(callback);
  }

  private startHeartbeat(): void {
    this.stopHeartbeat();
    const intervalMs = this.options.pingIntervalMs ?? 20000;
    if (intervalMs <= 0) {
      return; // heartbeat disabled — stanza-level keepalive is used instead
    }
    this.pingTimer = setInterval(() => {
      if (!this.isConnected || !this.socket) {
        return;
      }
      if (!this.isAlive) {
        this.disconnect(1006, 'Heartbeat timeout');
        return;
      }
      this.isAlive = false;
      this.socket.ping();
    }, intervalMs);
  }

  private stopHeartbeat(): void {
    if (this.pingTimer) {
      clearInterval(this.pingTimer);
      this.pingTimer = null;
    }
  }

  private notifyError(error: Error): void {
    for (const cb of this.errorCallbacks) {
      try {
        cb(error);
      } catch {
        // avoid unhandled nested error
      }
    }
  }
}
