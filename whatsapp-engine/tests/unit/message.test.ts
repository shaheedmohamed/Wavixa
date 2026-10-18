import { describe, it, expect } from 'vitest';
import { MessageManager } from '../../src/message/MessageManager.js';
import { MediaManager } from '../../src/message/MediaManager.js';

describe('Message & Media Protocol Managers', () => {
  const manager = new MessageManager();

  it('MessageManager prepares text messages with standardized JIDs and IDs', () => {
    const prep = manager.prepareMessage({
      to: '01011679726',
      type: 'text',
      content: 'Wavixa test transmission',
    });

    expect(prep.toJid).toBe('201011679726@s.whatsapp.net');
    expect(prep.messageId).toMatch(/^3EB0[0-9A-F]{16}$/);
    expect(prep.node.tag).toBe('message');
    expect(prep.node.attrs.id).toBe(prep.messageId);
    expect(prep.protoPayload.conversation).toBe('Wavixa test transmission');
  });

  it('MediaManager encrypts and decrypts media files with MAC verification', () => {
    const rawImage = Buffer.from('simulated-jpeg-raw-bytes-1234567890');
    const enc = MediaManager.encryptMedia(rawImage, 'image');

    expect(enc.fileLength).toBe(rawImage.length);
    expect(enc.mediaKey).toHaveLength(32);
    expect(enc.fileSha256).toHaveLength(32);

    const decrypted = MediaManager.decryptMedia(enc.ciphertext, enc.mediaKey, 'image');
    expect(decrypted.equals(rawImage)).toBe(true);
  });

  it('MediaManager rejects corrupted or tampered media files', () => {
    const rawDoc = Buffer.from('contract-pdf-sample');
    const enc = MediaManager.encryptMedia(rawDoc, 'document');

    // Corrupt one byte in ciphertext
    enc.ciphertext[5] = (enc.ciphertext[5]! ^ 0xff);

    expect(() => {
      MediaManager.decryptMedia(enc.ciphertext, enc.mediaKey, 'document');
    }).toThrow(/MAC authentication failure/);
  });

  it('MediaManager SSRF protection blocks private IP ranges and localhost', () => {
    expect(MediaManager.isSafeUrl('http://127.0.0.1:8000/secret')).toBe(false);
    expect(MediaManager.isSafeUrl('http://localhost:3000/api')).toBe(false);
    expect(MediaManager.isSafeUrl('http://10.0.0.5/test')).toBe(false);
    expect(MediaManager.isSafeUrl('http://192.168.1.1/router')).toBe(false);
    expect(MediaManager.isSafeUrl('https://wavixa.net/logo.png')).toBe(true);
  });
});
