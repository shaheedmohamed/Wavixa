import crypto from 'crypto';

export class Hkdf {
  /**
   * Derive keys using HKDF-SHA256
   */
  public static derive(
    ikm: Buffer,
    salt: Buffer,
    info: Buffer,
    length: number,
  ): Buffer {
    return Buffer.from(crypto.hkdfSync('sha256', ikm, salt, info, length));
  }
}
