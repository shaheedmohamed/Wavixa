import protobuf from 'protobufjs';

/**
 * Wavixa WhatsApp Engine - ProtoRegistry
 * Manages Google Protocol Buffer schema definitions and serialization
 * for WhatsApp Multi-Device messages, pairing payloads, and app-state sync.
 */

export interface IMessageKey {
  remoteJid: string;
  fromMe: boolean;
  id: string;
  participant?: string;
}

export interface ITextMessage {
  conversation?: string;
  extendedTextMessage?: {
    text: string;
    matchedText?: string;
  };
}

export interface IMediaMessage {
  url?: string;
  mimetype?: string;
  caption?: string;
  fileSha256?: Buffer;
  fileLength?: number;
  mediaKey?: Buffer;
  seconds?: number;
  ptt?: boolean;
  fileName?: string;
}

export interface ILocationMessage {
  degreesLatitude: number;
  degreesLongitude: number;
  name?: string;
  address?: string;
}

export interface IContactMessage {
  displayName: string;
  vcard: string;
}

export interface IReactionMessage {
  key: IMessageKey;
  text: string;
  senderTimestampMs?: number;
}

export interface IMessage {
  conversation?: string;
  extendedTextMessage?: { text: string };
  imageMessage?: IMediaMessage;
  videoMessage?: IMediaMessage;
  audioMessage?: IMediaMessage;
  documentMessage?: IMediaMessage;
  locationMessage?: ILocationMessage;
  contactMessage?: IContactMessage;
  reactionMessage?: IReactionMessage;
}

export class ProtoRegistry {
  private static instance: ProtoRegistry | null = null;
  private root: protobuf.Root;

  private messageType: protobuf.Type;
  private webMessageInfoType: protobuf.Type;
  private clientPayloadType: protobuf.Type;

  private constructor() {
    this.root = new protobuf.Root();
    this.buildSchemas();
    this.messageType = this.root.lookupType('wavixa.proto.Message');
    this.webMessageInfoType = this.root.lookupType('wavixa.proto.WebMessageInfo');
    this.clientPayloadType = this.root.lookupType('wavixa.proto.ClientPayload');
  }

  public static getInstance(): ProtoRegistry {
    if (!ProtoRegistry.instance) {
      ProtoRegistry.instance = new ProtoRegistry();
    }
    return ProtoRegistry.instance;
  }

  private buildSchemas(): void {
    const pkg = this.root.define('wavixa.proto');

    // MessageKey
    const MessageKey = new protobuf.Type('MessageKey')
      .add(new protobuf.Field('remoteJid', 1, 'string'))
      .add(new protobuf.Field('fromMe', 2, 'bool'))
      .add(new protobuf.Field('id', 3, 'string'))
      .add(new protobuf.Field('participant', 4, 'string', 'optional'));

    // ExtendedTextMessage
    const ExtendedTextMessage = new protobuf.Type('ExtendedTextMessage')
      .add(new protobuf.Field('text', 1, 'string'))
      .add(new protobuf.Field('matchedText', 2, 'string', 'optional'));

    // MediaMessage
    const MediaMessage = new protobuf.Type('MediaMessage')
      .add(new protobuf.Field('url', 1, 'string', 'optional'))
      .add(new protobuf.Field('mimetype', 2, 'string', 'optional'))
      .add(new protobuf.Field('caption', 3, 'string', 'optional'))
      .add(new protobuf.Field('fileSha256', 4, 'bytes', 'optional'))
      .add(new protobuf.Field('fileLength', 5, 'uint64', 'optional'))
      .add(new protobuf.Field('mediaKey', 6, 'bytes', 'optional'))
      .add(new protobuf.Field('seconds', 7, 'uint32', 'optional'))
      .add(new protobuf.Field('ptt', 8, 'bool', 'optional'))
      .add(new protobuf.Field('fileName', 9, 'string', 'optional'));

    // LocationMessage
    const LocationMessage = new protobuf.Type('LocationMessage')
      .add(new protobuf.Field('degreesLatitude', 1, 'double'))
      .add(new protobuf.Field('degreesLongitude', 2, 'double'))
      .add(new protobuf.Field('name', 3, 'string', 'optional'))
      .add(new protobuf.Field('address', 4, 'string', 'optional'));

    // ContactMessage
    const ContactMessage = new protobuf.Type('ContactMessage')
      .add(new protobuf.Field('displayName', 1, 'string'))
      .add(new protobuf.Field('vcard', 2, 'string'));

    // ReactionMessage
    const ReactionMessage = new protobuf.Type('ReactionMessage')
      .add(new protobuf.Field('key', 1, 'MessageKey'))
      .add(new protobuf.Field('text', 2, 'string'))
      .add(new protobuf.Field('senderTimestampMs', 3, 'int64', 'optional'));

    // Message
    const Message = new protobuf.Type('Message')
      .add(new protobuf.Field('conversation', 1, 'string', 'optional'))
      .add(new protobuf.Field('extendedTextMessage', 2, 'ExtendedTextMessage', 'optional'))
      .add(new protobuf.Field('imageMessage', 3, 'MediaMessage', 'optional'))
      .add(new protobuf.Field('videoMessage', 4, 'MediaMessage', 'optional'))
      .add(new protobuf.Field('audioMessage', 5, 'MediaMessage', 'optional'))
      .add(new protobuf.Field('documentMessage', 6, 'MediaMessage', 'optional'))
      .add(new protobuf.Field('locationMessage', 7, 'LocationMessage', 'optional'))
      .add(new protobuf.Field('contactMessage', 8, 'ContactMessage', 'optional'))
      .add(new protobuf.Field('reactionMessage', 9, 'ReactionMessage', 'optional'));

    // WebMessageInfo
    const WebMessageInfo = new protobuf.Type('WebMessageInfo')
      .add(new protobuf.Field('key', 1, 'MessageKey'))
      .add(new protobuf.Field('message', 2, 'Message'))
      .add(new protobuf.Field('messageTimestamp', 3, 'uint64'))
      .add(new protobuf.Field('status', 4, 'uint32', 'optional'));

    // ClientPayload for Pairing & Registration
    const ClientPayload = new protobuf.Type('ClientPayload')
      .add(new protobuf.Field('username', 1, 'uint64', 'optional'))
      .add(new protobuf.Field('passive', 2, 'bool', 'optional'))
      .add(new protobuf.Field('userAgent', 3, 'bytes', 'optional'))
      .add(new protobuf.Field('devicePairingData', 4, 'bytes', 'optional'));

    pkg.add(MessageKey);
    pkg.add(ExtendedTextMessage);
    pkg.add(MediaMessage);
    pkg.add(LocationMessage);
    pkg.add(ContactMessage);
    pkg.add(ReactionMessage);
    pkg.add(Message);
    pkg.add(WebMessageInfo);
    pkg.add(ClientPayload);
  }

  public encodeMessage(msg: IMessage): Buffer {
    const errMsg = this.messageType.verify(msg);
    if (errMsg) throw new Error(`Invalid message protobuf: ${errMsg}`);
    const messageInstance = this.messageType.create(msg);
    return Buffer.from(this.messageType.encode(messageInstance).finish());
  }

  public decodeMessage(buffer: Buffer): IMessage {
    const decoded = this.messageType.decode(buffer);
    return this.messageType.toObject(decoded, { defaults: true }) as IMessage;
  }

  public encodeWebMessageInfo(info: {
    key: IMessageKey;
    message: IMessage;
    messageTimestamp: number;
    status?: number;
  }): Buffer {
    const errMsg = this.webMessageInfoType.verify(info);
    if (errMsg) throw new Error(`Invalid web message info: ${errMsg}`);
    const instance = this.webMessageInfoType.create(info);
    return Buffer.from(this.webMessageInfoType.encode(instance).finish());
  }

  public decodeWebMessageInfo(buffer: Buffer): {
    key: IMessageKey;
    message: IMessage;
    messageTimestamp: number;
    status?: number;
  } {
    const decoded = this.webMessageInfoType.decode(buffer);
    return this.webMessageInfoType.toObject(decoded, { defaults: true }) as any;
  }

  public encodeClientPayload(payload: {
    username?: number;
    passive?: boolean;
    userAgent?: Buffer;
    devicePairingData?: Buffer;
  }): Buffer {
    const instance = this.clientPayloadType.create(payload);
    return Buffer.from(this.clientPayloadType.encode(instance).finish());
  }

  public decodeClientPayload(buffer: Buffer): any {
    const decoded = this.clientPayloadType.decode(buffer);
    return this.clientPayloadType.toObject(decoded, { defaults: true });
  }
}
