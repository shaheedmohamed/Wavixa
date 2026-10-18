import axios from 'axios';
import zlib from 'zlib';
import protobuf from 'protobufjs';
import { loadWaProto } from '../protocol/proto/WaProto.js';
import { MediaManager } from './MediaManager.js';
import { logger } from '../config/Logger.js';

export interface NormalizedMedia {
  mediaKey?: Buffer;
  directPath?: string;
  url?: string;
  mimetype?: string;
  fileName?: string;
  fileLength?: number;
  caption?: string;
  seconds?: number;
  fileSha256?: Buffer;
}

export interface NormalizedMessage {
  type: string;
  body: string;
  media?: NormalizedMedia;
  quotedMessageId?: string;
  quotedParticipant?: string;
  isForwarded?: boolean;
  edited?: boolean;
  fromMe?: boolean;
  destinationJid?: string;
}

export interface NormalizedHistoryMessage extends NormalizedMessage {
  id: string;
  remoteJid: string;
  participant?: string;
  pushName?: string;
  timestamp: number;
  status: number;
}

export interface ParsedHistorySync {
  syncType: number;
  chunkOrder?: number;
  progress?: number;
  requestId?: string | null;
  conversations: Array<{
    jid: string;
    name?: string;
    username?: string;
    unreadCount?: number;
    lastMsgTimestamp?: number;
    archived?: boolean;
    messages: NormalizedHistoryMessage[];
  }>;
  pushnames: Array<{ jid: string; pushname: string }>;
  lidMappings: Array<{ pn: string; lid: string }>;
  /** INITIAL_STATUS_V3 chunks carry raw WebMessageInfo statuses, not conversations. */
  statusMessages?: NormalizedHistoryMessage[];
}

/** Wrappers whose inner `message` field carries the real content. */
const FUTURE_PROOF_WRAPPERS = [
  'ephemeralMessage',
  'viewOnceMessage',
  'viewOnceMessageV2',
  'viewOnceMessageV2Extension',
  'documentWithCaptionMessage',
  'editedMessage',
] as const;

/**
 * Peel WhatsApp's FutureProofMessage wrappers (ephemeral, view-once,
 * edits, document-with-caption, device-sent) down to the inner payload.
 */
export function unwrapMessage(decoded: any): {
  message: any;
  fromMe: boolean;
  destinationJid?: string;
  edited: boolean;
} {
  let message = decoded;
  let fromMe = false;
  let destinationJid: string | undefined;
  let edited = false;

  if (message?.deviceSentMessage?.message) {
    destinationJid = message.deviceSentMessage.destinationJid;
    message = message.deviceSentMessage.message;
    fromMe = true;
  }

  for (let i = 0; i < 6; i++) {
    const wrapper = FUTURE_PROOF_WRAPPERS.find((w) => message?.[w]?.message);
    if (!wrapper) break;
    if (wrapper === 'editedMessage') edited = true;
    message = message[wrapper].message;
  }

  if (message?.protocolMessage?.editedMessage) {
    message = message.protocolMessage.editedMessage;
    edited = true;
    // The edited payload may itself sit inside a wrapper.
    for (let i = 0; i < 6; i++) {
      const wrapper = FUTURE_PROOF_WRAPPERS.find((w) => message?.[w]?.message);
      if (!wrapper) break;
      message = message[wrapper].message;
    }
  }

  return { message, fromMe, destinationJid, edited };
}

const MEDIA_FIELDS: Array<[string, string]> = [
  ['imageMessage', 'image'],
  ['videoMessage', 'video'],
  ['audioMessage', 'audio'],
  ['documentMessage', 'document'],
  ['stickerMessage', 'sticker'],
];

const CONTENT_FIELDS = [
  'conversation',
  'extendedTextMessage',
  'imageMessage',
  'videoMessage',
  'audioMessage',
  'documentMessage',
  'stickerMessage',
  'reactionMessage',
  'locationMessage',
  'liveLocationMessage',
  'contactMessage',
  'contactsArrayMessage',
  'pollCreationMessage',
  'pollCreationMessageV2',
  'pollCreationMessageV3',
  'eventMessage',
];

/**
 * Reduce a decoded proto.Message to a normalized CRM-friendly shape.
 * Returns null for protocol/key-distribution frames with no user-visible
 * content — those must not be surfaced as chat messages.
 */
export function normalizeMessageContent(message: any): NormalizedMessage | null {
  if (!message || typeof message !== 'object') return null;

  const ctxs = MEDIA_FIELDS.concat([['extendedTextMessage', '']])
    .map(([f]) => message?.[f]?.contextInfo)
    .filter((c) => c != null);
  const ctx = ctxs.find((c) => c?.stanzaId);
  const quotedMessageId = ctx?.stanzaId || undefined;
  const quotedParticipant = ctx?.participant || undefined;
  // WhatsApp flags forwarded content via contextInfo.isForwarded — surface it
  // so the CRM can show the same "Forwarded" label as the official client.
  const isForwarded = ctxs.some((c) => c?.isForwarded === true) || undefined;
  const quoted = { quotedMessageId, quotedParticipant, isForwarded };

  if (message.reactionMessage) {
    return { type: 'reaction', body: message.reactionMessage.text || '', ...quoted };
  }

  for (const [field, type] of MEDIA_FIELDS) {
    const m = message[field];
    if (!m) continue;
    const resolvedType = field === 'audioMessage' && m.ptt ? 'voice' : type;
    return {
      type: resolvedType,
      body: m.caption || m.fileName || '',
      media: {
        mediaKey: m.mediaKey,
        directPath: m.directPath,
        url: m.url,
        mimetype: m.mimetype,
        fileName: m.fileName,
        fileLength: m.fileLength != null ? Number(m.fileLength) : undefined,
        caption: m.caption,
        seconds: m.seconds != null ? Number(m.seconds) : undefined,
        fileSha256: m.fileSha256,
      },
      ...quoted,
    };
  }

  if (message.locationMessage) {
    const l = message.locationMessage;
    return {
      type: 'location',
      body: l.name || l.address || `${l.degreesLatitude},${l.degreesLongitude}`,
      ...quoted,
    };
  }

  if (message.liveLocationMessage) {
    const l = message.liveLocationMessage;
    return { type: 'location', body: l.caption || 'مشاركة الموقع المباشر', ...quoted };
  }

  if (message.contactMessage) {
    return {
      type: 'contact',
      body: message.contactMessage.displayName || 'جهة اتصال',
      ...quoted,
    };
  }

  if (message.contactsArrayMessage) {
    return {
      type: 'contact',
      body: message.contactsArrayMessage.displayName || 'جهات اتصال',
      ...quoted,
    };
  }

  const text = message.conversation || message.extendedTextMessage?.text;
  if (text) {
    return { type: 'text', body: text, ...quoted };
  }

  if (message.eventMessage) {
    return { type: 'event', body: message.eventMessage.name || 'حدث', ...quoted };
  }

  const hasContent = CONTENT_FIELDS.some((f) => message[f] != null);
  if (!hasContent) return null;

  return { type: 'other', body: '', ...quoted };
}

/** Normalize a HistorySyncMsg's WebMessageInfo into the CRM message shape. */
export function normalizeWebMessageInfo(wmi: any): NormalizedHistoryMessage | null {
  const key = wmi?.key;
  if (!key?.id || !key?.remoteJid) return null;

  const { message, fromMe, destinationJid, edited } = unwrapMessage(wmi.message);
  const norm = normalizeMessageContent(message);
  if (!norm) return null;

  return {
    ...norm,
    edited,
    id: key.id,
    remoteJid: destinationJid || key.remoteJid,
    participant: wmi.participant || key.participant || undefined,
    pushName: wmi.pushName || undefined,
    fromMe: Boolean(key.fromMe) || fromMe,
    timestamp: Number(wmi.messageTimestamp) || 0,
    status: Number(wmi.status) || 0,
  };
}

async function decodeHistorySync(buf: Buffer): Promise<any> {
  const root = await loadWaProto();
  const convT = root.lookupType('proto.Conversation');
  const pushT = root.lookupType('proto.Pushname');
  const lidMapT = root.lookupType('proto.PhoneNumberToLIDMapping');
  const wmiT = root.lookupType('proto.WebMessageInfo');

  // Decode HistorySync field-by-field instead of via the generated decoder:
  // some repeated sub-messages on the wire no longer match the bundled schema
  // (e.g. phoneNumberToLidMappings), and a single generated decode failure
  // would drop the whole chunk. Skipping them at wire level keeps everything
  // else intact.
  const toObj = { bytes: Buffer, longs: Number, defaults: false } as const;
  const out: any = { conversations: [], pushnames: [], lidMappings: [] };
  const reader = protobuf.Reader.create(buf);
  const end = reader.len;

  while (reader.pos < end) {
    try {
      const tag = reader.uint32();
      const fieldNo = tag >>> 3;
      const wire = tag & 7;
      switch (fieldNo) {
        case 1: // syncType (varint)
          out.syncType = reader.uint32();
          break;
        case 2: { // repeated Conversation
          const len = reader.uint32();
          const pos = reader.pos;
          try {
            const c = convT.decode(reader, len);
            out.conversations.push(convT.toObject(c, toObj));
          } catch (e) {
            logger.warn({ err: e, fieldNo, len }, 'Skipped undecodable history conversation');
            reader.pos = pos + len; // skip the malformed entry entirely
          }
          break;
        }
        case 3: { // repeated WebMessageInfo (INITIAL_STATUS_V3 statuses)
          const len = reader.uint32();
          const pos = reader.pos;
          try {
            const w = wmiT.decode(reader, len);
            (out.statusV3Messages = out.statusV3Messages || []).push(
              wmiT.toObject(w, toObj),
            );
          } catch {
            reader.pos = pos + len;
          }
          break;
        }
        case 5:
          out.chunkOrder = reader.uint32();
          break;
        case 6:
          out.progress = reader.uint32();
          break;
        case 7: { // repeated Pushname
          const len = reader.uint32();
          const pos = reader.pos;
          try {
            const p = pushT.decode(reader, len);
            out.pushnames.push(pushT.toObject(p, toObj));
          } catch {
            reader.pos = pos + len;
          }
          break;
        }
        case 15: { // repeated PhoneNumberToLIDMapping
          const len = reader.uint32();
          const pos = reader.pos;
          try {
            const m = lidMapT.decode(reader, len);
            out.lidMappings.push(lidMapT.toObject(m, toObj));
          } catch {
            reader.pos = pos + len;
          }
          break;
        }
        default:
          reader.skipType(wire);
          break;
      }
    } catch {
      // A single malformed/unknown-versioned entry must not kill the chunk.
      break;
    }
  }
  return out;
}

/**
 * Resolve a HistorySyncNotification into the parsed HistorySync blob.
 * Initial bootstrap arrives inline; FULL/RECENT/ON_DEMAND chunks arrive as
 * media-encrypted blobs on the WhatsApp CDN (md-msg-hist ⇒ History HKDF).
 */
export async function processHistorySyncNotification(
  notif: any,
): Promise<ParsedHistorySync | null> {
  let blob: Buffer | null = null;

  if (notif.initialHistBootstrapInlinePayload) {
    blob = Buffer.from(notif.initialHistBootstrapInlinePayload);
  } else if (notif.directPath && notif.mediaKey) {
    const url = `https://mmg.whatsapp.net${notif.directPath}`;
    const resp = await axios.get(url, {
      responseType: 'arraybuffer',
      timeout: 60_000,
      maxContentLength: 256 * 1024 * 1024,
    });
    blob = MediaManager.decryptMedia(
      Buffer.from(resp.data),
      Buffer.from(notif.mediaKey),
      'history',
    );
  }

  if (!blob) {
    logger.warn({ syncType: notif.syncType }, 'History sync notification had no payload');
    return null;
  }

  // On-demand chunks arrive zlib-compressed (0x78 deflate header); inline
  // bootstrap payloads are raw protobuf.
  let decoded = blob;
  if (blob.length > 2 && blob[0] === 0x78) {
    try {
      decoded = zlib.inflateSync(blob);
    } catch {
      // not actually compressed — decode as-is
    }
  }

  const history = await decodeHistorySync(decoded);
  logger.debug(
    {
      blobLen: blob.length,
      decodedConversations: history.conversations?.length,
      decodedPushnames: history.pushnames?.length,
      decodedSyncType: history.syncType,
    },
    'History sync blob decoded',
  );
  const syncType = Number(notif.syncType ?? history.syncType ?? 0);

  const conversations = (history.conversations || [])
    .filter((c: any) => c?.id)
    .map((c: any) => ({
      jid: c.id,
      name: c.name || undefined,
      username: c.username || undefined,
      unreadCount: c.unreadCount != null ? Number(c.unreadCount) : undefined,
      lastMsgTimestamp: c.lastMsgTimestamp != null ? Number(c.lastMsgTimestamp) : undefined,
      archived: c.archived === true,
      messages: (c.messages || [])
        .map((m: any) => normalizeWebMessageInfo(m?.message))
        .filter((m: NormalizedHistoryMessage | null): m is NormalizedHistoryMessage => m !== null),
    }));

  return {
    syncType,
    chunkOrder: notif.chunkOrder != null ? Number(notif.chunkOrder) : history.chunkOrder,
    progress: notif.progress != null ? Number(notif.progress) : history.progress,
    // Correlates chunks belonging to one sync run — full-history on-demand
    // pulls carry our generated requestId, letting Laravel reset its synced
    // counters per run instead of accumulating across unrelated syncs.
    requestId:
      (notif.peerDataRequestSessionId as string | undefined) ||
      (notif.fullHistorySyncOnDemandRequestMetadata?.requestId as
        | string
        | undefined) ||
      null,
    conversations,
    pushnames: (history.pushnames || [])
      .filter((p: any) => p?.id && p?.pushname)
      .map((p: any) => ({ jid: p.id, pushname: p.pushname })),
    lidMappings: (history.lidMappings || [])
      .filter((m: any) => m?.lidJid && m?.pnJid)
      .map((m: any) => ({ lid: m.lidJid, pn: m.pnJid })),
    statusMessages: (history.statusV3Messages || [])
      .map((w: any) => normalizeWebMessageInfo(w))
      .filter((m: NormalizedHistoryMessage | null): m is NormalizedHistoryMessage => m !== null),
  };
}
