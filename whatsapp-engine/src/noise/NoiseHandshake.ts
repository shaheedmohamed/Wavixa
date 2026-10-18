import crypto from 'crypto';
import { CipherState } from './CipherState.js';
import { Curve25519, KeyPair } from '../crypto/Curve25519.js';

export interface HandshakeResult {
  readCipher: CipherState;
  writeCipher: CipherState;
  remoteStaticPublicKey?: Buffer;
}

export class NoiseHandshake {
  private static readonly PROTOCOL_NAME = Buffer.from('Noise_XX_25519_AESGCM_SHA256\0\0\0\0');

  private h: Buffer;
  private ck: Buffer;
  private cipherState: CipherState;

  private localEphemeralKey: KeyPair;
  private remoteEphemeralKey: Buffer | null = null;
  private remoteStaticKey: Buffer | null = null;

  constructor(
    private readonly localStaticKey: KeyPair,
    prologue: Buffer = Buffer.from('WA\x06\x02', 'latin1'),
  ) {
    this.h = Buffer.alloc(32);
    NoiseHandshake.PROTOCOL_NAME.copy(this.h, 0, 0, 32);
    this.ck = Buffer.from(this.h);
    this.cipherState = new CipherState();
    this.localEphemeralKey = Curve25519.generateKeyPair();

    if (prologue.length > 0) {
      this.mixHash(prologue);
    }
  }

  public getEphemeralPublicKey(): Buffer {
    return this.localEphemeralKey.publicKey;
  }

  public getHash(): Buffer {
    return Buffer.from(this.h);
  }

  /**
   * Step 1 (Initiator): -> e
   * Produces the initial message containing the ephemeral public key (32 bytes)
   */
  public startInitiator(): Buffer {
    const e = this.localEphemeralKey.publicKey;
    this.mixHash(e);
    return e;
  }

  /**
   * Step 2 (Initiator): <- e, ee, s, es
   * Processes the responder message:
   * - 32 bytes responder ephemeral public key
   * - 48 bytes encrypted responder static public key (32 bytes key + 16 bytes tag)
   * - optional encrypted payload
   */
  public processResponderMessage(responderMessage: Buffer): Buffer {
    if (responderMessage.length < 32 + 48) {
      throw new Error(`Responder message too short: ${responderMessage.length} bytes (expected >= 80)`);
    }

    // 1. Read e
    const re = responderMessage.subarray(0, 32);
    this.remoteEphemeralKey = re;
    this.mixHash(re);

    // 2. Perform ee
    const ee = Curve25519.sharedKey(this.localEphemeralKey.privateKey, re);
    this.mixKey(ee);

    // 3. Read and decrypt encrypted static key s (32 bytes key + 16 bytes tag)
    const encryptedStatic = responderMessage.subarray(32, 80);
    const remoteStatic = this.cipherState.decryptWithAd(this.h, encryptedStatic);
    this.remoteStaticKey = remoteStatic;
    this.mixHash(encryptedStatic);

    // 4. Perform es
    const es = Curve25519.sharedKey(this.localEphemeralKey.privateKey, remoteStatic);
    this.mixKey(es);

    // 5. Decrypt remaining payload if any
    if (responderMessage.length > 80) {
      const encryptedPayload = responderMessage.subarray(80);
      const payload = this.cipherState.decryptWithAd(this.h, encryptedPayload);
      this.mixHash(encryptedPayload);
      return payload;
    }

    return Buffer.alloc(0);
  }

  /**
   * Step 3 (Initiator): -> s, se, payload
   * Produces final handshake message:
   * - encrypted local static identity key
   * - DH(se)
   * - encrypted payload (e.g. client registration protobuf)
   */
  public finishInitiator(payload: Buffer = Buffer.alloc(0)): {
    message: Buffer;
    result: HandshakeResult;
  } {
    if (!this.remoteEphemeralKey) {
      throw new Error('Cannot finish handshake: missing remote ephemeral key');
    }

    // 1. Encrypt static identity key s
    const encryptedStatic = this.cipherState.encryptWithAd(this.h, this.localStaticKey.publicKey);
    this.mixHash(encryptedStatic);

    // 2. Perform se
    const se = Curve25519.sharedKey(this.localStaticKey.privateKey, this.remoteEphemeralKey);
    this.mixKey(se);

    // 3. Encrypt payload
    const encryptedPayload = this.cipherState.encryptWithAd(this.h, payload);
    this.mixHash(encryptedPayload);

    const message = Buffer.concat([encryptedStatic, encryptedPayload]);

    // 4. Split into transport read & write ciphers
    const [writeKey, readKey] = this.split();

    const writeCipher = new CipherState(writeKey);
    const readCipher = new CipherState(readKey);

    return {
      message,
      result: {
        readCipher,
        writeCipher,
        remoteStaticPublicKey: this.remoteStaticKey || undefined,
      },
    };
  }

  private mixHash(data: Buffer): void {
    const hash = crypto.createHash('sha256');
    hash.update(this.h);
    hash.update(data);
    this.h = hash.digest();
  }

  private mixKey(data: Buffer): void {
    const derived = Buffer.from(
      crypto.hkdfSync('sha256', data, this.ck, Buffer.alloc(0), 64),
    );
    this.ck = derived.subarray(0, 32);
    const tempKey = derived.subarray(32, 64);
    this.cipherState.initializeKey(tempKey);
  }

  private split(): [Buffer, Buffer] {
    const derived = Buffer.from(
      crypto.hkdfSync('sha256', Buffer.alloc(0), this.ck, Buffer.alloc(0), 64),
    );
    const k1 = derived.subarray(0, 32);
    const k2 = derived.subarray(32, 64);
    return [k1, k2];
  }
}
