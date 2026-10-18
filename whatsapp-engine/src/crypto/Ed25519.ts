import crypto from 'crypto';
import { KeyPair } from './Curve25519.js';

const ED25519_SPKI_PREFIX = Buffer.from('302a300506032b6570032100', 'hex');
const ED25519_PKCS8_PREFIX = Buffer.from('302e020100300506032b657004220420', 'hex');

export class Ed25519 {
  /**
   * Generate an Ed25519 keypair (32 bytes private, 32 bytes public)
   */
  public static generateKeyPair(): KeyPair {
    const keypair = crypto.generateKeyPairSync('ed25519');
    const privateKey = keypair.privateKey.export({ format: 'der', type: 'pkcs8' }).subarray(-32);
    const publicKey = keypair.publicKey.export({ format: 'der', type: 'spki' }).subarray(-32);
    return { privateKey, publicKey };
  }

  /**
   * Sign message with Ed25519 private key
   * Returns 64-byte signature
   */
  public static sign(privateKey: Buffer, message: Buffer): Buffer {
    if (privateKey.length !== 32) {
      throw new Error(`Invalid Ed25519 private key length: ${privateKey.length}`);
    }

    const privKeyObj = crypto.createPrivateKey({
      key: Buffer.concat([ED25519_PKCS8_PREFIX, privateKey]),
      format: 'der',
      type: 'pkcs8',
    });

    return crypto.sign(null, message, privKeyObj);
  }

  /**
   * Verify Ed25519 signature
   */
  public static verify(publicKey: Buffer, message: Buffer, signature: Buffer): boolean {
    if (publicKey.length !== 32) {
      throw new Error(`Invalid Ed25519 public key length: ${publicKey.length}`);
    }
    if (signature.length !== 64) {
      return false;
    }

    try {
      const pubKeyObj = crypto.createPublicKey({
        key: Buffer.concat([ED25519_SPKI_PREFIX, publicKey]),
        format: 'der',
        type: 'spki',
      });

      return crypto.verify(null, message, pubKeyObj, signature);
    } catch {
      return false;
    }
  }
}
