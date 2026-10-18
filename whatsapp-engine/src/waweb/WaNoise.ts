import crypto from 'crypto';
import { BinaryDecoder } from '../protocol/binary/Decoder.js';
import { BinaryNode } from '../protocol/binary/BinaryNode.js';
import { Curve25519 } from '../crypto/Curve25519.js';
import { Signature } from '../crypto/Signature.js';
import { WaProto } from '../protocol/proto/WaProto.js';
import { NOISE_MODE, NOISE_WA_HEADER, WA_CERT_DETAILS } from './WaConstants.js';
import type { IKeyPair } from '../protocol/proto/WaProto.js';

const IV_LENGTH = 12;
const EMPTY_BUFFER = Buffer.alloc(0);

const generateIV = (counter: number): Uint8Array => {
  const iv = new ArrayBuffer(IV_LENGTH);
  new DataView(iv).setUint32(8, counter);
  return new Uint8Array(iv);
};

const sha256 = (b: Buffer): Buffer => crypto.createHash('sha256').update(b).digest();

const aesEncryptGCM = (
  plaintext: Uint8Array,
  key: Uint8Array,
  iv: Uint8Array,
  aad: Uint8Array,
): Buffer => {
  const cipher = crypto.createCipheriv('aes-256-gcm', key, iv);
  cipher.setAAD(aad);
  return Buffer.concat([cipher.update(plaintext), cipher.final(), cipher.getAuthTag()]);
};

const aesDecryptGCM = (
  ciphertext: Uint8Array,
  key: Uint8Array,
  iv: Uint8Array,
  aad: Uint8Array,
): Buffer => {
  const decipher = crypto.createDecipheriv('aes-256-gcm', key, iv);
  decipher.setAAD(aad);
  const enc = ciphertext.subarray(0, ciphertext.length - 16);
  const tag = ciphertext.subarray(ciphertext.length - 16);
  decipher.setAuthTag(tag);
  return Buffer.concat([decipher.update(enc), decipher.final()]);
};

const hkdf = (ikm: Uint8Array, length: number, salt: Uint8Array): Buffer =>
  Buffer.from(crypto.hkdfSync('sha256', ikm, salt, Buffer.alloc(0), length));

class TransportState {
  private readCounter = 0;
  private writeCounter = 0;
  private readonly iv = new Uint8Array(IV_LENGTH);

  constructor(
    private readonly encKey: Uint8Array,
    private readonly decKey: Uint8Array,
  ) {}

  encrypt(plaintext: Uint8Array): Buffer {
    const c = this.writeCounter++;
    this.iv[8] = (c >>> 24) & 0xff;
    this.iv[9] = (c >>> 16) & 0xff;
    this.iv[10] = (c >>> 8) & 0xff;
    this.iv[11] = c & 0xff;
    return aesEncryptGCM(plaintext, this.encKey, this.iv, EMPTY_BUFFER);
  }

  decrypt(ciphertext: Uint8Array): Buffer {
    const c = this.readCounter++;
    this.iv[8] = (c >>> 24) & 0xff;
    this.iv[9] = (c >>> 16) & 0xff;
    this.iv[10] = (c >>> 8) & 0xff;
    this.iv[11] = c & 0xff;
    return aesDecryptGCM(ciphertext, this.decKey, this.iv, EMPTY_BUFFER);
  }
}

/**
 * Byte-exact implementation of the WhatsApp Web Noise_XX handshake and
 * transport cipher, matching the live wire protocol:
 *
 *   -> e                                   (HandshakeMessage.clientHello.ephemeral)
 *   <- e, ee, s(encrypted), es, certChain  (HandshakeMessage.serverHello)
 *   -> s(encrypted), se, clientPayload     (HandshakeMessage.clientFinish)
 */
export class WaNoise {
  private hash: Buffer;
  private salt: Uint8Array;
  private encKey: Uint8Array;
  private decKey: Uint8Array;
  private counter = 0;
  private sentIntro = false;

  private inBytes: Buffer = Buffer.alloc(0);
  private transport: TransportState | null = null;
  private isWaitingForTransport = false;
  private pendingOnFrame: ((buff: Uint8Array | BinaryNode) => void) | null = null;

  private readonly decoder = new BinaryDecoder();

  constructor(private readonly ephemeralKeyPair: IKeyPair) {
    const data = Buffer.from(NOISE_MODE);
    this.hash = data.byteLength === 32 ? data : sha256(data);
    this.salt = this.hash;
    this.encKey = this.hash;
    this.decKey = this.hash;

    this.authenticate(NOISE_WA_HEADER);
    this.authenticate(ephemeralKeyPair.public);
  }

  private authenticate(data: Uint8Array): void {
    if (!this.transport) {
      this.hash = sha256(Buffer.concat([this.hash, Buffer.from(data)]));
    }
  }

  encrypt(plaintext: Uint8Array): Buffer {
    if (this.transport) {
      return this.transport.encrypt(plaintext);
    }
    const result = aesEncryptGCM(plaintext, this.encKey, generateIV(this.counter++), this.hash);
    this.authenticate(result);
    return result;
  }

  private decrypt(ciphertext: Uint8Array): Buffer {
    if (this.transport) {
      return this.transport.decrypt(ciphertext);
    }
    const result = aesDecryptGCM(ciphertext, this.decKey, generateIV(this.counter++), this.hash);
    this.authenticate(ciphertext);
    return result;
  }

  private localHKDF(data: Uint8Array): [Buffer, Buffer] {
    const key = hkdf(data, 64, this.salt);
    return [key.subarray(0, 32), key.subarray(32)];
  }

  private mixIntoKey(data: Uint8Array): void {
    const [write, read] = this.localHKDF(data);
    this.salt = write;
    this.encKey = read;
    this.decKey = read;
    this.counter = 0;
  }

  finishInit(): void {
    this.isWaitingForTransport = true;
    const [write, read] = this.localHKDF(new Uint8Array(0));
    this.transport = new TransportState(write, read);
    this.isWaitingForTransport = false;

    if (this.pendingOnFrame) {
      const cb = this.pendingOnFrame;
      this.pendingOnFrame = null;
      this.processData(cb);
    }
  }

  /**
   * Process the ServerHello handshake message:
   * validates the noise certificate chain and produces the encrypted
   * static key for ClientFinish.
   */
  async processHandshake(serverHello: {
    ephemeral?: Buffer;
    static?: Buffer;
    payload?: Buffer;
  }, noiseKey: IKeyPair): Promise<Buffer> {
    if (!serverHello.ephemeral || !serverHello.static || !serverHello.payload) {
      throw new Error('Invalid ServerHello: missing fields');
    }

    this.authenticate(serverHello.ephemeral);
    this.mixIntoKey(
      Curve25519.sharedKey(this.ephemeralKeyPair.private, serverHello.ephemeral),
    );

    const decStaticContent = this.decrypt(serverHello.static);
    this.mixIntoKey(
      Curve25519.sharedKey(this.ephemeralKeyPair.private, decStaticContent),
    );

    const certDecoded = this.decrypt(serverHello.payload);
    const { intermediate: certIntermediate, leaf } = await WaProto.decodeCertChain(certDecoded);

    if (!leaf?.details || !leaf?.signature) {
      throw new Error('invalid noise leaf certificate');
    }
    if (!certIntermediate?.details || !certIntermediate?.signature) {
      throw new Error('invalid noise intermediate certificate');
    }

    const details = await WaProto.decodeCertDetails(certIntermediate.details);

    const verify = Signature.verify(details.key, leaf.details, leaf.signature);
    const verifyIntermediate = Signature.verify(
      WA_CERT_DETAILS.PUBLIC_KEY,
      certIntermediate.details,
      certIntermediate.signature,
    );

    if (!verify) {
      throw new Error('noise certificate signature invalid');
    }
    if (!verifyIntermediate) {
      throw new Error('noise intermediate certificate signature invalid');
    }
    if (details.issuerSerial !== WA_CERT_DETAILS.SERIAL) {
      throw new Error('certification match failed');
    }

    const keyEnc = this.encrypt(noiseKey.public);
    this.mixIntoKey(Curve25519.sharedKey(noiseKey.private, serverHello.ephemeral));

    return keyEnc;
  }

  /**
   * Encode a raw payload into a wire frame. The first frame is prefixed
   * with the intro header (WA\x06\x03). Post-handshake payloads are
   * encrypted with the transport cipher.
   */
  encodeFrame(data: Buffer | Uint8Array): Buffer {
    let payload: Buffer | Uint8Array = data;
    if (this.transport) {
      payload = this.transport.encrypt(payload);
    }

    const dataLen = payload.byteLength;
    const introSize = this.sentIntro ? 0 : NOISE_WA_HEADER.length;
    const frame = Buffer.allocUnsafe(introSize + 3 + dataLen);

    if (!this.sentIntro) {
      frame.set(NOISE_WA_HEADER, 0);
      this.sentIntro = true;
    }

    frame[introSize] = (dataLen >>> 16) & 0xff;
    frame[introSize + 1] = (dataLen >>> 8) & 0xff;
    frame[introSize + 2] = dataLen & 0xff;
    frame.set(payload, introSize + 3);

    return frame;
  }

  /**
   * Feed incoming bytes; emits raw handshake frames pre-transport and
   * decoded BinaryNodes post-transport.
   */
  decodeFrame(newData: Buffer | Uint8Array, onFrame: (buff: Uint8Array | BinaryNode) => void): void {
    if (this.isWaitingForTransport) {
      this.inBytes = Buffer.concat([this.inBytes, Buffer.from(newData)]);
      this.pendingOnFrame = onFrame;
      return;
    }

    this.inBytes = this.inBytes.length === 0
      ? Buffer.from(newData)
      : Buffer.concat([this.inBytes, Buffer.from(newData)]);

    this.processData(onFrame);
  }

  private processData(onFrame: (buff: Uint8Array | BinaryNode) => void): void {
    while (true) {
      if (this.inBytes.length < 3) return;
      const size = (this.inBytes[0]! << 16) | (this.inBytes[1]! << 8) | this.inBytes[2]!;
      if (this.inBytes.length < size + 3) return;

      let frame: Uint8Array | BinaryNode = this.inBytes.subarray(3, size + 3);
      this.inBytes = this.inBytes.subarray(size + 3);

      if (this.transport) {
        const result = this.transport.decrypt(frame);
        frame = this.decoder.decode(result);
      }

      onFrame(frame);
    }
  }
}
