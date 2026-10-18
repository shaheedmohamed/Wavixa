/**
 * Wavixa WhatsApp Engine - Multi-Device Session State Machine
 */

export type SessionState =
  | 'DISCONNECTED'
  | 'CONNECTING'
  | 'AUTHENTICATING'
  | 'CONNECTED'
  | 'SYNCING'
  | 'READY'
  | 'RECONNECTING'
  | 'LOGGED_OUT'
  | 'ERROR';

export type StateChangeCallback = (newState: SessionState, previousState: SessionState) => void;

export class SessionStateMachine {
  private currentState: SessionState = 'DISCONNECTED';
  private listeners: StateChangeCallback[] = [];

  private static readonly VALID_TRANSITIONS: Record<SessionState, SessionState[]> = {
    DISCONNECTED: ['CONNECTING', 'ERROR'],
    CONNECTING: ['AUTHENTICATING', 'CONNECTED', 'RECONNECTING', 'ERROR', 'DISCONNECTED'],
    AUTHENTICATING: ['CONNECTING', 'AUTHENTICATING', 'CONNECTED', 'LOGGED_OUT', 'RECONNECTING', 'ERROR', 'DISCONNECTED'],
    CONNECTED: ['SYNCING', 'READY', 'RECONNECTING', 'LOGGED_OUT', 'ERROR', 'DISCONNECTED'],
    SYNCING: ['READY', 'RECONNECTING', 'LOGGED_OUT', 'ERROR', 'DISCONNECTED'],
    READY: ['CONNECTING', 'RECONNECTING', 'LOGGED_OUT', 'DISCONNECTED', 'ERROR'],
    RECONNECTING: ['CONNECTING', 'DISCONNECTED', 'LOGGED_OUT', 'ERROR'],
    LOGGED_OUT: ['CONNECTING', 'DISCONNECTED'],
    ERROR: ['CONNECTING', 'RECONNECTING', 'DISCONNECTED'],
  };

  constructor(initialState: SessionState = 'DISCONNECTED') {
    this.currentState = initialState;
  }

  public getState(): SessionState {
    return this.currentState;
  }

  public transition(nextState: SessionState): void {
    if (this.currentState === nextState) {
      return;
    }

    const allowed = SessionStateMachine.VALID_TRANSITIONS[this.currentState];
    if (!allowed || !allowed.includes(nextState)) {
      throw new Error(`Invalid session state transition from ${this.currentState} to ${nextState}`);
    }

    const prev = this.currentState;
    this.currentState = nextState;

    for (const listener of this.listeners) {
      try {
        listener(nextState, prev);
      } catch {
        // Prevent listener exception from aborting state machine
      }
    }
  }

  public onChange(callback: StateChangeCallback): void {
    this.listeners.push(callback);
  }

  public canTransition(nextState: SessionState): boolean {
    const allowed = SessionStateMachine.VALID_TRANSITIONS[this.currentState];
    return allowed !== undefined && allowed.includes(nextState);
  }
}
