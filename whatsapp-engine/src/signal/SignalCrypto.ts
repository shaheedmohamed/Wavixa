import crypto from 'crypto';

/**
 * Signal protocol crypto primitives — byte-compatible with
 * libsignal's Node implementation (AES-256-CBC, HMAC-SHA256, HKDF-style
 * deriveSecrets producing 32-byte chunks).
 */

export const signalEncrypt = (key: Buffer, data: Buffer, iv: Buffer): Buffer => {
  const cipher = crypto.createCipheriv('aes-256-cbc', key, iv);
  return Buffer.concat([cipher.update(data), cipher.final()]);
};

export const signalDecrypt = (key: Buffer, data: Buffer, iv: Buffer): Buffer => {
  const decipher = crypto.createDecipheriv('aes-256-cbc', key, iv);
  return Buffer.concat([decipher.update(data), decipher.final()]);
};

export const calculateMAC = (key: Buffer, data: Buffer): Buffer =>
  crypto.createHmac('sha256', key).update(data).digest();

export const verifyMAC = (data: Buffer, key: Buffer, mac: Buffer, length: number): void => {
  const calculated = calculateMAC(key, data);
  if (!crypto.timingSafeEqual(calculated.subarray(0, length), mac.subarray(0, length))) {
    throw new Error('Bad MAC');
  }
};

/**
 * RFC 5869 HKDF variant used by Signal: returns `chunks` 32-byte outputs.
 */
export const deriveSecrets = (
  input: Buffer,
  salt: Buffer,
  info: Buffer,
  chunks = 3,
): Buffer[] => {
  if (salt.byteLength !== 32) {
    throw new Error('Got salt of incorrect length');
  }
  const prk = calculateMAC(salt, input);
  const infoArray = new Uint8Array(info.byteLength + 1 + 32);
  infoArray.set(info, 32);
  infoArray[infoArray.length - 1] = 1;
  const signed: Buffer[] = [calculateMAC(prk, Buffer.from(infoArray.subarray(32)))];
  if (chunks > 1) {
    infoArray.set(signed[signed.length - 1]!);
    infoArray[infoArray.length - 1] = 2;
    signed.push(calculateMAC(prk, Buffer.from(infoArray)));
  }
  if (chunks > 2) {
    infoArray.set(signed[signed.length - 1]!);
    infoArray[infoArray.length - 1] = 3;
    signed.push(calculateMAC(prk, Buffer.from(infoArray)));
  }
  return signed;
};
