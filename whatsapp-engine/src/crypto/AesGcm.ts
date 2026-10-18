import crypto from 'crypto';

export interface EncryptedPayload {
  ciphertext: Buffer;
  iv: Buffer;
  tag: Buffer;
}

export class AesGcm {
  /**
   * Encrypt plaintext with AES-256-GCM
   */
  public static encrypt(
    key: Buffer,
    plaintext: Buffer,
    iv: Buffer = crypto.randomBytes(12),
    aad: Buffer = Buffer.alloc(0),
  ): EncryptedPayload {
    if (key.length !== 32) {
      throw new Error(`AesGcm key must be 32 bytes, got ${key.length}`);
    }

    const cipher = crypto.createCipheriv('aes-256-gcm', key, iv);
    if (aad.length > 0) {
      cipher.setAAD(aad);
    }

    const ciphertext = Buffer.concat([cipher.update(plaintext), cipher.final()]);
    const tag = cipher.getAuthTag();

    return { ciphertext, iv, tag };
  }

  /**
   * Decrypt ciphertext with AES-256-GCM
   */
  public static decrypt(
    key: Buffer,
    ciphertext: Buffer,
    iv: Buffer,
    tag: Buffer,
    aad: Buffer = Buffer.alloc(0),
  ): Buffer {
    if (key.length !== 32) {
      throw new Error(`AesGcm key must be 32 bytes, got ${key.length}`);
    }
    if (tag.length !== 16) {
      throw new Error(`AesGcm auth tag must be 16 bytes, got ${tag.length}`);
    }

    const decipher = crypto.createDecipheriv('aes-256-gcm', key, iv);
    decipher.setAuthTag(tag);
    if (aad.length > 0) {
      decipher.setAAD(aad);
    }

    return Buffer.concat([decipher.update(ciphertext), decipher.final()]);
  }
}
