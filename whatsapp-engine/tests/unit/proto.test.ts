import { describe, it, expect } from 'vitest';
import { ProtoRegistry, IMessage } from '../../src/protocol/proto/ProtoRegistry.js';

describe('Protobuf Registry Message Schemas', () => {
  const registry = ProtoRegistry.getInstance();

  it('correctly encodes and decodes plain text conversation message', () => {
    const original: IMessage = {
      conversation: 'Hello from Wavixa independent WhatsApp engine!',
    };

    const encoded = registry.encodeMessage(original);
    expect(encoded.length).toBeGreaterThan(0);

    const decoded = registry.decodeMessage(encoded);
    expect(decoded.conversation).toBe('Hello from Wavixa independent WhatsApp engine!');
  });

  it('correctly encodes and decodes media and location messages', () => {
    const original: IMessage = {
      imageMessage: {
        url: 'https://wavixa.net/media/sample.jpg',
        mimetype: 'image/jpeg',
        caption: 'Sample photo via Wavixa',
        fileLength: 1048576,
      },
      locationMessage: {
        degreesLatitude: 30.0444,
        degreesLongitude: 31.2357,
        name: 'Cairo Central',
        address: 'Tahrir, Cairo, Egypt',
      },
    };

    const encoded = registry.encodeMessage(original);
    const decoded = registry.decodeMessage(encoded);

    expect(decoded.imageMessage?.url).toBe('https://wavixa.net/media/sample.jpg');
    expect(decoded.imageMessage?.caption).toBe('Sample photo via Wavixa');
    expect(decoded.locationMessage?.name).toBe('Cairo Central');
    expect(decoded.locationMessage?.degreesLatitude).toBeCloseTo(30.0444);
  });

  it('correctly encodes and decodes WebMessageInfo wrapper with keys', () => {
    const info = {
      key: {
        remoteJid: '201011679726@s.whatsapp.net',
        fromMe: true,
        id: '3EB0TEST12345678',
      },
      message: {
        conversation: 'Ping from automated worker',
      },
      messageTimestamp: 1726315200,
      status: 2, // SERVER_ACK
    };

    const encoded = registry.encodeWebMessageInfo(info);
    const decoded = registry.decodeWebMessageInfo(encoded);

    expect(decoded.key.remoteJid).toBe('201011679726@s.whatsapp.net');
    expect(decoded.key.fromMe).toBe(true);
    expect(decoded.key.id).toBe('3EB0TEST12345678');
    expect(decoded.message.conversation).toBe('Ping from automated worker');
  });
});
