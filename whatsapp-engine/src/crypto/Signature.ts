import * as curve25519 from 'curve25519-js';

/** 0x05 key-bundle type byte prefixed to public keys (kept local to avoid a crypto→waweb dependency) */
const KEY_BUNDLE_TYPE = Buffer.from([5]);

/**
 * Curve25519 signature scheme (XEdDSA variant) used by WhatsApp for
 * identity keys, signed pre-keys, and ADV device identity signatures.
 */
export class Signature {
  /**
   * Prefix the 5-byte key bundle type to a raw 32-byte public key
   * (required for some signature/agreement operations).
   */
  public static prefixedPubKey(pubKey: Buffer): Buffer {
    return pubKey.length === 33 ? pubKey : Buffer.concat([KEY_BUNDLE_TYPE, pubKey]);
  }

  /**
   * Sign a message with a Curve25519 private key.
   * Returns a 64-byte signature.
   */
  public static sign(privateKey: Buffer, message: Buffer): Buffer {
    if (privateKey.length !== 32) {
      throw new Error(`Invalid private key length: ${privateKey.length}`);
    }
    return Buffer.from(curve25519.sign(privateKey, message));
  }

  /**
   * Verify a Curve25519 signature against a raw 32-byte public key.
   */
  public static verify(publicKey: Buffer, message: Buffer, signature: Buffer): boolean {
    try {
      const pub = publicKey.length === 33 ? publicKey.subarray(1) : publicKey;
      if (pub.length !== 32 || signature.length !== 64) {
        return false;
      }
      return curve25519.verify(pub, message, signature);
    } catch {
      return false;
    }
  }
}
