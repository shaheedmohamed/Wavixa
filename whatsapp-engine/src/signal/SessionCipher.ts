import { loadWaProto } from '../protocol/proto/WaProto.js';
import {
  calculateMAC,
  deriveSecrets,
  signalDecrypt,
  signalEncrypt,
  verifyMAC,
} from './SignalCrypto.js';
import {
  ChainType,
  SessionRecord,
  sessionAddChain,
  sessionDeleteChain,
  sessionGetChain,
  type Chain,
  type SessionEntry,
} from './SessionTypes.js';
import { SessionBuilder } from './SessionBuilder.js';
import { generateSignalKeyPair, type SignalStore } from './SignalStore.js';
import { Curve25519 } from '../crypto/Curve25519.js';

const VERSION = 3;

const encodeTupleByte = (a: number, b: number): number => (a << 4) | b;
const decodeTupleByte = (b: number): [number, number] => [b >> 4, b & 0xf];

const agreement = (pubKey: Buffer, privKey: Buffer): Buffer =>
  Curve25519.sharedKey(privKey, pubKey.length === 33 ? pubKey.subarray(1) : pubKey);

export interface EncryptResult {
  type: 'pkmsg' | 'msg';
  body: Buffer;
  registrationId?: number;
}

/**
 * Double-ratchet session cipher — port of libsignal's SessionCipher.
 */
export class SessionCipher {
  constructor(
    private readonly store: SignalStore,
    private readonly addr: string,
  ) {}

  public async hasOpenSession(): Promise<boolean> {
    return this.store.hasSession(this.addr);
  }

  public async encrypt(data: Buffer): Promise<EncryptResult> {
    const ourIdentityKey = this.store.getOurIdentity();
    const record = this.store.loadSession(this.addr);
    if (!record) throw new Error('No sessions');
    const session = record.getOpenSession();
    if (!session) throw new Error('No open session');

    const remoteIdentityKey = session.indexInfo.remoteIdentityKey;
    const chain = sessionGetChain(session, session.currentRatchet.ephemeralKeyPair.pubKey);
    if (!chain) throw new Error('No sending chain');
    if (chain.chainType === ChainType.RECEIVING) {
      throw new Error('Tried to encrypt on a receiving chain');
    }

    this.fillMessageKeys(chain, chain.chainKey.counter + 1);
    const keys = deriveSecrets(
      chain.messageKeys[chain.chainKey.counter]!,
      Buffer.alloc(32),
      Buffer.from('WhisperMessageKeys'),
    );
    delete chain.messageKeys[chain.chainKey.counter];

    const root = await loadWaProto();
    const WMsg = root.lookupType('proto.SignalMessage');
    const msg = WMsg.create({
      ratchetKey: session.currentRatchet.ephemeralKeyPair.pubKey,
      counter: chain.chainKey.counter,
      previousCounter: session.currentRatchet.previousCounter,
      ciphertext: signalEncrypt(keys[0]!, data, keys[2]!.subarray(0, 16)),
    });
    const msgBuf = Buffer.from(WMsg.encode(msg).finish());

    const macInput = Buffer.alloc(msgBuf.byteLength + 33 * 2 + 1);
    macInput.set(ourIdentityKey.pubKey, 0);
    macInput.set(remoteIdentityKey, 33);
    macInput[33 * 2] = encodeTupleByte(VERSION, VERSION);
    macInput.set(msgBuf, 33 * 2 + 1);
    const mac = calculateMAC(keys[1]!, macInput);

    const result = Buffer.alloc(msgBuf.byteLength + 9);
    result[0] = encodeTupleByte(VERSION, VERSION);
    result.set(msgBuf, 1);
    result.set(mac.subarray(0, 8), msgBuf.byteLength + 1);

    let type: 'pkmsg' | 'msg';
    let body: Buffer;
    if (session.pendingPreKey) {
      type = 'pkmsg';
      const PKMsg = root.lookupType('proto.PreKeySignalMessage');
      const preKeyMsg = PKMsg.create({
        identityKey: ourIdentityKey.pubKey,
        registrationId: this.store.getOurRegistrationId(),
        baseKey: session.pendingPreKey.baseKey,
        signedPreKeyId: session.pendingPreKey.signedKeyId,
        preKeyId: session.pendingPreKey.preKeyId,
        message: result,
      });
      body = Buffer.concat([
        Buffer.from([encodeTupleByte(VERSION, VERSION)]),
        Buffer.from(PKMsg.encode(preKeyMsg).finish()),
      ]);
    } else {
      type = 'msg';
      body = result;
    }

    this.store.storeSession(this.addr, record);
    return { type, body, registrationId: session.registrationId };
  }

  public async decryptPreKeyWhisperMessage(data: Buffer): Promise<Buffer> {
    const versions = decodeTupleByte(data[0]!);
    if (versions[1] > 3 || versions[0] < 3) {
      throw new Error('Incompatible version on PreKeySignalMessage');
    }

    const root = await loadWaProto();
    const PKMsg = root.lookupType('proto.PreKeySignalMessage');
    const preKeyProto: any = PKMsg.toObject(PKMsg.decode(data.subarray(1)), {
      bytes: Buffer,
      longs: Number,
      defaults: false,
    });

    let record = this.store.loadSession(this.addr);
    if (!record) {
      if (preKeyProto.registrationId == null) throw new Error('No registrationId');
      record = new SessionRecord();
    }

    const builder = new SessionBuilder(this.store, this.addr);
    const preKeyId = await builder.initIncoming(record, {
      registrationId: preKeyProto.registrationId,
      identityKey: Buffer.from(preKeyProto.identityKey),
      baseKey: Buffer.from(preKeyProto.baseKey),
      preKeyId: preKeyProto.preKeyId,
      signedPreKeyId: preKeyProto.signedPreKeyId,
    });

    const session = record.getSession(Buffer.from(preKeyProto.baseKey));
    const plaintext = await this.doDecryptWhisperMessage(
      Buffer.from(preKeyProto.message),
      session!,
    );
    this.store.storeSession(this.addr, record);
    if (preKeyId) this.store.removePreKey(preKeyId);
    return plaintext;
  }

  public async decryptWhisperMessage(data: Buffer): Promise<Buffer> {
    const record = this.store.loadSession(this.addr);
    if (!record) throw new Error('No session record');
    const sessions = record.getSessions();
    if (!sessions.length) throw new Error('No sessions available');

    const errs: Error[] = [];
    for (const session of sessions) {
      try {
        const plaintext = await this.doDecryptWhisperMessage(data, session);
        session.indexInfo.used = Date.now();
        this.store.storeSession(this.addr, record);
        return plaintext;
      } catch (e) {
        errs.push(e as Error);
      }
    }
    throw new Error(
      `No matching session: ${errs.map((e) => e.message).join('; ')}`,
    );
  }

  private async doDecryptWhisperMessage(
    messageBuffer: Buffer,
    session: SessionEntry,
  ): Promise<Buffer> {
    const versions = decodeTupleByte(messageBuffer[0]!);
    if (versions[1] > 3 || versions[0] < 3) {
      throw new Error('Incompatible version on SignalMessage');
    }
    const messageProto = messageBuffer.subarray(1, -8);

    const root = await loadWaProto();
    const WMsg = root.lookupType('proto.SignalMessage');
    const message: any = WMsg.toObject(WMsg.decode(messageProto), {
      bytes: Buffer,
      longs: Number,
      defaults: false,
    });

    const remoteKey = Buffer.from(message.ratchetKey);
    this.maybeStepRatchet(session, remoteKey, message.previousCounter);
    const chain = sessionGetChain(session, remoteKey);
    if (!chain) throw new Error('No chain for remote key');
    if (chain.chainType === ChainType.SENDING) {
      throw new Error('Tried to decrypt on a sending chain');
    }

    this.fillMessageKeys(chain, message.counter);
    if (!chain.messageKeys[message.counter]) {
      throw new Error('Key used already or never filled');
    }
    const messageKey = chain.messageKeys[message.counter]!;
    delete chain.messageKeys[message.counter];

    const keys = deriveSecrets(messageKey, Buffer.alloc(32), Buffer.from('WhisperMessageKeys'));
    const ourIdentityKey = this.store.getOurIdentity();

    const macInput = Buffer.alloc(messageProto.byteLength + 33 * 2 + 1);
    macInput.set(session.indexInfo.remoteIdentityKey, 0);
    macInput.set(ourIdentityKey.pubKey, 33);
    macInput[33 * 2] = encodeTupleByte(VERSION, VERSION);
    macInput.set(messageProto, 33 * 2 + 1);
    verifyMAC(macInput, keys[1]!, messageBuffer.subarray(-8), 8);

    const plaintext = signalDecrypt(keys[0]!, Buffer.from(message.ciphertext), keys[2]!.subarray(0, 16));
    delete session.pendingPreKey;
    return plaintext;
  }

  private fillMessageKeys(chain: Chain, counter: number): void {
    if (chain.chainKey.counter >= counter) return;
    if (counter - chain.chainKey.counter > 2000) {
      throw new Error('Over 2000 messages into the future!');
    }
    if (chain.chainKey.key === undefined) {
      throw new Error('Chain closed');
    }
    const key = chain.chainKey.key;
    chain.messageKeys[chain.chainKey.counter + 1] = calculateMAC(key, Buffer.from([1]));
    chain.chainKey.key = calculateMAC(key, Buffer.from([2]));
    chain.chainKey.counter += 1;
    return this.fillMessageKeys(chain, counter);
  }

  private maybeStepRatchet(session: SessionEntry, remoteKey: Buffer, previousCounter: number): void {
    if (sessionGetChain(session, remoteKey)) return;
    const ratchet = session.currentRatchet;
    const previousRatchet = sessionGetChain(session, ratchet.lastRemoteEphemeralKey);
    if (previousRatchet) {
      this.fillMessageKeys(previousRatchet, previousCounter);
      delete previousRatchet.chainKey.key;
    }
    this.calculateRatchet(session, remoteKey, false);

    const prevCounter = sessionGetChain(session, ratchet.ephemeralKeyPair.pubKey);
    if (prevCounter) {
      ratchet.previousCounter = prevCounter.chainKey.counter;
      sessionDeleteChain(session, ratchet.ephemeralKeyPair.pubKey);
    }
    ratchet.ephemeralKeyPair = generateSignalKeyPair();
    this.calculateRatchet(session, remoteKey, true);
    ratchet.lastRemoteEphemeralKey = remoteKey;
  }

  private calculateRatchet(session: SessionEntry, remoteKey: Buffer, sending: boolean): void {
    const ratchet = session.currentRatchet;
    const sharedSecret = agreement(remoteKey, ratchet.ephemeralKeyPair.privKey);
    const masterKey = deriveSecrets(sharedSecret, ratchet.rootKey, Buffer.from('WhisperRatchet'), 2);
    const chainKey = sending ? ratchet.ephemeralKeyPair.pubKey : remoteKey;
    sessionAddChain(session, chainKey, {
      messageKeys: {},
      chainKey: { counter: -1, key: masterKey[1] },
      chainType: sending ? ChainType.SENDING : ChainType.RECEIVING,
    });
    ratchet.rootKey = masterKey[0]!;
  }
}
