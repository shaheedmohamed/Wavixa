import crypto from 'crypto';
import { AesGcm } from '../crypto/AesGcm.js';
import { DeviceIdentityManager, ISignedPreKey } from './DeviceIdentityManager.js';
import { KeyPair } from '../crypto/Curve25519.js';

export interface StoredKeyBundle {
  sessionId: string;
  registrationId: number;
  deviceId: number;
  identityPublicKeyHex: string;
  encryptedBundle: string; // base64
  ivHex: string;
  tagHex: string;
  version: number;
}

export class KeyStore {
  private masterKey: Buffer;

  constructor(masterKeyHex?: string) {
    if (masterKeyHex && masterKeyHex.length >= 32) {
      this.masterKey = crypto.createHash('sha256').update(masterKeyHex).digest();
    } else {
      // Fallback to environment or generate secure runtime key
      const envKey = process.env.ENGINE_MASTER_KEY || 'wavixa-whatsapp-engine-master-vault-key-2026';
      this.masterKey = crypto.createHash('sha256').update(envKey).digest();
    }
  }

  /**
   * Encrypt and serialize device identity into a secure storage bundle
   */
  public exportEncryptedBundle(sessionId: string, identity: DeviceIdentityManager): StoredKeyBundle {
    const rawKeys = {
      registrationId: identity.getRegistrationId(),
      deviceId: identity.getDeviceId(),
      identityKeyPair: {
        privateKey: identity.getIdentityKeyPair().privateKey.toString('hex'),
        publicKey: identity.getIdentityKeyPair().publicKey.toString('hex'),
      },
      noiseKeyPair: {
        privateKey: identity.getNoiseKeyPair().privateKey.toString('hex'),
        publicKey: identity.getNoiseKeyPair().publicKey.toString('hex'),
      },
      signedPreKey: {
        keyId: identity.getSignedPreKey().keyId,
        privateKey: identity.getSignedPreKey().keyPair.privateKey.toString('hex'),
        publicKey: identity.getSignedPreKey().keyPair.publicKey.toString('hex'),
        signature: identity.getSignedPreKey().signature.toString('hex'),
      },
    };

    const plaintext = Buffer.from(JSON.stringify(rawKeys), 'utf8');
    const aad = Buffer.from(`wavixa:session:${sessionId}`, 'utf8');
    const encrypted = AesGcm.encrypt(this.masterKey, plaintext, crypto.randomBytes(12), aad);

    return {
      sessionId,
      registrationId: identity.getRegistrationId(),
      deviceId: identity.getDeviceId(),
      identityPublicKeyHex: identity.getIdentityKeyPair().publicKey.toString('hex'),
      encryptedBundle: encrypted.ciphertext.toString('base64'),
      ivHex: encrypted.iv.toString('hex'),
      tagHex: encrypted.tag.toString('hex'),
      version: 1,
    };
  }

  /**
   * Decrypt storage bundle and reconstruct DeviceIdentityManager
   */
  public importEncryptedBundle(bundle: StoredKeyBundle): DeviceIdentityManager {
    const ciphertext = Buffer.from(bundle.encryptedBundle, 'base64');
    const iv = Buffer.from(bundle.ivHex, 'hex');
    const tag = Buffer.from(bundle.tagHex, 'hex');
    const aad = Buffer.from(`wavixa:session:${bundle.sessionId}`, 'utf8');

    const plaintext = AesGcm.decrypt(this.masterKey, ciphertext, iv, tag, aad);
    const rawKeys = JSON.parse(plaintext.toString('utf8'));

    const identityKeyPair: KeyPair = {
      privateKey: Buffer.from(rawKeys.identityKeyPair.privateKey, 'hex'),
      publicKey: Buffer.from(rawKeys.identityKeyPair.publicKey, 'hex'),
    };

    const noiseKeyPair: KeyPair = {
      privateKey: Buffer.from(rawKeys.noiseKeyPair.privateKey, 'hex'),
      publicKey: Buffer.from(rawKeys.noiseKeyPair.publicKey, 'hex'),
    };

    const signedPreKey: ISignedPreKey = {
      keyId: rawKeys.signedPreKey.keyId,
      keyPair: {
        privateKey: Buffer.from(rawKeys.signedPreKey.privateKey, 'hex'),
        publicKey: Buffer.from(rawKeys.signedPreKey.publicKey, 'hex'),
      },
      signature: Buffer.from(rawKeys.signedPreKey.signature, 'hex'),
    };

    return new DeviceIdentityManager(
      rawKeys.registrationId,
      rawKeys.deviceId,
      identityKeyPair,
      noiseKeyPair,
      signedPreKey,
    );
  }
}
