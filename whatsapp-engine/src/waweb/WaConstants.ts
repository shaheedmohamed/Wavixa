/**
 * WhatsApp Web protocol constants.
 * Sourced from the public WhatsApp Web protocol specification.
 */

/** Active WhatsApp Web client version reported in ClientPayload */
export const WA_WEB_VERSION: [number, number, number] = [2, 3000, 1047447330];

/** WebSocket endpoint and HTTP origin */
export const WA_WEBSOCKET_URL = 'wss://web.whatsapp.com/ws/chat';
export const WA_ORIGIN = 'https://web.whatsapp.com';

/** Noise protocol identifier (>32 bytes → hashed) */
export const NOISE_MODE = 'Noise_XX_25519_AESGCM_SHA256\0\0\0\0';

/** WABinary token dictionary version — embedded in the intro header */
export const DICT_VERSION = 3;

/** Intro header sent as the first bytes of the first frame: 'WA' + 0x06 + dict version */
export const NOISE_WA_HEADER = Buffer.from([87, 65, 6, DICT_VERSION]);

/** 0x05 version byte prefixed to public keys in signal key bundles */
export const KEY_BUNDLE_TYPE = Buffer.from([5]);

/** ADV signature domain separators */
export const WA_ADV_ACCOUNT_SIG_PREFIX = Buffer.from([6, 0]);
export const WA_ADV_DEVICE_SIG_PREFIX = Buffer.from([6, 1]);
export const WA_ADV_HOSTED_ACCOUNT_SIG_PREFIX = Buffer.from([6, 5]);
export const WA_ADV_HOSTED_DEVICE_SIG_PREFIX = Buffer.from([6, 6]);

/** WhatsApp long-term noise certificate public key (pins the cert chain) */
export const WA_CERT_DETAILS = {
  SERIAL: 0,
  ISSUER: 'WhatsAppLongTerm1',
  PUBLIC_KEY: Buffer.from(
    '142375574d0a587166aae71ebe516437c4a28b73e3695c6ce1f7f9545da8ee6b',
    'hex',
  ),
};

/** Browser description: [os, clientName, version] */
export const WA_BROWSER: [string, string, string] = ['Mac OS', 'Chrome', '14.4.1'];

export const S_WHATSAPP_NET = '@s.whatsapp.net';

/** keepalive interval (ms) — server expects pings within this window */
export const KEEP_ALIVE_INTERVAL_MS = 30_000;

/** How long a QR ref stays displayable before rotating to the next ref (ms) */
export const QR_TTL_MS = 60_000;
export const QR_TTL_SUBSEQUENT_MS = 20_000;
