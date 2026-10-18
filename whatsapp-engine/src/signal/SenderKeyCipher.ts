import crypto from 'crypto';
import { WaProto } from '../protocol/proto/WaProto.js';
import { calculateMAC, deriveSecrets, signalDecrypt } from './SignalCrypto.js';
import { SenderKeyStore, type SenderKeyStateData } from './SenderKeyStore.js';

const ED25519_SPKI_PREFIX = Buffer.from('302a300506032b6570032100', 'hex');
const MAX_SKIPPED_KEYS = 250;

/**
 * Sender-key signatures are XEdDSA: the SKDM signingKey is a Montgomery
 * (Curve25519) u-coordinate. Ed25519 verification requires the Edwards-form
 * point: y = (u - 1) / (u + 1) mod p, sign bit of x = 0.
 */
const FIELD_P = (1n << 255n) - 19n;

const modPow = (base: bigint, exp: bigint): bigint => {
  let r = 1n;
  let b = base % FIELD_P;
  let e = exp;
  while (e > 0n) {
    if (e & 1n) r = (r * b) % FIELD_P;
    b = (b * b) % FIELD_P;
    e >>= 1n;
  }
  return r;
};

const montgomeryToEdwards = (uBytes: Buffer): Buffer => {
  const u = BigInt(`0x${Buffer.from(uBytes).reverse().toString('hex')}`);
  const y = (((u - 1n) * modPow(u + 1n, FIELD_P - 2n)) % FIELD_P + FIELD_P) % FIELD_P;
  const out = Buffer.from(y.toString(16).padStart(64, '0'), 'hex').reverse();
  out[31]! &= 0x7f; // x sign bit = 0 (XEdDSA "positive" point)
  return out;
};

/**
 * WhatsApp sender-key ("skmsg") decryption — used for group messages and
 * status@broadcast stories. Wire format per libsignal's group protocol:
 *
 *   skmsg   = versionByte | protobuf(SenderKeyMessage{id,iteration,ciphertext}) | 64B ed25519 sig
 *   SKDM    = protobuf(SenderKeyDistributionMessage{id,iteration,chainKey,signingKey})
 *             wrapped in Message.senderKeyDistributionMessage{groupId, axolotl…}
 *
 * Ratchet: messageKey = HMAC-SHA256(chainKey, 0x01); next chainKey =
 * HMAC-SHA256(chainKey, 0x02). Content keys = deriveSecrets(messageKey,
 * salt=32 zero bytes, info="WhisperGroup") -> aesKey=keys[0], iv=keys[2][:16].
 */
export class SenderKeyCipher {
  constructor(private readonly store: SenderKeyStore) {}

  /** Store a key distributed to us by `authorJid` (full device JID). */
  public async processDistribution(
    groupJid: string,
    authorJid: string,
    axolotl: Buffer,
  ): Promise<void> {
    // The blob is libsignal-serialized: versionByte(0x33) || protobuf(SKDM).
    // Older payloads may lack the prefix — try the raw proto first, then
    // with the version byte stripped.
    let skdm: any;
    try {
      skdm = await WaProto.decodeSenderKeyDistributionMessage(axolotl);
      if (!skdm?.chainKey || !skdm?.signingKey) throw new Error('incomplete');
    } catch {
      skdm = await WaProto.decodeSenderKeyDistributionMessage(axolotl.subarray(1));
    }
    if (!skdm?.chainKey || !skdm?.signingKey) return;
    this.store.store(SenderKeyStore.name(groupJid, authorJid), {
      keyId: Number(skdm.id ?? 0),
      iteration: Number(skdm.iteration ?? 0),
      chainKey: Buffer.from(skdm.chainKey).toString('base64'),
      signingKey: Buffer.from(skdm.signingKey).toString('base64'),
      messageKeys: {},
    });
  }

  public async decrypt(buf: Buffer, groupJid: string, authorJid: string): Promise<Buffer> {
    if (!buf || buf.length < 65) {
      throw new Error('skmsg too short');
    }
    const serialized = buf.subarray(0, buf.length - 64);
    const signature = buf.subarray(buf.length - 64);
    const msg = await WaProto.decodeSenderKeyMessage(serialized.subarray(1));
    const name = SenderKeyStore.name(groupJid, authorJid);
    const state = this.store.load(name);
    if (!state || state.keyId !== Number(msg.id)) {
      throw new Error(`No sender key for ${name} (want id ${msg.id})`);
    }

    this.verifySignature(state, serialized, signature);
    const seed = this.messageKeyFor(state, Number(msg.iteration));
    const keys = deriveSecrets(seed, Buffer.alloc(32), Buffer.from('WhisperGroup'));
    const plain = signalDecrypt(keys[0]!, Buffer.from(msg.ciphertext), keys[2]!.subarray(0, 16));
    this.store.store(name, state);
    return plain;
  }

  private verifySignature(state: SenderKeyStateData, serialized: Buffer, signature: Buffer): void {
    try {
      let rawPub = Buffer.from(state.signingKey, 'base64');
      if (rawPub.length === 33 && rawPub[0] === 5) rawPub = rawPub.subarray(1); // curve key-type prefix
      const edwards = montgomeryToEdwards(rawPub.subarray(0, 32));
      // XEdDSA recovers the Edwards x via a negate-variant in some builds —
      // the compressed point's sign bit is therefore ambiguous. Verify with
      // both conventions and accept either.
      const ok = [0x00, 0x80].some((bit) => {
        const pt = Buffer.from(edwards);
        pt[31] = (pt[31]! & 0x7f) | bit;
        const der = Buffer.concat([ED25519_SPKI_PREFIX, pt]);
        const keyObj = crypto.createPublicKey({ key: der, format: 'der', type: 'spki' });
        return crypto.verify(null, serialized, keyObj, signature);
      });
      if (!ok) {
        throw new Error('bad sender-key signature');
      }
    } catch (e) {
      throw new Error(`sender-key signature check failed: ${e instanceof Error ? e.message : e}`);
    }
  }

  /**
   * Advance the ratchet to `iteration`, stashing intermediate message keys
   * so out-of-order delivery still decrypts. Returns the message key seed.
   */
  private messageKeyFor(state: SenderKeyStateData, iteration: number): Buffer {
    const stash = state.messageKeys;
    if (iteration < state.iteration) {
      const stashed = stash[String(iteration)];
      if (!stashed) {
        throw new Error(`skmsg iteration ${iteration} already consumed (at ${state.iteration})`);
      }
      delete stash[String(iteration)];
      return Buffer.from(stashed, 'base64');
    }

    let chainKey: Buffer = Buffer.from(state.chainKey, 'base64');
    let msgKey: Buffer | undefined;
    while (state.iteration <= iteration) {
      msgKey = calculateMAC(chainKey, Buffer.from([0x01]));
      if (state.iteration < iteration) {
        stash[String(state.iteration)] = msgKey.toString('base64');
      }
      chainKey = calculateMAC(chainKey, Buffer.from([0x02]));
      state.iteration++;
    }
    state.chainKey = chainKey.toString('base64');

    const stashKeys = Object.keys(stash);
    if (stashKeys.length > MAX_SKIPPED_KEYS) {
      stashKeys.sort((a, b) => +a - +b);
      for (const k of stashKeys.slice(0, stashKeys.length - MAX_SKIPPED_KEYS)) {
        delete stash[k];
      }
    }
    return msgKey!;
  }
}
