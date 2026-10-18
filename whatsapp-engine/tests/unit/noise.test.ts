import { describe, it, expect } from 'vitest';
import { CipherState } from '../../src/noise/CipherState.js';
import { NoiseHandshake } from '../../src/noise/NoiseHandshake.js';
import { Curve25519 } from '../../src/crypto/Curve25519.js';
import crypto from 'crypto';

describe('Noise Protocol Primitives & Handshake', () => {
  it('CipherState correctly encrypts and decrypts with AES-256-GCM and nonces', () => {
    const key = crypto.randomBytes(32);
    const cipher = new CipherState(key);
    const decipher = new CipherState(key);

    const ad = Buffer.from('associated-data');
    const plaintext = Buffer.from('hello-whatsapp-noise');

    const encrypted = cipher.encryptWithAd(ad, plaintext);
    expect(encrypted.length).toBe(plaintext.length + 16); // 16 bytes auth tag

    const decrypted = decipher.decryptWithAd(ad, encrypted);
    expect(decrypted.toString()).toBe('hello-whatsapp-noise');
  });

  it('Curve25519 DH produces identical shared secrets for Alice and Bob', () => {
    const alice = Curve25519.generateKeyPair();
    const bob = Curve25519.generateKeyPair();

    const shared1 = Curve25519.sharedKey(alice.privateKey, bob.publicKey);
    const shared2 = Curve25519.sharedKey(bob.privateKey, alice.publicKey);

    expect(shared1.length).toBe(32);
    expect(shared1.equals(shared2)).toBe(true);
  });

  it('NoiseHandshake executes full 3-step initiator flow with responder simulation', () => {
    const clientStatic = Curve25519.generateKeyPair();
    const serverStatic = Curve25519.generateKeyPair();

    const handshake = new NoiseHandshake(clientStatic);

    // Step 1: Initiator sends e
    const e = handshake.startInitiator();
    expect(e.length).toBe(32);

    // Simulate Responder processing e and sending responder message
    const serverEphemeral = Curve25519.generateKeyPair();
    // Simulate server side of Noise_XX
    const serverCipher = new CipherState();
    // We can simulate step 2 payload:
    // re (32 bytes) + encrypted server static (48 bytes)
    const ee = Curve25519.sharedKey(serverEphemeral.privateKey, e);

    // Setup server temp hash and chaining key
    let h = Buffer.alloc(32);
    Buffer.from('Noise_XX_25519_AESGCM_SHA256\0\0\0\0').copy(h, 0, 0, 32);
    let ck = Buffer.from(h);

    const prologue = Buffer.from('WA\x06\x02', 'latin1');
    const mixH = (d: Buffer) => {
      h = crypto.createHash('sha256').update(h).update(d).digest();
    };
    const mixK = (d: Buffer) => {
      const derived = Buffer.from(crypto.hkdfSync('sha256', d, ck, Buffer.alloc(0), 64));
      ck = derived.subarray(0, 32);
      serverCipher.initializeKey(derived.subarray(32, 64));
    };

    mixH(prologue);
    mixH(e);
    mixH(serverEphemeral.publicKey);
    mixK(ee);

    const encServerStatic = serverCipher.encryptWithAd(h, serverStatic.publicKey);
    mixH(encServerStatic);

    const es = Curve25519.sharedKey(serverStatic.privateKey, e);
    mixK(es);

    const responderMsg = Buffer.concat([serverEphemeral.publicKey, encServerStatic]);

    // Client processes responder message
    handshake.processResponderMessage(responderMsg);

    // Step 3: Initiator finishes with client payload
    const clientPayload = Buffer.from('client-registration-proto');
    const { message, result } = handshake.finishInitiator(clientPayload);

    expect(message.length).toBeGreaterThan(0);
    expect(result.readCipher.hasKey()).toBe(true);
    expect(result.writeCipher.hasKey()).toBe(true);
    expect(result.remoteStaticPublicKey?.equals(serverStatic.publicKey)).toBe(true);
  });
});
