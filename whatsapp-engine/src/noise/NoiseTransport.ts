import { ITransport } from '../transport/ITransport.js';
import { CipherState } from './CipherState.js';

export type FrameCallback = (plaintext: Buffer) => void;

export class NoiseTransport {
  private readCipher: CipherState | null = null;
  private writeCipher: CipherState | null = null;
  private frameCallbacks: FrameCallback[] = [];
  private rxBuffer: Buffer = Buffer.alloc(0);

  constructor(private readonly transport: ITransport) {
    this.transport.onMessage((chunk: Buffer) => {
      this.handleIncomingData(chunk);
    });
  }

  public setCiphers(readCipher: CipherState, writeCipher: CipherState): void {
    this.readCipher = readCipher;
    this.writeCipher = writeCipher;
  }

  public onFrame(callback: FrameCallback): void {
    this.frameCallbacks.push(callback);
  }

  /**
   * Encrypt and send a plaintext frame over the transport
   */
  public async sendFrame(plaintext: Buffer): Promise<void> {
    if (!this.writeCipher) {
      throw new Error('NoiseTransport: write cipher not initialized');
    }

    const ciphertext = this.writeCipher.encryptWithAd(Buffer.alloc(0), plaintext);
    const frameLength = ciphertext.length;

    // 3-byte big endian header
    const header = Buffer.alloc(3);
    header[0] = (frameLength >> 16) & 0xff;
    header[1] = (frameLength >> 8) & 0xff;
    header[2] = frameLength & 0xff;

    const packet = Buffer.concat([header, ciphertext]);
    await this.transport.send(packet);
  }

  /**
   * Send raw binary data directly (used during prologue / handshake)
   */
  public async sendRaw(data: Buffer): Promise<void> {
    await this.transport.send(data);
  }

  private handleIncomingData(chunk: Buffer): void {
    this.rxBuffer = Buffer.concat([this.rxBuffer, chunk]);

    while (this.rxBuffer.length >= 3) {
      const frameLength =
        ((this.rxBuffer[0] ?? 0) << 16) |
        ((this.rxBuffer[1] ?? 0) << 8) |
        (this.rxBuffer[2] ?? 0);

      const totalPacketLength = 3 + frameLength;
      if (this.rxBuffer.length < totalPacketLength) {
        // Wait for more data
        break;
      }

      const frameData = this.rxBuffer.subarray(3, totalPacketLength);
      this.rxBuffer = this.rxBuffer.subarray(totalPacketLength);

      if (this.readCipher) {
        try {
          const plaintext = this.readCipher.decryptWithAd(Buffer.alloc(0), frameData);
          for (const cb of this.frameCallbacks) {
            cb(plaintext);
          }
        } catch (err) {
          throw new Error(`NoiseTransport frame decryption failed: ${err instanceof Error ? err.message : String(err)}`);
        }
      } else {
        // Pre-handshake raw frame
        for (const cb of this.frameCallbacks) {
          cb(frameData);
        }
      }
    }
  }
}
