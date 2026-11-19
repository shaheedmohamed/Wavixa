import crypto from 'crypto';
import { logger } from '../config/Logger.js';
import { WaProto } from '../protocol/proto/WaProto.js';
import { calculateMAC, deriveSecrets, signalDecrypt, signalEncrypt } from './SignalCrypto.js';
import { SenderKeyStore, type SenderKeyStateData } from './SenderKeyStore.js';
import { Ed25519 } from '../crypto/Ed25519.js';
import { Signature } from '../crypto/Signature.js';

const ED25519_SPKI_PREFIX = Buffer.from('302a300506032b6570032100', 'hex');
const MAX_SKIPPED_KEYS = 250;

/**
 * Sender-key signatures are XEdDSA verified directly against the SKDM
 * signingKey's raw Montgomery (Curve25519) bytes via curve25519_sign_open.
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

/** Edwards→Montgomery: u = (1+y)/(1-y). Used to publish our Ed25519
 * sender-signing key in the Curve25519 form SKDM recipients expect —
 * the shared scalar makes the same XEdDSA identity valid on both curves. */
const edwardsToMontgomery = (edPub: Buffer): Buffer => {
  const y = BigInt(`0x${Buffer.from(edPub).reverse().toString('hex')}`) & ((1n << 255n) - 1n);
  const u = (((1n + y) * modPow(((1n - y) % FIELD_P + FIELD_P) % FIELD_P, FIELD_P - 2n)) % FIELD_P + FIELD_P) % FIELD_P;
  return Buffer.from(u.toString(16).padStart(64, '0'), 'hex').reverse();
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
 * salt=32 zero bytes, info="WhisperGroup") -> cipherKey = keys[0][16:] ||
 * keys[1][:16], iv = keys[0][:16] (libsignal's spliced layout).
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
    const requestedIter = Number(msg.iteration);
    let seed: Buffer | undefined;
    try {
      seed = this.messageKeyFor(state, requestedIter);
      const keys = deriveSecrets(seed!, Buffer.alloc(32), Buffer.from('WhisperGroup'));
      // libsignal SenderMessageKey splices the material: cipherKey is the
      // LAST 16 bytes of chunk 0 joined with the FIRST 16 of chunk 1, and
      // the IV is the first 16 of chunk 0. Using whole chunks (a very
      // natural reading of HKDF output) produces a key+iv that verifies
      // the signature but can never decrypt — the exact interop break
      // seen as "Waiting for this message" / bad decrypt both ways.
      const cipherKey = Buffer.concat([keys[0]!.subarray(16), keys[1]!.subarray(0, 16)]);
      const iv = keys[0]!.subarray(0, 16);
      const plain = signalDecrypt(cipherKey, Buffer.from(msg.ciphertext), iv);
      this.store.store(name, state);
      return plain;
    } catch (e) {
      if (process.env.ENGINE_DEBUG_STANZAS === '1') {
        logger.info(
          {
            name,
            keyId: state.keyId,
            msgIter: requestedIter,
            stateIter: state.iteration,
            chainKey: state.chainKey,
            seed: seed?.toString('base64'),
            wire: buf.toString('base64'),
          },
          'skmsg decrypt failed dump',
        );
      }
      throw new Error(
        `${e instanceof Error ? e.message : e} (keyId=${state.keyId} msgIter=${requestedIter} stateIter=${state.iteration})`,
      );
    }
  }

  /**
   * Outgoing group encryption — the inverse of `decrypt`. Creates (or
   * ratchets) our sender key for `groupJid` under `authorJid`, produces:
   *  - ciphertext: versionByte(0x33) || proto(SenderKeyMessage) || 64B XEdDSA sig
   *  - skdm: the frozen SenderKeyDistributionMessage blob (iteration-0 chain
   *    key) to fan out to member devices that don't have our key yet.
   */
  public async encryptForGroup(
    groupJid: string,
    authorJid: string,
    plaintext: Buffer,
  ): Promise<{ ciphertext: Buffer; skdm: Buffer; state: SenderKeyStateData; isNew: boolean }> {
    const name = SenderKeyStore.name(groupJid, authorJid);
    let state = this.store.load(name);
    // v=4 marks even-x Ed25519 signing keys. The SKDM publishes only the
    // Montgomery u-coordinate; recipients rebuild the Edwards point with
    // x-sign = 0. If our point has ODD x, they reconstruct -P and every
    // skmsg signature fails — the exact "Waiting for this message" break.
    const isNew = !state || state.v !== 4;

    if (!state || state.v !== 4) {
      // The sender signing key is an Ed25519 pair — recipients verify
      // skmsg sigs under the Edwards point, so the private must be a real
      // Ed25519 seed. The SKDM publishes the Montgomery form of the same
      // key (u = (1+y)/(1-y)) — the format the protocol carries.
      let signing = Ed25519.generateKeyPair();
      // Resample until the Edwards x-parity is even — the only form the
      // Montgomery conversion preserves. (~50% odds per draw.)
      while (signing.publicKey[31]! & 0x80) {
        signing = Ed25519.generateKeyPair();
      }
      const signingPubMont = edwardsToMontgomery(signing.publicKey);
      const chainKey = crypto.randomBytes(32);
      const keyId = (crypto.randomBytes(4).readUInt32BE(0) & 0x7fffffff) || 1;
      state = {
        v: 4,
        keyId,
        iteration: 0,
        chainKey: chainKey.toString('base64'),
        signingKey: signingPubMont.toString('base64'),
        signingPrivateKey: signing.privateKey.toString('base64'),
        messageKeys: {},
        distributedTo: {},
        // The SKDM is frozen at creation — every member device must start
        // from the SAME iteration-0 chain key and ratchet forward to catch
        // up, so the original blob is kept for redistribution to new
        // devices/members later.
        skdm: Buffer.concat([
          Buffer.from([0x33]),
          await WaProto.encodeSenderKeyDistributionMessage({
            id: keyId,
            iteration: 0,
            chainKey,
            // Curve key-type byte 0x05 — libsignal serializes IdentityKeys
            // with the type prefix; bare 32B keys fail recipient parsing.
            signingKey: Buffer.concat([Buffer.from([5]), signingPubMont]),
          }),
        ]).toString('base64'),
      };
    }

    // One ratchet step per message: msgKey = HMAC(chain,0x01),
    // next chain = HMAC(chain,0x02) — same scheme decrypt() walks back.
    const chainKey = Buffer.from(state.chainKey, 'base64');
    const msgKey = calculateMAC(chainKey, Buffer.from([0x01]));
    const iteration = state.iteration;
    state.chainKey = calculateMAC(chainKey, Buffer.from([0x02])).toString('base64');
    state.iteration = iteration + 1;

    const secrets = deriveSecrets(msgKey, Buffer.alloc(32), Buffer.from('WhisperGroup'));
    // Same splice as decrypt(): cipherKey = chunk0[16:] || chunk1[:16],
    // iv = chunk0[:16] — this is libsignal's exact SenderMessageKey layout.
    const cipherKey = Buffer.concat([secrets[0]!.subarray(16), secrets[1]!.subarray(0, 16)]);
    const iv = secrets[0]!.subarray(0, 16);
    const inner = signalEncrypt(cipherKey, plaintext, iv);
    const skm = await WaProto.encodeSenderKeyMessage({
      id: state.keyId,
      iteration,
      ciphertext: inner,
    });
    const wire = Buffer.concat([Buffer.from([0x33]), skm]);
    const signature = Ed25519.sign(
      Buffer.from(state.signingPrivateKey!, 'base64'),
      wire,
    );
    const ciphertext = Buffer.concat([wire, signature]);

    this.store.store(name, state);
    return { ciphertext, skdm: Buffer.from(state.skdm!, 'base64'), state, isNew };
  }

  private verifySignature(state: SenderKeyStateData, serialized: Buffer, signature: Buffer): void {
    let rawPub = Buffer.from(state.signingKey, 'base64');
    if (rawPub.length === 33 && rawPub[0] === 5) rawPub = rawPub.subarray(1); // curve key-type prefix
    // XEdDSA verification is done directly on the Montgomery public key —
    // converting it to an Edwards point by hand changes the sign-bit
    // semantics and rejects every real signature (verified against captured
    // LID-era traffic: curve25519_sign_open passes where all manual
    // conversions failed). Keep the raw Ed25519 read only as a fallback
    // for senders that distribute an Edwards point directly.
    if (rawPub.length === 32 && Signature.verify(rawPub, serialized, signature)) {
      return;
    }
    try {
      const der = Buffer.concat([ED25519_SPKI_PREFIX, rawPub.subarray(0, 32)]);
      const keyObj = crypto.createPublicKey({ key: der, format: 'der', type: 'spki' });
      if (crypto.verify(null, serialized, keyObj, signature)) return;
    } catch { /* fall through to the hard error below */ }
    throw new Error('sender-key signature check failed: bad sender-key signature');
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
