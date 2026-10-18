import crypto from 'crypto';

export interface KeyPair {
  privateKey: Buffer;
  publicKey: Buffer;
}

const X25519_PKCS8_PREFIX = Buffer.from('302e020100300506032b656e04220420', 'hex');
const X25519_SPKI_PREFIX = Buffer.from('302a300506032b656e032100', 'hex');

export class Curve25519 {
  /**
   * Generate an X25519 keypair (32 bytes private, 32 bytes public)
   */
  public static generateKeyPair(): KeyPair {
    const keypair = crypto.generateKeyPairSync('x25519');
    const privateKey = keypair.privateKey.export({ format: 'der', type: 'pkcs8' }).subarray(-32);
    const publicKey = keypair.publicKey.export({ format: 'der', type: 'spki' }).subarray(-32);
    return { privateKey, publicKey };
  }

  /**
   * Derive shared secret from private key and remote public key (Diffie-Hellman)
   */
  public static sharedKey(privateKey: Buffer, remotePublicKey: Buffer): Buffer {
    if (privateKey.length !== 32) {
      throw new Error(`Invalid private key length: ${privateKey.length}`);
    }
    if (remotePublicKey.length !== 32) {
      throw new Error(`Invalid public key length: ${remotePublicKey.length}`);
    }

    const privKeyObj = crypto.createPrivateKey({
      key: Buffer.concat([X25519_PKCS8_PREFIX, privateKey]),
      format: 'der',
      type: 'pkcs8',
    });

    const pubKeyObj = crypto.createPublicKey({
      key: Buffer.concat([X25519_SPKI_PREFIX, remotePublicKey]),
      format: 'der',
      type: 'spki',
    });

    return crypto.diffieHellman({
      privateKey: privKeyObj,
      publicKey: pubKeyObj,
    });
  }
}
