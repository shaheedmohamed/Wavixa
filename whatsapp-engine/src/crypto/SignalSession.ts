import crypto from 'crypto';
import { Curve25519, KeyPair } from './Curve25519.js';
import { AesGcm } from './AesGcm.js';

export interface SignalCiphertext {
  type: 'signal' | 'prekey';
  ciphertext: string; // base64
  ephemeralPublicKeyHex?: string;
  counter: number;
}

export class SignalSession {
  private rootKey: Buffer;
  private sendingChainKey: Buffer;
  private receivingChainKey: Buffer;
  private localDhKeyPair: KeyPair;
  private remoteDhPublicKey: Buffer;
  private sendCounter: number = 0;
  private receiveCounter: number = 0;

  constructor(
    sharedSecret: Buffer,
    localDhKeyPair: KeyPair,
    remoteDhPublicKey: Buffer,
    isInitiator: boolean = true,
  ) {
    this.localDhKeyPair = localDhKeyPair;
    this.remoteDhPublicKey = remoteDhPublicKey;

    // Initialize root key and chain keys from initial shared secret
    const derived = Buffer.from(
      crypto.hkdfSync(
        'sha256',
        sharedSecret,
        Buffer.alloc(32),
        Buffer.from('WhisperRatchet', 'utf8'),
        96,
      ),
    );

    this.rootKey = derived.subarray(0, 32);
    if (isInitiator) {
      this.sendingChainKey = derived.subarray(32, 64);
      this.receivingChainKey = derived.subarray(64, 96);
    } else {
      this.sendingChainKey = derived.subarray(64, 96);
      this.receivingChainKey = derived.subarray(32, 64);
    }
  }

  /**
   * Encrypt plaintext message using the Double Ratchet sending chain
   */
  public encrypt(plaintext: Buffer): SignalCiphertext {
    // 1. Derive message key from current sending chain key
    const messageKey = crypto
      .createHmac('sha256', this.sendingChainKey)
      .update(Buffer.from([0x01]))
      .digest();

    // 2. Advance sending chain key
    this.sendingChainKey = crypto
      .createHmac('sha256', this.sendingChainKey)
      .update(Buffer.from([0x02]))
      .digest();

    const currentCounter = this.sendCounter;
    this.sendCounter += 1;

    // 3. Encrypt plaintext with derived message key
    const aad = Buffer.alloc(4);
    aad.writeUInt32BE(currentCounter, 0);

    const enc = AesGcm.encrypt(messageKey, plaintext, crypto.randomBytes(12), aad);
    const combined = Buffer.concat([enc.iv, enc.tag, enc.ciphertext]);

    return {
      type: 'signal',
      ciphertext: combined.toString('base64'),
      ephemeralPublicKeyHex: this.localDhKeyPair.publicKey.toString('hex'),
      counter: currentCounter,
    };
  }

  /**
   * Decrypt ciphertext message using receiving chain
   */
  public decrypt(signalMsg: SignalCiphertext): Buffer {
    const raw = Buffer.from(signalMsg.ciphertext, 'base64');
    if (raw.length < 28) {
      throw new Error(`Signal message too short: ${raw.length} bytes`);
    }

    const iv = raw.subarray(0, 12);
    const tag = raw.subarray(12, 28);
    const ciphertext = raw.subarray(28);

    // Derive message key from receiving chain key
    const messageKey = crypto
      .createHmac('sha256', this.receivingChainKey)
      .update(Buffer.from([0x01]))
      .digest();

    // Advance receiving chain key
    this.receivingChainKey = crypto
      .createHmac('sha256', this.receivingChainKey)
      .update(Buffer.from([0x02]))
      .digest();

    this.receiveCounter += 1;

    const aad = Buffer.alloc(4);
    aad.writeUInt32BE(signalMsg.counter, 0);

    return AesGcm.decrypt(messageKey, ciphertext, iv, tag, aad);
  }

  /**
   * Perform DH ratchet step upon receiving a new remote DH key
   */
  public ratchetStep(newRemoteDhPublicKey: Buffer): void {
    this.remoteDhPublicKey = newRemoteDhPublicKey;

    // 1. DH output with old local key + new remote key
    const dh1 = Curve25519.sharedKey(this.localDhKeyPair.privateKey, this.remoteDhPublicKey);
    const derived1 = Buffer.from(
      crypto.hkdfSync(
        'sha256',
        dh1,
        this.rootKey,
        Buffer.from('WhisperRatchet', 'utf8'),
        64,
      ),
    );
    this.rootKey = derived1.subarray(0, 32);
    this.receivingChainKey = derived1.subarray(32, 64);

    // 2. Generate new local DH keypair
    this.localDhKeyPair = Curve25519.generateKeyPair();

    // 3. DH output with new local key + new remote key
    const dh2 = Curve25519.sharedKey(this.localDhKeyPair.privateKey, this.remoteDhPublicKey);
    const derived2 = Buffer.from(
      crypto.hkdfSync(
        'sha256',
        dh2,
        this.rootKey,
        Buffer.from('WhisperRatchet', 'utf8'),
        64,
      ),
    );
    this.rootKey = derived2.subarray(0, 32);
    this.sendingChainKey = derived2.subarray(32, 64);
    this.sendCounter = 0;
  }
}
