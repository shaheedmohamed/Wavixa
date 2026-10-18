import { Curve25519 } from '../crypto/Curve25519.js';
import { Signature } from '../crypto/Signature.js';
import { deriveSecrets } from './SignalCrypto.js';
import {
  BaseKeyType,
  ChainType,
  SessionRecord,
  sessionAddChain,
  type SessionEntry,
  type SignalKeyPair,
} from './SessionTypes.js';
import { generateSignalKeyPair, type SignalStore } from './SignalStore.js';

export interface DeviceBundle {
  registrationId: number;
  identityKey: Buffer; // 33 bytes
  signedPreKey: { keyId: number; publicKey: Buffer; signature: Buffer };
  preKey?: { keyId: number; publicKey: Buffer };
}

const agreement = (pubKey: Buffer, privKey: Buffer): Buffer =>
  Curve25519.sharedKey(privKey, pubKey.length === 33 ? pubKey.subarray(1) : pubKey);

/**
 * X3DH session setup — initiator (outgoing) and responder (incoming) paths.
 * Ported from libsignal's SessionBuilder.
 */
export class SessionBuilder {
  constructor(
    private readonly store: SignalStore,
    private readonly addr: string,
  ) {}

  public async initOutgoing(device: DeviceBundle): Promise<void> {
    if (
      !Signature.verify(
        device.identityKey.length === 33 ? device.identityKey.subarray(1) : device.identityKey,
        device.signedPreKey.publicKey.length === 33
          ? device.signedPreKey.publicKey
          : Buffer.concat([Buffer.from([5]), device.signedPreKey.publicKey]),
        device.signedPreKey.signature,
      )
    ) {
      throw new Error('Invalid signature on device signed prekey');
    }

    const baseKey = generateSignalKeyPair();
    const devicePreKey = device.preKey?.publicKey;

    const session = await this.initSession(
      true,
      baseKey,
      undefined,
      device.identityKey,
      devicePreKey,
      device.signedPreKey.publicKey,
      device.registrationId,
    );

    session.pendingPreKey = {
      signedKeyId: device.signedPreKey.keyId,
      baseKey: baseKey.pubKey,
      preKeyId: device.preKey?.keyId,
    };

    let record = this.store.loadSession(this.addr);
    if (!record) {
      record = new SessionRecord();
    } else {
      const open = record.getOpenSession();
      if (open) record.closeSession(open);
    }
    record.setSession(session);
    this.store.storeSession(this.addr, record);
  }

  public async initIncoming(
    record: SessionRecord,
    message: {
      registrationId: number;
      identityKey: Buffer;
      baseKey: Buffer;
      preKeyId?: number;
      signedPreKeyId: number;
    },
  ): Promise<number | undefined> {
    if (record.getSession(message.baseKey)) {
      return undefined; // session already exists — haven't replied yet
    }

    const preKeyPair = message.preKeyId ? this.store.loadPreKey(message.preKeyId) : undefined;
    if (message.preKeyId && !preKeyPair) {
      throw new Error('Invalid PreKey ID');
    }

    const signedPreKeyPair = this.store.loadSignedPreKey(message.signedPreKeyId);
    if (!signedPreKeyPair) {
      throw new Error('Missing SignedPreKey');
    }

    const ourSignedKey: SignalKeyPair = {
      privKey: signedPreKeyPair.keyPair.private,
      pubKey:
        signedPreKeyPair.keyPair.public.length === 33
          ? signedPreKeyPair.keyPair.public
          : Buffer.concat([Buffer.from([5]), signedPreKeyPair.keyPair.public]),
    };

    const existingOpen = record.getOpenSession();
    if (existingOpen) record.closeSession(existingOpen);

    record.setSession(
      await this.initSession(
        false,
        preKeyPair
          ? {
              privKey: preKeyPair.private,
              pubKey:
                preKeyPair.public.length === 33
                  ? preKeyPair.public
                  : Buffer.concat([Buffer.from([5]), preKeyPair.public]),
            }
          : undefined,
        ourSignedKey,
        message.identityKey,
        message.baseKey,
        undefined,
        message.registrationId,
      ),
    );

    return message.preKeyId;
  }

  private async initSession(
    isInitiator: boolean,
    ourEphemeralKey: SignalKeyPair | undefined,
    ourSignedKey: SignalKeyPair | undefined,
    theirIdentityPubKey: Buffer,
    theirEphemeralPubKey: Buffer | undefined,
    theirSignedPubKey: Buffer | undefined,
    registrationId: number | undefined,
  ): Promise<SessionEntry> {
    if (isInitiator) {
      ourSignedKey = ourEphemeralKey;
    } else {
      theirSignedPubKey = theirEphemeralPubKey;
    }

    const sharedSecret = Buffer.alloc(ourEphemeralKey && theirEphemeralPubKey ? 32 * 5 : 32 * 4);
    sharedSecret.fill(0xff, 0, 32);

    const ourIdentityKey = this.store.getOurIdentity();
    const a1 = agreement(theirSignedPubKey!, ourIdentityKey.privKey);
    const a2 = agreement(theirIdentityPubKey, ourSignedKey!.privKey);
    const a3 = agreement(theirSignedPubKey!, ourSignedKey!.privKey);

    if (isInitiator) {
      sharedSecret.set(a1, 32);
      sharedSecret.set(a2, 64);
    } else {
      sharedSecret.set(a1, 64);
      sharedSecret.set(a2, 32);
    }
    sharedSecret.set(a3, 96);

    if (ourEphemeralKey && theirEphemeralPubKey) {
      const a4 = agreement(theirEphemeralPubKey, ourEphemeralKey.privKey);
      sharedSecret.set(a4, 128);
    }

    const masterKey = deriveSecrets(sharedSecret, Buffer.alloc(32), Buffer.from('WhisperText'));

    const session = SessionRecord.createEntry();
    session.registrationId = registrationId;
    session.currentRatchet = {
      rootKey: masterKey[0]!,
      ephemeralKeyPair: isInitiator ? generateSignalKeyPair() : ourSignedKey!,
      lastRemoteEphemeralKey: theirSignedPubKey!,
      previousCounter: 0,
    };
    session.indexInfo = {
      created: Date.now(),
      used: Date.now(),
      remoteIdentityKey:
        theirIdentityPubKey.length === 33
          ? theirIdentityPubKey
          : Buffer.concat([Buffer.from([5]), theirIdentityPubKey]),
      baseKey: isInitiator ? ourEphemeralKey!.pubKey : theirEphemeralPubKey!,
      baseKeyType: isInitiator ? BaseKeyType.OURS : BaseKeyType.THEIRS,
      closed: -1,
    };

    if (isInitiator) {
      this.calculateSendingRatchet(session, theirSignedPubKey!);
    }

    return session;
  }

  private calculateSendingRatchet(session: SessionEntry, remoteKey: Buffer): void {
    const ratchet = session.currentRatchet;
    const sharedSecret = agreement(remoteKey, ratchet.ephemeralKeyPair.privKey);
    const masterKey = deriveSecrets(sharedSecret, ratchet.rootKey, Buffer.from('WhisperRatchet'));
    sessionAddChain(session, ratchet.ephemeralKeyPair.pubKey, {
      messageKeys: {},
      chainKey: { counter: -1, key: masterKey[1] },
      chainType: ChainType.SENDING,
    });
    ratchet.rootKey = masterKey[0]!;
  }
}
