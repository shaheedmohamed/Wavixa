import crypto from 'crypto';

/**
 * Noise Protocol CipherState (AES-256-GCM)
 * Handles frame encryption, decryption, and 64-bit nonce increment.
 */
export class CipherState {
  private key: Buffer | null = null;
  private nonce: bigint = 0n;

  constructor(key?: Buffer) {
    if (key) {
      this.initializeKey(key);
    }
  }

  public initializeKey(key: Buffer): void {
    if (key.length !== 32) {
      throw new Error(`CipherState key must be 32 bytes, got ${key.length}`);
    }
    this.key = Buffer.from(key);
    this.nonce = 0n;
  }

  public hasKey(): boolean {
    return this.key !== null;
  }

  public getNonce(): bigint {
    return this.nonce;
  }

  public setNonce(nonce: bigint): void {
    this.nonce = nonce;
  }

  /**
   * Encrypt plaintext with optional associated data.
   * Format: [ciphertext] + [16-byte authentication tag]
   */
  public encryptWithAd(associatedData: Buffer, plaintext: Buffer): Buffer {
    if (!this.key) {
      return Buffer.from(plaintext);
    }

    const iv = this.buildIv(this.nonce);
    this.nonce += 1n;

    const cipher = crypto.createCipheriv('aes-256-gcm', this.key, iv);
    if (associatedData.length > 0) {
      cipher.setAAD(associatedData);
    }

    const ciphertext = cipher.update(plaintext);
    cipher.final();
    const tag = cipher.getAuthTag();

    return Buffer.concat([ciphertext, tag]);
  }

  /**
   * Decrypt ciphertext with associated data.
   * Expects the last 16 bytes to be the authentication tag.
   */
  public decryptWithAd(associatedData: Buffer, ciphertextAndTag: Buffer): Buffer {
    if (!this.key) {
      return Buffer.from(ciphertextAndTag);
    }

    if (ciphertextAndTag.length < 16) {
      throw new Error(`Ciphertext too short for authentication tag (${ciphertextAndTag.length} bytes)`);
    }

    const iv = this.buildIv(this.nonce);
    this.nonce += 1n;

    const tag = ciphertextAndTag.subarray(ciphertextAndTag.length - 16);
    const ciphertext = ciphertextAndTag.subarray(0, ciphertextAndTag.length - 16);

    const decipher = crypto.createDecipheriv('aes-256-gcm', this.key, iv);
    decipher.setAuthTag(tag);
    if (associatedData.length > 0) {
      decipher.setAAD(associatedData);
    }

    const plaintext = decipher.update(ciphertext);
    decipher.final();

    return plaintext;
  }

  /**
   * Formats a 64-bit nonce into a 12-byte IV:
   * 4 zero bytes + 8-byte big-endian counter
   */
  private buildIv(nonce: bigint): Buffer {
    const iv = Buffer.alloc(12);
    iv.writeBigUInt64BE(nonce, 4);
    return iv;
  }
}
