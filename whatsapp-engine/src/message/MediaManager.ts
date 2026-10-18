import crypto from 'crypto';
import net from 'net';
import fs from 'fs';
import path from 'path';
import axios from 'axios';
import { logger } from '../config/Logger.js';

export type MediaType = 'image' | 'video' | 'audio' | 'document' | 'voice' | 'history';

export interface EncryptedMedia {
  ciphertext: Buffer;
  fileSha256: Buffer;
  fileEncSha256: Buffer;
  mediaKey: Buffer;
  fileLength: number;
}

export class MediaManager {
  private static readonly MEDIA_KEY_INFO: Record<MediaType, string> = {
    image: 'WhatsApp Image Keys',
    video: 'WhatsApp Video Keys',
    audio: 'WhatsApp Audio Keys',
    document: 'WhatsApp Document Keys',
    voice: 'WhatsApp Audio Keys',
    history: 'WhatsApp History Keys',
  };

  /**
   * Encrypt raw media file bytes using WhatsApp media keys
   */
  public static encryptMedia(rawBytes: Buffer, type: MediaType): EncryptedMedia {
    const mediaKey = crypto.randomBytes(32);
    const infoStr = MediaManager.MEDIA_KEY_INFO[type];

    // Derive iv (16), cipherKey (32), macKey (32) = 112 bytes total
    const derived = Buffer.from(
      crypto.hkdfSync(
        'sha256',
        mediaKey,
        Buffer.alloc(32),
        Buffer.from(infoStr, 'utf8'),
        112,
      ),
    );

    const iv = derived.subarray(0, 16);
    const cipherKey = derived.subarray(16, 48);
    const macKey = derived.subarray(48, 80);

    const cipher = crypto.createCipheriv('aes-256-cbc', cipherKey, iv);
    const ciphertext = Buffer.concat([cipher.update(rawBytes), cipher.final()]);

    const macInput = Buffer.concat([iv, ciphertext]);
    const mac = crypto.createHmac('sha256', macKey).update(macInput).digest();
    const truncatedMac = mac.subarray(0, 10);

    const finalCiphertext = Buffer.concat([ciphertext, truncatedMac]);

    const fileSha256 = crypto.createHash('sha256').update(rawBytes).digest();
    const fileEncSha256 = crypto.createHash('sha256').update(finalCiphertext).digest();

    return {
      ciphertext: finalCiphertext,
      fileSha256,
      fileEncSha256,
      mediaKey,
      fileLength: rawBytes.length,
    };
  }

  /**
   * Decrypt WhatsApp encrypted media buffer
   */
  public static decryptMedia(
    encryptedBuffer: Buffer,
    mediaKey: Buffer,
    type: MediaType,
  ): Buffer {
    if (encryptedBuffer.length < 26) {
      throw new Error(`Media buffer too short: ${encryptedBuffer.length} bytes`);
    }

    const infoStr = MediaManager.MEDIA_KEY_INFO[type];
    const derived = Buffer.from(
      crypto.hkdfSync(
        'sha256',
        mediaKey,
        Buffer.alloc(32),
        Buffer.from(infoStr, 'utf8'),
        112,
      ),
    );

    const iv = derived.subarray(0, 16);
    const cipherKey = derived.subarray(16, 48);
    const macKey = derived.subarray(48, 80);

    const ciphertext = encryptedBuffer.subarray(0, encryptedBuffer.length - 10);
    const receivedMac = encryptedBuffer.subarray(encryptedBuffer.length - 10);

    const macInput = Buffer.concat([iv, ciphertext]);
    const expectedMac = crypto.createHmac('sha256', macKey).update(macInput).digest().subarray(0, 10);

    if (!crypto.timingSafeEqual(receivedMac, expectedMac)) {
      throw new Error('Media MAC authentication failure: corrupted or tampered media file');
    }

    const decipher = crypto.createDecipheriv('aes-256-cbc', cipherKey, iv);
    return Buffer.concat([decipher.update(ciphertext), decipher.final()]);
  }

  /**
   * Anti-SSRF check: Prevents downloading media from localhost or private network ranges
   */
  public static isSafeUrl(urlString: string): boolean {
    try {
      const parsed = new URL(urlString);
      if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') {
        return false;
      }

      const hostname = parsed.hostname.toLowerCase();
      if (
        hostname === 'localhost' ||
        hostname === '127.0.0.1' ||
        hostname === '0.0.0.0' ||
        hostname === '::1'
      ) {
        return false;
      }

      if (net.isIP(hostname)) {
        const parts = hostname.split('.').map(Number);
        if (parts.length === 4) {
          const [a, b] = parts;
          if (a === 10) return false; // 10.0.0.0/8
          if (a === 172 && (b ?? 0) >= 16 && (b ?? 0) <= 31) return false; // 172.16.0.0/12
          if (a === 192 && b === 168) return false; // 192.168.0.0/16
          if (a === 127) return false; // 127.0.0.0/8
          if (a === 169 && b === 254) return false; // Link-local
        }
      }

      return true;
    } catch {
      return false;
    }
  }

  /**
   * Download encrypted WhatsApp media from CDN, decrypt with mediaKey, and save to local public storage
   */
  public static async downloadAndSaveMedia(opts: {
    directPath?: string;
    url?: string;
    mediaKey: Buffer | Uint8Array;
    type: MediaType;
    mimetype?: string;
  }): Promise<string | null> {
    try {
      const downloadUrl = opts.directPath
        ? `https://mmg.whatsapp.net${opts.directPath}`
        : opts.url;
      if (!downloadUrl) return null;

      const resp = await axios.get(downloadUrl, {
        responseType: 'arraybuffer',
        timeout: 45000,
        maxContentLength: 100 * 1024 * 1024,
      });

      const encryptedBuf = Buffer.from(resp.data);
      const mediaKeyBuf = Buffer.from(opts.mediaKey);
      const decrypted = MediaManager.decryptMedia(encryptedBuf, mediaKeyBuf, opts.type);

      let ext = 'bin';
      if (opts.type === 'image') ext = 'jpg';
      else if (opts.type === 'video') ext = 'mp4';
      else if (opts.type === 'audio' || opts.type === 'voice') ext = 'ogg';
      else if (opts.mimetype) {
        if (opts.mimetype.includes('jpeg') || opts.mimetype.includes('jpg')) ext = 'jpg';
        else if (opts.mimetype.includes('png')) ext = 'png';
        else if (opts.mimetype.includes('webp')) ext = 'webp';
        else if (opts.mimetype.includes('pdf')) ext = 'pdf';
        else if (opts.mimetype.includes('mp4')) ext = 'mp4';
        else if (opts.mimetype.includes('ogg')) ext = 'ogg';
        else if (opts.mimetype.includes('mp3')) ext = 'mp3';
      }

      const filename = `media_${Date.now()}_${crypto.randomBytes(6).toString('hex')}.${ext}`;
      const storageDir = '/var/www/Wavixa/backend/public/storage/crm_media';
      if (!fs.existsSync(storageDir)) {
        fs.mkdirSync(storageDir, { recursive: true });
      }
      const filePath = path.join(storageDir, filename);
      await fs.promises.writeFile(filePath, decrypted);

      return `/storage/crm_media/${filename}`;
    } catch (err) {
      logger.warn({ err }, 'Failed to download and decrypt media');
      return null;
    }
  }
}
