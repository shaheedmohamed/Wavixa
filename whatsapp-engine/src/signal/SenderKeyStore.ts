import type { WaCreds } from '../waweb/WaCreds.js';

export interface SenderKeyStateData {
  keyId: number;
  iteration: number;
  chainKey: string; // base64
  signingKey: string; // base64 ed25519 public (32B)
  messageKeys: Record<string, string>; // iteration -> base64 seed
}

/**
 * Sender-key (group/broadcast) store — the counterpart of SignalStore for
 * `skmsg` payloads. WhatsApp uses the Signal *sender keys* protocol for
 * @g.us groups and status@broadcast stories: the author distributes a
 * SenderKeyDistributionMessage to each recipient device over a pairwise
 * msg/pkmsg, then encrypts the actual content once with a ratcheting
 * chain key. Keys are persisted into creds.signalSenderKeys so a restart
 * does not lose the ratchet state.
 */
export class SenderKeyStore {
  private keys = new Map<string, SenderKeyStateData>();
  private readonly onPersist: () => void;

  constructor(
    private readonly creds: WaCreds & { signalSenderKeys?: Record<string, SenderKeyStateData> },
    onPersist?: () => void,
  ) {
    this.onPersist = onPersist || (() => undefined);
    for (const [k, v] of Object.entries(creds.signalSenderKeys || {})) {
      this.keys.set(k, v);
    }
  }

  /** group/broadcast jid + authoring device jid, e.g. "status@broadcast::12345.4@lid" */
  public static name(groupJid: string, authorJid: string): string {
    return `${groupJid}::${authorJid}`;
  }

  public load(name: string): SenderKeyStateData | undefined {
    return this.keys.get(name);
  }

  public store(name: string, state: SenderKeyStateData): void {
    this.keys.set(name, state);
    this.creds.signalSenderKeys = this.creds.signalSenderKeys || {};
    this.creds.signalSenderKeys[name] = state;
    this.onPersist();
  }

  public remove(name: string): void {
    this.keys.delete(name);
    if (this.creds.signalSenderKeys) {
      delete this.creds.signalSenderKeys[name];
      this.onPersist();
    }
  }
}
