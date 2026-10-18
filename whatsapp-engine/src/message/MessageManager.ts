import crypto from 'crypto';
import { BinaryNode } from '../protocol/binary/BinaryNode.js';
import { ProtoRegistry, IMessage } from '../protocol/proto/ProtoRegistry.js';
import { MediaManager, MediaType } from './MediaManager.js';

export interface SendMessagePayload {
  to: string;
  type: 'text' | 'image' | 'video' | 'audio' | 'voice' | 'document' | 'location' | 'contact' | 'reaction';
  content: string; // text body, media base64 or url, json string
  caption?: string;
  fileName?: string;
  mimeType?: string;
  quotedMessageId?: string;
  location?: {
    latitude: number;
    longitude: number;
    name?: string;
    address?: string;
  };
  contact?: {
    displayName: string;
    vcard: string;
  };
}

export interface PreparedMessage {
  messageId: string;
  toJid: string;
  node: BinaryNode;
  protoPayload: IMessage;
}

export class MessageManager {
  private protoRegistry: ProtoRegistry;

  constructor() {
    this.protoRegistry = ProtoRegistry.getInstance();
  }

  /**
   * Format destination number into WhatsApp JID
   */
  public static normalizeJid(to: string): string {
    const cleaned = to.trim();
    if (cleaned.includes('@')) {
      return cleaned;
    }
    const digitsOnly = cleaned.replace(/\D/g, '');
    if (digitsOnly.startsWith('01') && digitsOnly.length === 11) {
      return `2${digitsOnly}@s.whatsapp.net`;
    }
    return `${digitsOnly}@s.whatsapp.net`;
  }

  /**
   * Generate standard WhatsApp message ID (3EB0...)
   */
  public static generateMessageId(): string {
    return '3EB0' + crypto.randomBytes(8).toString('hex').toUpperCase();
  }

  /**
   * Build BinaryNode for outgoing message
   */
  public prepareMessage(payload: SendMessagePayload): PreparedMessage {
    const messageId = MessageManager.generateMessageId();
    const toJid = MessageManager.normalizeJid(payload.to);

    let proto: IMessage = {};

    switch (payload.type) {
      case 'text':
        proto = { conversation: payload.content };
        break;
      case 'image':
      case 'video':
      case 'audio':
      case 'voice':
      case 'document': {
        const mediaType = payload.type as MediaType;
        const rawBytes = Buffer.from(payload.content, payload.content.startsWith('http') ? 'utf8' : 'base64');
        const enc = MediaManager.encryptMedia(rawBytes, mediaType);
        proto = {
          imageMessage: payload.type === 'image' ? {
            mimetype: payload.mimeType || 'image/jpeg',
            caption: payload.caption,
            fileSha256: enc.fileSha256,
            fileLength: enc.fileLength,
            mediaKey: enc.mediaKey,
          } : undefined,
          videoMessage: payload.type === 'video' ? {
            mimetype: payload.mimeType || 'video/mp4',
            caption: payload.caption,
            fileSha256: enc.fileSha256,
            fileLength: enc.fileLength,
            mediaKey: enc.mediaKey,
          } : undefined,
          audioMessage: (payload.type === 'audio' || payload.type === 'voice') ? {
            mimetype: payload.mimeType || 'audio/ogg',
            ptt: payload.type === 'voice',
            fileSha256: enc.fileSha256,
            fileLength: enc.fileLength,
            mediaKey: enc.mediaKey,
          } : undefined,
          documentMessage: payload.type === 'document' ? {
            mimetype: payload.mimeType || 'application/pdf',
            fileName: payload.fileName || 'document.pdf',
            caption: payload.caption,
            fileSha256: enc.fileSha256,
            fileLength: enc.fileLength,
            mediaKey: enc.mediaKey,
          } : undefined,
        };
        break;
      }
      case 'location':
        proto = {
          locationMessage: {
            degreesLatitude: payload.location?.latitude ?? 0,
            degreesLongitude: payload.location?.longitude ?? 0,
            name: payload.location?.name,
            address: payload.location?.address,
          },
        };
        break;
      case 'contact':
        proto = {
          contactMessage: {
            displayName: payload.contact?.displayName || 'Contact',
            vcard: payload.contact?.vcard || '',
          },
        };
        break;
      case 'reaction':
        proto = {
          reactionMessage: {
            key: {
              remoteJid: toJid,
              fromMe: false,
              id: payload.quotedMessageId || '',
            },
            text: payload.content,
            senderTimestampMs: Date.now(),
          },
        };
        break;
    }

    const protoBytes = this.protoRegistry.encodeMessage(proto);

    const node: BinaryNode = {
      tag: 'message',
      attrs: {
        id: messageId,
        to: toJid,
        type: payload.type === 'reaction' ? 'reaction' : 'text',
      },
      content: protoBytes,
    };

    return {
      messageId,
      toJid,
      node,
      protoPayload: proto,
    };
  }

  /**
   * Build delivery or read receipt BinaryNode
   */
  public static buildReceipt(
    toJid: string,
    messageId: string,
    type: 'read' | 'delivered' | 'played' = 'delivered',
  ): BinaryNode {
    return {
      tag: 'receipt',
      attrs: {
        id: messageId,
        to: toJid,
        type,
        t: String(Math.floor(Date.now() / 1000)),
      },
    };
  }
}
