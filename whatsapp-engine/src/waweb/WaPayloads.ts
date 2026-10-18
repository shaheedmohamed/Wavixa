import crypto from 'crypto';
import { WaProto } from '../protocol/proto/WaProto.js';
import { BinaryNode } from '../protocol/binary/BinaryNode.js';
import { jidDecode } from '../protocol/binary/jid.js';
import { Signature } from '../crypto/Signature.js';
import { KEY_BUNDLE_TYPE, WA_ADV_ACCOUNT_SIG_PREFIX, WA_ADV_DEVICE_SIG_PREFIX, WA_ADV_HOSTED_ACCOUNT_SIG_PREFIX, WA_BROWSER, WA_WEB_VERSION } from './WaConstants.js';
import type { ISignedPreKey, SignalIdentity, WaCreds } from './WaCreds.js';

const hmacSign = (buffer: Buffer, key: Buffer): Buffer =>
  crypto.createHmac('sha256', key).update(buffer).digest();

export const encodeBigEndian = (e: number, t = 4): Buffer => {
  const a = Buffer.alloc(t);
  for (let i = t - 1; i >= 0; i--) {
    a[i] = 255 & e;
    e >>>= 8;
  }
  return a;
};

export const generateMdTagPrefix = (): string => {
  const bytes = crypto.randomBytes(4);
  return `${bytes.readUInt16BE(0)}.${bytes.readUInt16BE(2)}-`;
};

// --- ClientPayload builders -------------------------------------------------

// ClientPayload.UserAgent.Platform.WEB = 14, ReleaseChannel.RELEASE = 0
// ClientPayload.ConnectType.WIFI_UNKNOWN = 1, ConnectReason.USER_ACTIVATED = 1
// ClientPayload.WebInfo.WebSubPlatform.DARWIN = 1

const getUserAgent = (): Record<string, unknown> => ({
  appVersion: {
    primary: WA_WEB_VERSION[0],
    secondary: WA_WEB_VERSION[1],
    tertiary: WA_WEB_VERSION[2],
  },
  platform: 14, // WEB
  releaseChannel: 0, // RELEASE
  osVersion: '0.1',
  device: 'Desktop',
  osBuildNumber: '0.1',
  localeLanguageIso6391: 'en',
  mnc: '000',
  mcc: '000',
  localeCountryIso31661Alpha2: 'US',
});

const getWebInfo = (): Record<string, unknown> => ({
  webSubPlatform: 0, // WEB_BROWSER (browser[1] is 'Chrome', not 'Desktop')
});

const getClientPayload = (): Record<string, unknown> => ({
  connectType: 1, // WIFI_UNKNOWN
  connectReason: 1, // USER_ACTIVATED
  userAgent: getUserAgent(),
  webInfo: getWebInfo(),
});

/** Companion device registration payload (QR / fresh pair path). */
export const generateRegistrationNode = async (
  creds: Pick<WaCreds, 'registrationId' | 'signedPreKey' | 'signedIdentityKey'>,
): Promise<Buffer> => {
  const appVersionBuf = crypto
    .createHash('md5')
    .update(WA_WEB_VERSION.join('.'))
    .digest();

  const companion = {
    os: WA_BROWSER[0],
    platformType: 1, // CHROME
    requireFullSync: true,
    historySyncConfig: {
      storageQuotaMb: 10240,
      inlineInitialPayloadInE2EeMsg: true,
      supportCallLogHistory: false,
      supportBotUserAgentChatHistory: true,
      supportCagReactionsAndPolls: true,
      supportBizHostedMsg: true,
      supportRecentSyncChunkMessageCountTuning: true,
      supportHostedGroupMsg: true,
      supportFbidBotChatHistory: true,
      supportMessageAssociation: true,
      supportGroupHistory: false,
    },
    version: { primary: 10, secondary: 15, tertiary: 7 },
  };

  const companionProto = await WaProto.encodeDeviceProps(companion);

  return WaProto.encodeClientPayload({
    ...getClientPayload(),
    passive: false,
    pull: false,
    devicePairingData: {
      buildHash: appVersionBuf,
      deviceProps: companionProto,
      eRegid: encodeBigEndian(creds.registrationId),
      eKeytype: KEY_BUNDLE_TYPE,
      eIdent: creds.signedIdentityKey.public,
      eSkeyId: encodeBigEndian(creds.signedPreKey.keyId, 3),
      eSkeyVal: creds.signedPreKey.keyPair.public,
      eSkeySig: creds.signedPreKey.signature,
    },
  });
};

/** Login payload for an already-registered companion device. */
export const generateLoginNode = async (userJid: string): Promise<Buffer> => {
  const { user, device } = jidDecode(userJid)!;
  return WaProto.encodeClientPayload({
    ...getClientPayload(),
    passive: true,
    pull: true,
    username: +user,
    device: device,
    lidDbMigrated: false,
  });
};

// --- Pairing ----------------------------------------------------------------

enum CompanionWebClientType {
  CHROME = 1,
  EDGE = 2,
  FIREFOX = 3,
  SAFARI = 6,
  ELECTRON = 7,
  UWP = 8,
  OTHER_WEB_CLIENT = 9,
}

const getCompanionWebClientType = (): CompanionWebClientType => {
  const [os, browserName] = WA_BROWSER;
  if (browserName === 'Desktop') {
    return os === 'Windows' ? CompanionWebClientType.UWP : CompanionWebClientType.ELECTRON;
  }
  const map: Record<string, CompanionWebClientType> = {
    Chrome: CompanionWebClientType.CHROME,
    Edge: CompanionWebClientType.EDGE,
    Firefox: CompanionWebClientType.FIREFOX,
    Safari: CompanionWebClientType.SAFARI,
  };
  return map[browserName] || CompanionWebClientType.OTHER_WEB_CLIENT;
};

export const getCompanionPlatformId = (): string => getCompanionWebClientType().toString();
export const getCompanionPlatformDisplay = (): string => `${WA_BROWSER[1]} (${WA_BROWSER[0]})`;

export const buildPairingQRData = (
  ref: string,
  noiseKeyB64: string,
  identityKeyB64: string,
  advB64: string,
): string =>
  'https://wa.me/settings/linked_devices#' +
  [ref, noiseKeyB64, identityKeyB64, advB64, getCompanionPlatformId()].join(',');

// --- Pair success processing ------------------------------------------------

const getBinaryNodeChild = (node: BinaryNode | undefined, tag: string): BinaryNode | undefined => {
  if (node && Array.isArray(node.content)) {
    return node.content.find((c) => c.tag === tag);
  }
  return undefined;
};

export interface PairSuccessResult {
  reply: BinaryNode;
  credsUpdate: Partial<WaCreds>;
}

export const configureSuccessfulPairing = async (
  stanza: BinaryNode,
  creds: Pick<WaCreds, 'advSecretKey' | 'signedIdentityKey' | 'signalIdentities'>,
): Promise<PairSuccessResult> => {
  const msgId = stanza.attrs.id;

  const pairSuccessNode = getBinaryNodeChild(stanza, 'pair-success');
  const deviceIdentityNode = getBinaryNodeChild(pairSuccessNode, 'device-identity');
  const platformNode = getBinaryNodeChild(pairSuccessNode, 'platform');
  const deviceNode = getBinaryNodeChild(pairSuccessNode, 'device');
  const businessNode = getBinaryNodeChild(pairSuccessNode, 'biz');

  if (!deviceIdentityNode || !deviceNode) {
    throw new Error('Missing device-identity or device in pair success node');
  }

  const bizName = businessNode?.attrs.name;
  const jid = deviceNode.attrs.jid;
  const lid = deviceNode.attrs.lid;

  const { details, hmac, accountType } = await WaProto.decodeADVSignedDeviceIdentityHMAC(
    deviceIdentityNode.content as Buffer,
  );

  let hmacPrefix = Buffer.from([]);
  if (accountType !== undefined && accountType === 1) {
    hmacPrefix = WA_ADV_HOSTED_ACCOUNT_SIG_PREFIX;
  }

  const advSign = hmacSign(
    Buffer.concat([hmacPrefix, details]),
    Buffer.from(creds.advSecretKey, 'base64'),
  );
  if (Buffer.compare(hmac, advSign) !== 0) {
    throw new Error('Invalid account signature');
  }

  const account = await WaProto.decodeADVSignedDeviceIdentity(details);
  const { accountSignatureKey, accountSignature, details: deviceDetails } = account;

  const deviceIdentity = await WaProto.decodeADVDeviceIdentity(deviceDetails);

  const accountSignaturePrefix =
    deviceIdentity.deviceType === 1 ? WA_ADV_HOSTED_ACCOUNT_SIG_PREFIX : WA_ADV_ACCOUNT_SIG_PREFIX;
  const accountMsg = Buffer.concat([
    accountSignaturePrefix,
    deviceDetails,
    creds.signedIdentityKey.public,
  ]);
  if (!Signature.verify(accountSignatureKey, accountMsg, accountSignature)) {
    throw new Error('Failed to verify account signature');
  }

  const deviceMsg = Buffer.concat([
    WA_ADV_DEVICE_SIG_PREFIX,
    deviceDetails,
    creds.signedIdentityKey.public,
    accountSignatureKey,
  ]);
  account.deviceSignature = Signature.sign(creds.signedIdentityKey.private, deviceMsg);

  const identity: SignalIdentity = {
    identifier: { name: lid!, deviceId: 0 },
    identifierKey: Signature.prefixedPubKey(accountSignatureKey),
  };

  // Encode signed device identity without the signature key
  const accountToEncode = { ...account, accountSignatureKey: null };
  const accountEnc = await WaProto.encodeADVSignedDeviceIdentity(accountToEncode);

  const reply: BinaryNode = {
    tag: 'iq',
    attrs: {
      to: 's.whatsapp.net',
      type: 'result',
      id: msgId!,
    },
    content: [
      {
        tag: 'pair-device-sign',
        attrs: {},
        content: [
          {
            tag: 'device-identity',
            attrs: { 'key-index': deviceIdentity.keyIndex!.toString() },
            content: accountEnc,
          },
        ],
      },
    ],
  };

  return {
    reply,
    credsUpdate: {
      account,
      me: { id: jid!, name: bizName, lid },
      signalIdentities: [...(creds.signalIdentities || []), identity],
      platform: platformNode?.attrs.name,
    },
  };
};

// --- Pre-key upload ----------------------------------------------------------

export const xmppSignedPreKey = (key: ISignedPreKey): BinaryNode => ({
  tag: 'skey',
  attrs: {},
  content: [
    { tag: 'id', attrs: {}, content: encodeBigEndian(key.keyId, 3) },
    { tag: 'value', attrs: {}, content: key.keyPair.public },
    { tag: 'signature', attrs: {}, content: key.signature },
  ],
});

export const xmppPreKey = (keyPair: { public: Buffer }, id: number): BinaryNode => ({
  tag: 'key',
  attrs: {},
  content: [
    { tag: 'id', attrs: {}, content: encodeBigEndian(id, 3) },
    { tag: 'value', attrs: {}, content: keyPair.public },
  ],
});
