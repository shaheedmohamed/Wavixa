import { describe, it, expect } from 'vitest';
import { BinaryNode } from '../../src/protocol/binary/BinaryNode.js';
import { BinaryEncoder } from '../../src/protocol/binary/Encoder.js';
import { BinaryDecoder } from '../../src/protocol/binary/Decoder.js';

describe('Binary Protocol Node Encoding & Decoding', () => {
  const encoder = new BinaryEncoder();
  const decoder = new BinaryDecoder();

  it('correctly roundtrips a simple node with token tag and attributes', () => {
    const original: BinaryNode = {
      tag: 'iq',
      attrs: {
        id: 'msg_12345',
        type: 'set',
        xmlns: 'urn:xmpp:whatsapp:push',
      },
    };

    const encoded = encoder.encode(original);
    expect(encoded.length).toBeGreaterThan(0);

    const decoded = decoder.decode(encoded);
    expect(decoded.tag).toBe(original.tag);
    expect(decoded.attrs.id).toBe('msg_12345');
    expect(decoded.attrs.type).toBe('set');
    expect(decoded.attrs.xmlns).toBe('urn:xmpp:whatsapp:push');
  });

  it('correctly roundtrips a node with binary buffer content', () => {
    const payload = Buffer.from('whatsapp-secret-media-payload-bytes');
    const original: BinaryNode = {
      tag: 'media',
      attrs: {
        type: 'image',
        mimetype: 'image/jpeg',
      },
      content: payload,
    };

    const encoded = encoder.encode(original);
    const decoded = decoder.decode(encoded);

    expect(decoded.tag).toBe('media');
    expect(decoded.attrs.type).toBe('image');
    expect(Buffer.isBuffer(decoded.content)).toBe(true);
    expect((decoded.content as Buffer).toString('utf8')).toBe(payload.toString('utf8'));
  });

  it('correctly roundtrips nested child nodes', () => {
    const original: BinaryNode = {
      tag: 'message',
      attrs: {
        to: '201011679726@s.whatsapp.net',
        type: 'text',
      },
      content: [
        {
          tag: 'body',
          attrs: {},
          content: 'Hello Wavixa from scratch',
        },
        {
          tag: 'receipt',
          attrs: {
            id: 'receipt_99',
            status: 'read',
          },
        },
      ],
    };

    const encoded = encoder.encode(original);
    const decoded = decoder.decode(encoded);

    expect(decoded.tag).toBe('message');
    expect(decoded.attrs.to).toBe('201011679726@s.whatsapp.net');
    expect(Array.isArray(decoded.content)).toBe(true);

    const children = decoded.content as BinaryNode[];
    expect(children.length).toBe(2);
    expect(children[0]?.tag).toBe('body');
    expect(children[0]?.content?.toString('utf8')).toBe('Hello Wavixa from scratch');
    expect(children[1]?.tag).toBe('receipt');
    expect(children[1]?.attrs.status).toBe('read');
  });
});
