import { describe, it, expect } from 'vitest';
import { Ed25519 } from '../../src/crypto/Ed25519.js';
import { DeviceIdentityManager } from '../../src/identity/DeviceIdentityManager.js';
import { KeyStore } from '../../src/identity/KeyStore.js';
import { SignalSession } from '../../src/crypto/SignalSession.js';
import { Curve25519 } from '../../src/crypto/Curve25519.js';
import crypto from 'crypto';

describe('Identity & Signal Protocol Cryptography', () => {
  it('Ed25519 signs and verifies messages accurately', () => {
    const keyPair = Ed25519.generateKeyPair();
    const message = Buffer.from('verify-wavixa-identity-signature');

    const signature = Ed25519.sign(keyPair.privateKey, message);
    expect(signature.length).toBe(64);

    const isValid = Ed25519.verify(keyPair.publicKey, message, signature);
    expect(isValid).toBe(true);

    const isCorruptValid = Ed25519.verify(keyPair.publicKey, Buffer.from('corrupt'), signature);
    expect(isCorruptValid).toBe(false);
  });

  it('DeviceIdentityManager exports public identity with zero private key exposure', () => {
    const identity = new DeviceIdentityManager();
    const pub = identity.getPublicIdentity();

    expect(pub.registrationId).toBeGreaterThan(0);
    expect(pub.identityPublicKey).toHaveLength(64); // 32 bytes hex
    expect(pub.noisePublicKey).toHaveLength(64);
    expect(pub.signedPreKey.signature).toHaveLength(128); // 64 bytes hex

    // Verify JSON serialization contains zero private keys
    const jsonStr = JSON.stringify(identity);
    expect(jsonStr).not.toContain('privateKey');
  });

  it('KeyStore encrypts and imports device identity bundle losslessly', () => {
    const keyStore = new KeyStore('a'.repeat(64));
    const original = new DeviceIdentityManager();

    const bundle = keyStore.exportEncryptedBundle('session_user_test', original);
    expect(bundle.encryptedBundle).toBeTruthy();
    expect(bundle.ivHex).toHaveLength(24); // 12 bytes hex
    expect(bundle.tagHex).toHaveLength(32); // 16 bytes hex

    const restored = keyStore.importEncryptedBundle(bundle);
    expect(restored.getRegistrationId()).toBe(original.getRegistrationId());
    expect(restored.getIdentityKeyPair().publicKey.equals(original.getIdentityKeyPair().publicKey)).toBe(true);
    expect(restored.getIdentityKeyPair().privateKey.equals(original.getIdentityKeyPair().privateKey)).toBe(true);
    expect(restored.getNoiseKeyPair().publicKey.equals(original.getNoiseKeyPair().publicKey)).toBe(true);
  });

  it('SignalSession encrypts and decrypts messages with Double Ratchet chains', () => {
    const sharedSecret = crypto.randomBytes(32);
    const aliceDh = Curve25519.generateKeyPair();
    const bobDh = Curve25519.generateKeyPair();

    // Alice -> Bob session (Alice is initiator)
    const aliceSession = new SignalSession(sharedSecret, aliceDh, bobDh.publicKey, true);
    // Bob -> Alice matching session (Bob is responder)
    const bobSession = new SignalSession(sharedSecret, bobDh, aliceDh.publicKey, false);

    // Alice encrypts message
    const plaintext = Buffer.from('secret end-to-end payload');
    const ciphertext = aliceSession.encrypt(plaintext);

    // Bob decrypts message
    const decrypted = bobSession.decrypt(ciphertext);
    expect(decrypted.toString('utf8')).toBe('secret end-to-end payload');
  });
});
