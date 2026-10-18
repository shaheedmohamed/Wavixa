import { EventEmitter } from 'events';

export type EngineEvent =
  | 'session.connected'
  | 'session.disconnected'
  | 'session.ready'
  | 'qr.generated'
  | 'session.error'
  | 'message.received'
  | 'message.reaction'
  | 'message.sent'
  | 'message.failed'
  | 'message.status'
  | 'message.media'
  | 'message.revoked'
  | 'chatstate'
  | 'sync.started'
  | 'sync.completed'
  | 'history.sync'
  | 'device.logged_out';

export interface WebhookEventPayload {
  event: EngineEvent;
  session_id: string;
  message_id?: string;
  from?: string;
  to?: string;
  timestamp: number;
  data: Record<string, any>;
}

export type EventHandler = (payload: WebhookEventPayload) => void | Promise<void>;

export class EventBus {
  private static instance: EventBus | null = null;
  private emitter: EventEmitter = new EventEmitter();

  private constructor() {
    this.emitter.setMaxListeners(100);
  }

  public static getInstance(): EventBus {
    if (!EventBus.instance) {
      EventBus.instance = new EventBus();
    }
    return EventBus.instance;
  }

  public emit(event: EngineEvent, payload: WebhookEventPayload): void {
    this.emitter.emit(event, payload);
    this.emitter.emit('*', payload);
  }

  public on(event: EngineEvent | '*', handler: EventHandler): void {
    this.emitter.on(event, handler);
  }

  public off(event: EngineEvent | '*', handler: EventHandler): void {
    this.emitter.off(event, handler);
  }
}
