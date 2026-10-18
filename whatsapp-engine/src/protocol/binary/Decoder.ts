import { inflateSync } from 'zlib';
import { BinaryNode } from './BinaryNode.js';
import { DOUBLE_BYTE_TOKENS, SINGLE_BYTE_TOKENS } from './TokenDictionary.js';
import { TAGS } from './Encoder.js';
import { JidServer, jidEncode, WAJIDDomains } from './jid.js';

/**
 * Real WhatsApp WABinary decoder.
 * Input frames are prefixed with a compression flag byte:
 *  - bit 1 (0x02) set → zlib-inflate the remainder
 *  - otherwise        → raw node data
 */
export class BinaryDecoder {
  private index = 0;
  private buffer: Buffer = Buffer.alloc(0);

  /**
   * Decode a full frame (with flag byte) into a BinaryNode.
   */
  public decode(data: Buffer): BinaryNode {
    const decompressed = this.decompressIfRequired(data);
    this.buffer = decompressed;
    this.index = 0;
    return this.readNode();
  }

  private decompressIfRequired(buffer: Buffer): Buffer {
    if (2 & buffer.readUInt8(0)) {
      return inflateSync(buffer.subarray(1));
    }
    return buffer.subarray(1);
  }

  private checkEOS(length: number): void {
    if (this.index + length > this.buffer.length) {
      throw new Error('end of stream');
    }
  }

  private next(): number {
    const value = this.buffer[this.index];
    this.index += 1;
    return value!;
  }

  private readByte(): number {
    this.checkEOS(1);
    return this.next();
  }

  private readBytes(n: number): Buffer {
    this.checkEOS(n);
    const value = this.buffer.subarray(this.index, this.index + n);
    this.index += n;
    return Buffer.from(value);
  }

  private readStringFromChars(length: number): string {
    return this.readBytes(length).toString('utf-8');
  }

  private readInt(n: number): number {
    this.checkEOS(n);
    let val = 0;
    for (let i = 0; i < n; i++) {
      const shift = n - 1 - i;
      val |= this.next() << (shift * 8);
    }
    return val;
  }

  private readInt20(): number {
    this.checkEOS(3);
    return ((this.next() & 15) << 16) + (this.next() << 8) + this.next();
  }

  private unpackHex(value: number): number {
    if (value >= 0 && value < 16) {
      return value < 10 ? '0'.charCodeAt(0) + value : 'A'.charCodeAt(0) + value - 10;
    }
    throw new Error('invalid hex: ' + value);
  }

  private unpackNibble(value: number): number {
    if (value >= 0 && value <= 9) {
      return '0'.charCodeAt(0) + value;
    }
    switch (value) {
      case 10:
        return '-'.charCodeAt(0);
      case 11:
        return '.'.charCodeAt(0);
      case 15:
        return '\0'.charCodeAt(0);
      default:
        throw new Error('invalid nibble: ' + value);
    }
  }

  private unpackByte(tag: number, value: number): number {
    if (tag === TAGS.NIBBLE_8) {
      return this.unpackNibble(value);
    }
    if (tag === TAGS.HEX_8) {
      return this.unpackHex(value);
    }
    throw new Error('unknown tag: ' + tag);
  }

  private readPacked8(tag: number): string {
    const startByte = this.readByte();
    let value = '';
    for (let i = 0; i < (startByte & 127); i++) {
      const curByte = this.readByte();
      value += String.fromCharCode(this.unpackByte(tag, (curByte & 0xf0) >> 4));
      value += String.fromCharCode(this.unpackByte(tag, curByte & 0x0f));
    }
    if (startByte >> 7 !== 0) {
      value = value.slice(0, -1);
    }
    return value;
  }

  private isListTag(tag: number): boolean {
    return tag === TAGS.LIST_EMPTY || tag === TAGS.LIST_8 || tag === TAGS.LIST_16;
  }

  private readListSize(tag: number): number {
    switch (tag) {
      case TAGS.LIST_EMPTY:
        return 0;
      case TAGS.LIST_8:
        return this.readByte();
      case TAGS.LIST_16:
        return this.readInt(2);
      default:
        throw new Error('invalid tag for list size: ' + tag);
    }
  }

  private readJidPair(): string {
    const i = this.readString(this.readByte());
    const j = this.readString(this.readByte());
    if (j) {
      return (i || '') + '@' + j;
    }
    throw new Error('invalid jid pair: ' + i + ', ' + j);
  }

  private readAdJid(): string {
    const domainType = Number(this.readByte());
    const device = this.readByte();
    const user = this.readString(this.readByte());

    let server: JidServer = 's.whatsapp.net';
    if (domainType === WAJIDDomains.LID) {
      server = 'lid';
    } else if (domainType === WAJIDDomains.HOSTED) {
      server = 'hosted';
    } else if (domainType === WAJIDDomains.HOSTED_LID) {
      server = 'hosted.lid';
    }
    return jidEncode(user, server, device);
  }

  private readFbJid(): string {
    const user = this.readString(this.readByte());
    const device = this.readInt(2);
    const server = this.readString(this.readByte());
    return `${user}:${device}@${server}`;
  }

  private readInteropJid(): string {
    const user = this.readString(this.readByte());
    const device = this.readInt(2);
    const integrator = this.readInt(2);

    let server = 'interop';
    const beforeServer = this.index;
    try {
      server = this.readString(this.readByte());
    } catch {
      this.index = beforeServer;
    }
    return `${integrator}-${user}:${device}@${server}`;
  }

  private getTokenDouble(index1: number, index2: number): string {
    const dict = DOUBLE_BYTE_TOKENS[index1];
    if (!dict) {
      throw new Error(`Invalid double token dict (${index1})`);
    }
    const value = dict[index2];
    if (typeof value === 'undefined') {
      throw new Error(`Invalid double token (${index2})`);
    }
    return value;
  }

  private readString(tag: number): string {
    if (tag >= 1 && tag < SINGLE_BYTE_TOKENS.length) {
      return SINGLE_BYTE_TOKENS[tag] || '';
    }

    switch (tag) {
      case TAGS.DICTIONARY_0:
      case TAGS.DICTIONARY_1:
      case TAGS.DICTIONARY_2:
      case TAGS.DICTIONARY_3:
        return this.getTokenDouble(tag - TAGS.DICTIONARY_0, this.readByte());
      case TAGS.LIST_EMPTY:
        return '';
      case TAGS.BINARY_8:
        return this.readStringFromChars(this.readByte());
      case TAGS.BINARY_20:
        return this.readStringFromChars(this.readInt20());
      case TAGS.BINARY_32:
        return this.readStringFromChars(this.readInt(4));
      case TAGS.JID_PAIR:
        return this.readJidPair();
      case TAGS.FB_JID:
        return this.readFbJid();
      case TAGS.INTEROP_JID:
        return this.readInteropJid();
      case TAGS.AD_JID:
        return this.readAdJid();
      case TAGS.HEX_8:
      case TAGS.NIBBLE_8:
        return this.readPacked8(tag);
      default:
        throw new Error('invalid string with tag: ' + tag);
    }
  }

  private readList(tag: number): BinaryNode[] {
    const items: BinaryNode[] = [];
    const size = this.readListSize(tag);
    for (let i = 0; i < size; i++) {
      items.push(this.readNode());
    }
    return items;
  }

  private readNode(): BinaryNode {
    const listSize = this.readListSize(this.readByte());
    const header = this.readString(this.readByte());
    if (!listSize || !header.length) {
      throw new Error('invalid node');
    }

    const attrs: Record<string, string> = {};
    const attributesLength = (listSize - 1) >> 1;
    for (let i = 0; i < attributesLength; i++) {
      const key = this.readString(this.readByte());
      const value = this.readString(this.readByte());
      attrs[key] = value;
    }

    let data: BinaryNode['content'];
    if (listSize % 2 === 0) {
      const tag = this.readByte();
      if (this.isListTag(tag)) {
        data = this.readList(tag);
      } else {
        let decoded: Buffer | string;
        switch (tag) {
          case TAGS.BINARY_8:
            decoded = this.readBytes(this.readByte());
            break;
          case TAGS.BINARY_20:
            decoded = this.readBytes(this.readInt20());
            break;
          case TAGS.BINARY_32:
            decoded = this.readBytes(this.readInt(4));
            break;
          default:
            decoded = this.readString(tag);
            break;
        }
        data = decoded;
      }
    }

    return { tag: header, attrs, content: data };
  }
}
