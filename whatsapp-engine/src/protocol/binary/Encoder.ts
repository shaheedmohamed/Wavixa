import { BinaryNode } from './BinaryNode.js';
import { TOKEN_MAP } from './TokenDictionary.js';
import { FullJid, jidDecode } from './jid.js';

export const TAGS = {
  LIST_EMPTY: 0,
  DICTIONARY_0: 236,
  DICTIONARY_1: 237,
  DICTIONARY_2: 238,
  DICTIONARY_3: 239,
  INTEROP_JID: 245,
  FB_JID: 246,
  AD_JID: 247,
  LIST_8: 248,
  LIST_16: 249,
  JID_PAIR: 250,
  HEX_8: 251,
  BINARY_8: 252,
  BINARY_20: 253,
  BINARY_32: 254,
  NIBBLE_8: 255,
  PACKED_MAX: 127,
} as const;

/**
 * Real WhatsApp WABinary encoder.
 * Output is prefixed with a single compression-flag byte (0x00 = raw).
 */
export class BinaryEncoder {
  private buffer: number[] = [];

  public encode(node: BinaryNode): Buffer {
    this.buffer = [0]; // compression flag byte
    this.writeNode(node);
    return Buffer.from(this.buffer);
  }

  private pushByte(value: number): void {
    this.buffer.push(value & 0xff);
  }

  private pushBytes(bytes: Uint8Array | Buffer | number[]): void {
    for (const b of bytes) {
      this.buffer.push(b);
    }
  }

  private pushInt(value: number, n: number): void {
    for (let i = 0; i < n; i++) {
      const curShift = n - 1 - i;
      this.buffer.push((value >> (curShift * 8)) & 0xff);
    }
  }

  private pushInt20(value: number): void {
    this.pushBytes([(value >> 16) & 0x0f, (value >> 8) & 0xff, value & 0xff]);
  }

  private writeByteLength(length: number): void {
    if (length >= 4294967296) {
      throw new Error('string too large to encode: ' + length);
    }
    if (length >= 1 << 20) {
      this.pushByte(TAGS.BINARY_32);
      this.pushInt(length, 4);
    } else if (length >= 256) {
      this.pushByte(TAGS.BINARY_20);
      this.pushInt20(length);
    } else {
      this.pushByte(TAGS.BINARY_8);
      this.pushByte(length);
    }
  }

  private writeStringRaw(str: string): void {
    const bytes = Buffer.from(str, 'utf-8');
    this.writeByteLength(bytes.length);
    this.pushBytes(bytes);
  }

  private writeJid({ domainType, device, user, server }: FullJid): void {
    if (typeof device !== 'undefined') {
      this.pushByte(TAGS.AD_JID);
      this.pushByte(domainType || 0);
      this.pushByte(device || 0);
      this.writeString(user);
    } else {
      this.pushByte(TAGS.JID_PAIR);
      if (user.length) {
        this.writeString(user);
      } else {
        this.pushByte(TAGS.LIST_EMPTY);
      }
      this.writeString(server);
    }
  }

  private packNibble(char: string): number {
    switch (char) {
      case '-':
        return 10;
      case '.':
        return 11;
      case '\0':
        return 15;
      default:
        if (char >= '0' && char <= '9') {
          return char.charCodeAt(0) - '0'.charCodeAt(0);
        }
        throw new Error(`invalid byte for nibble "${char}"`);
    }
  }

  private packHex(char: string): number {
    if (char >= '0' && char <= '9') {
      return char.charCodeAt(0) - '0'.charCodeAt(0);
    }
    if (char >= 'A' && char <= 'F') {
      return 10 + char.charCodeAt(0) - 'A'.charCodeAt(0);
    }
    if (char >= 'a' && char <= 'f') {
      return 10 + char.charCodeAt(0) - 'a'.charCodeAt(0);
    }
    if (char === '\0') {
      return 15;
    }
    throw new Error(`Invalid hex char "${char}"`);
  }

  private writePackedBytes(str: string, type: 'nibble' | 'hex'): void {
    if (str.length > TAGS.PACKED_MAX) {
      throw new Error('Too many bytes to pack');
    }

    this.pushByte(type === 'nibble' ? TAGS.NIBBLE_8 : TAGS.HEX_8);

    let roundedLength = Math.ceil(str.length / 2.0);
    if (str.length % 2 !== 0) {
      roundedLength |= 128;
    }
    this.pushByte(roundedLength);

    const packFunction = type === 'nibble' ? this.packNibble : this.packHex;
    const packBytePair = (v1: string, v2: string) => (packFunction(v1) << 4) | packFunction(v2);

    const strLengthHalf = Math.floor(str.length / 2);
    for (let i = 0; i < strLengthHalf; i++) {
      this.pushByte(packBytePair(str[2 * i]!, str[2 * i + 1]!));
    }
    if (str.length % 2 !== 0) {
      this.pushByte(packBytePair(str[str.length - 1]!, '\x00'));
    }
  }

  private isNibble(str?: string): boolean {
    if (!str || str.length > TAGS.PACKED_MAX) {
      return false;
    }
    for (const char of str) {
      const isInNibbleRange = char >= '0' && char <= '9';
      if (!isInNibbleRange && char !== '-' && char !== '.') {
        return false;
      }
    }
    return true;
  }

  private isHex(str?: string): boolean {
    if (!str || str.length > TAGS.PACKED_MAX) {
      return false;
    }
    for (const char of str) {
      const isInNibbleRange = char >= '0' && char <= '9';
      if (!isInNibbleRange && !(char >= 'A' && char <= 'F')) {
        return false;
      }
    }
    return true;
  }

  private writeString(str?: string): void {
    if (str === undefined || str === null) {
      this.pushByte(TAGS.LIST_EMPTY);
      return;
    }
    if (str === '') {
      this.writeStringRaw(str);
      return;
    }

    const tokenIndex = TOKEN_MAP[str];
    if (tokenIndex) {
      if (typeof tokenIndex.dict === 'number') {
        this.pushByte(TAGS.DICTIONARY_0 + tokenIndex.dict);
      }
      this.pushByte(tokenIndex.index);
    } else if (this.isNibble(str)) {
      this.writePackedBytes(str, 'nibble');
    } else if (this.isHex(str)) {
      this.writePackedBytes(str, 'hex');
    } else {
      const decodedJid = jidDecode(str);
      if (decodedJid) {
        this.writeJid(decodedJid);
      } else {
        this.writeStringRaw(str);
      }
    }
  }

  private writeListStart(listSize: number): void {
    if (listSize === 0) {
      this.pushByte(TAGS.LIST_EMPTY);
    } else if (listSize < 256) {
      this.pushBytes([TAGS.LIST_8, listSize]);
    } else {
      this.pushByte(TAGS.LIST_16);
      this.pushInt(listSize, 2);
    }
  }

  private writeNode({ tag, attrs, content }: BinaryNode): void {
    if (!tag) {
      throw new Error('Invalid node: tag cannot be undefined');
    }

    const validAttributes = Object.keys(attrs || {}).filter(
      (k) => typeof attrs[k] !== 'undefined' && attrs[k] !== null,
    );

    this.writeListStart(2 * validAttributes.length + 1 + (typeof content !== 'undefined' ? 1 : 0));
    this.writeString(tag);

    for (const key of validAttributes) {
      if (typeof attrs[key] === 'string') {
        this.writeString(key);
        this.writeString(attrs[key]);
      }
    }

    if (typeof content === 'string') {
      this.writeString(content);
    } else if (Buffer.isBuffer(content) || content instanceof Uint8Array) {
      this.writeByteLength(content.length);
      this.pushBytes(content);
    } else if (Array.isArray(content)) {
      const validContent = content.filter(
        (item) =>
          item && (item.tag || Buffer.isBuffer(item) || item instanceof Uint8Array || typeof item === 'string'),
      );
      this.writeListStart(validContent.length);
      for (const item of validContent) {
        this.writeNode(item);
      }
    } else if (typeof content === 'undefined') {
      // do nothing
    } else {
      throw new Error(`invalid children for header "${tag}": ${content} (${typeof content})`);
    }
  }
}
