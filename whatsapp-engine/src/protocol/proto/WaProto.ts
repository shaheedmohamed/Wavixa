import path from 'path';
import fs from 'fs';
import protobuf from 'protobufjs';

/**
 * Loads the real WhatsApp Web protobuf schema (WAProto.proto).
 * The schema file lives in <repo>/proto/WAProto.proto and is loaded once,
 * lazily, on first use.
 */
let rootPromise: Promise<protobuf.Root> | null = null;

function protoFilePath(): string {
  // Works both from src/protocol/proto (tsx dev) and dist/protocol/proto (built)
  const fromHere = path.resolve(__dirname, '../../../proto/WAProto.proto');
  if (fs.existsSync(fromHere)) {
    return fromHere;
  }
  return path.resolve(process.cwd(), 'proto/WAProto.proto');
}

export async function loadWaProto(): Promise<protobuf.Root> {
  if (!rootPromise) {
    rootPromise = protobuf.load(protoFilePath());
  }
  return rootPromise;
}

export interface IKeyPair {
  private: Buffer;
  public: Buffer;
}

export const WaProto = {
  async encodeHandshakeMessage(msg: protobuf.Message | Record<string, unknown>): Promise<Buffer> {
    const root = await loadWaProto();
    const T = root.lookupType('proto.HandshakeMessage');
    return Buffer.from(T.encode(T.fromObject(msg as any)).finish());
  },

  async decodeHandshakeMessage(buf: Buffer): Promise<any> {
    const root = await loadWaProto();
    const T = root.lookupType('proto.HandshakeMessage');
    return T.toObject(T.decode(buf), { bytes: Buffer, longs: Number, defaults: false });
  },

  async encodeClientPayload(msg: Record<string, unknown>): Promise<Buffer> {
    const root = await loadWaProto();
    const T = root.lookupType('proto.ClientPayload');
    const err = T.verify(msg as any);
    if (err) {
      throw new Error(`ClientPayload verify failed: ${err}`);
    }
    return Buffer.from(T.encode(T.fromObject(msg as any)).finish());
  },

  async encodeDeviceProps(msg: Record<string, unknown>): Promise<Buffer> {
    const root = await loadWaProto();
    const T = root.lookupType('proto.DeviceProps');
    const err = T.verify(msg as any);
    if (err) {
      throw new Error(`DeviceProps verify failed: ${err}`);
    }
    return Buffer.from(T.encode(T.fromObject(msg as any)).finish());
  },

  async decodeCertChain(buf: Buffer): Promise<any> {
    const root = await loadWaProto();
    const T = root.lookupType('proto.CertChain');
    return T.toObject(T.decode(buf), { bytes: Buffer, longs: Number, defaults: false });
  },

  async decodeCertDetails(buf: Buffer): Promise<any> {
    const root = await loadWaProto();
    const T = root.lookupType('proto.CertChain.NoiseCertificate.Details');
    return T.toObject(T.decode(buf), { bytes: Buffer, longs: Number, defaults: false });
  },

  async decodeADVSignedDeviceIdentityHMAC(buf: Buffer): Promise<any> {
    const root = await loadWaProto();
    const T = root.lookupType('proto.ADVSignedDeviceIdentityHMAC');
    return T.toObject(T.decode(buf), { bytes: Buffer, longs: Number, defaults: false });
  },

  async decodeADVSignedDeviceIdentity(buf: Buffer): Promise<any> {
    const root = await loadWaProto();
    const T = root.lookupType('proto.ADVSignedDeviceIdentity');
    return T.toObject(T.decode(buf), { bytes: Buffer, longs: Number, defaults: false });
  },

  async encodeADVSignedDeviceIdentity(msg: Record<string, unknown>): Promise<Buffer> {
    const root = await loadWaProto();
    const T = root.lookupType('proto.ADVSignedDeviceIdentity');
    return Buffer.from(T.encode(T.fromObject(msg as any)).finish());
  },

  async decodeADVDeviceIdentity(buf: Buffer): Promise<any> {
    const root = await loadWaProto();
    const T = root.lookupType('proto.ADVDeviceIdentity');
    return T.toObject(T.decode(buf), { bytes: Buffer, longs: Number, defaults: false });
  },

  async encodeMessage(msg: Record<string, unknown>): Promise<Buffer> {
    const root = await loadWaProto();
    const T = root.lookupType('proto.Message');
    return Buffer.from(T.encode(T.fromObject(msg as any)).finish());
  },

  async decodeMessage(buf: Buffer): Promise<any> {
    const root = await loadWaProto();
    const T = root.lookupType('proto.Message');
    return T.toObject(T.decode(buf), { bytes: Buffer, longs: Number, defaults: false });
  },

  async decodeSenderKeyMessage(buf: Buffer): Promise<any> {
    const root = await loadWaProto();
    const T = root.lookupType('proto.SenderKeyMessage');
    return T.toObject(T.decode(buf), { bytes: Buffer, longs: Number, defaults: false });
  },

  async decodeSenderKeyDistributionMessage(buf: Buffer): Promise<any> {
    const root = await loadWaProto();
    const T = root.lookupType('proto.SenderKeyDistributionMessage');
    return T.toObject(T.decode(buf), { bytes: Buffer, longs: Number, defaults: false });
  },
};
