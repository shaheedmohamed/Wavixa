/**
 * Wavixa WhatsApp Engine - Transport Abstraction
 * Pure transport interface decoupled from WhatsApp business logic
 */

export type MessageCallback = (data: Buffer) => void;
export type CloseCallback = (code: number, reason: string) => void;
export type ErrorCallback = (error: Error) => void;

export interface ITransport {
  /**
   * Open connection to the remote endpoint
   */
  connect(url: string): Promise<void>;

  /**
   * Gracefully close connection with optional code and reason
   */
  disconnect(code?: number, reason?: string): Promise<void>;

  /**
   * Send binary data frame over wire
   */
  send(data: Buffer): Promise<void>;

  /**
   * Register message handler
   */
  onMessage(callback: MessageCallback): void;

  /**
   * Register connection closed handler
   */
  onClose(callback: CloseCallback): void;

  /**
   * Register error handler
   */
  onError(callback: ErrorCallback): void;

  /**
   * Current connection status
   */
  readonly isConnected: boolean;
}
