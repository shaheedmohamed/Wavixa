import crypto from 'crypto';
import { Curve25519, KeyPair } from '../crypto/Curve25519.js';
import { Ed25519 } from '../crypto/Ed25519.js';

export interface ISignedPreKey {
  keyId: number;
  keyPair: KeyPair;
  signature: Buffer;
}

export interface IPublicDeviceIdentity {
  registrationId: number;
  deviceId: number;
  identityPublicKey: string; // hex
  noisePublicKey: string; // hex
  signedPreKey: {
    keyId: number;
    publicKey: string; // hex
    signature: string; // hex
  };
}

export class DeviceIdentityManager {
  private registrationId: number;
  private deviceId: number;
  private identityKeyPair: KeyPair;
  private noiseKeyPair: KeyPair;
  private signedPreKey: ISignedPreKey;
  private oneTimePreKeys: Map<number, KeyPair> = new Map();

  constructor(
    registrationId?: number,
    deviceId: number = 0,
    identityKeyPair?: KeyPair,
    noiseKeyPair?: KeyPair,
    signedPreKey?: ISignedPreKey,
  ) {
    this.registrationId = registrationId ?? this.generateRegistrationId();
    this.deviceId = deviceId;
    this.identityKeyPair = identityKeyPair ?? Ed25519.generateKeyPair();
    this.noiseKeyPair = noiseKeyPair ?? Curve25519.generateKeyPair();

    if (signedPreKey) {
      this.signedPreKey = signedPreKey;
    } else {
      this.signedPreKey = this.generateSignedPreKey(1);
    }
  }

  public getRegistrationId(): number {
    return this.registrationId;
  }

  public getDeviceId(): number {
    return this.deviceId;
  }

  public getIdentityKeyPair(): KeyPair {
    return this.identityKeyPair;
  }

  public getNoiseKeyPair(): KeyPair {
    return this.noiseKeyPair;
  }

  public getSignedPreKey(): ISignedPreKey {
    return this.signedPreKey;
  }

  public generateSignedPreKey(keyId: number): ISignedPreKey {
    const keyPair = Curve25519.generateKeyPair();
    // Sign Curve25519 public key with Ed25519 private identity key
    const signature = Ed25519.sign(this.identityKeyPair.privateKey, keyPair.publicKey);
    return {
      keyId,
      keyPair,
      signature,
    };
  }

  public generateOneTimePreKeys(startId: number, count: number = 100): void {
    for (let i = 0; i < count; i++) {
      const id = startId + i;
      this.oneTimePreKeys.set(id, Curve25519.generateKeyPair());
    }
  }

  public getOneTimePreKey(id: number): KeyPair | undefined {
    return this.oneTimePreKeys.get(id);
  }

  public consumeOneTimePreKey(id: number): KeyPair | undefined {
    const key = this.oneTimePreKeys.get(id);
    if (key) {
      this.oneTimePreKeys.delete(id);
    }
    return key;
  }

  /**
   * Safe public representation for pairing and API responses.
   * STRICT GUARANTEE: Never contains private keys.
   */
  public getPublicIdentity(): IPublicDeviceIdentity {
    return {
      registrationId: this.registrationId,
      deviceId: this.deviceId,
      identityPublicKey: this.identityKeyPair.publicKey.toString('hex'),
      noisePublicKey: this.noiseKeyPair.publicKey.toString('hex'),
      signedPreKey: {
        keyId: this.signedPreKey.keyId,
        publicKey: this.signedPreKey.keyPair.publicKey.toString('hex'),
        signature: this.signedPreKey.signature.toString('hex'),
      },
    };
  }

  /**
   * Safe serialization for logs / inspect.
   * Completely omits private keys.
   */
  public toJSON(): IPublicDeviceIdentity {
    return this.getPublicIdentity();
  }

  private generateRegistrationId(): number {
    const buf = crypto.randomBytes(2);
    return (buf.readUInt16BE(0) & 0x3fff) + 1; // 1 to 16384
  }
}
