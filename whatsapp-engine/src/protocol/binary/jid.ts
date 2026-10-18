export type JidServer =
  | 'c.us'
  | 'g.us'
  | 'broadcast'
  | 's.whatsapp.net'
  | 'call'
  | 'lid'
  | 'newsletter'
  | 'bot'
  | 'hosted'
  | 'hosted.lid';

export enum WAJIDDomains {
  WHATSAPP = 0,
  LID = 1,
  HOSTED = 128,
  HOSTED_LID = 129,
}

export interface FullJid {
  user: string;
  device?: number;
  server: JidServer;
  domainType?: number;
}

export const getServerFromDomainType = (
  initialServer: string,
  domainType?: WAJIDDomains,
): JidServer => {
  switch (domainType) {
    case WAJIDDomains.LID:
      return 'lid';
    case WAJIDDomains.HOSTED:
      return 'hosted';
    case WAJIDDomains.HOSTED_LID:
      return 'hosted.lid';
    default:
      return initialServer as JidServer;
  }
};

export const jidEncode = (
  user: string | number | null,
  server: JidServer,
  device?: number,
  agent?: number,
): string => {
  return `${user || ''}${agent ? `_${agent}` : ''}${device ? `:${device}` : ''}@${server}`;
};

export const jidDecode = (jid: string | undefined): FullJid | undefined => {
  const sepIdx = typeof jid === 'string' ? jid.indexOf('@') : -1;
  if (sepIdx < 0) {
    return undefined;
  }

  const server = jid!.slice(sepIdx + 1);
  const userCombined = jid!.slice(0, sepIdx);

  const [userAgent, device] = userCombined.split(':');
  const [user, agent] = userAgent!.split('_');

  let domainType = WAJIDDomains.WHATSAPP;
  if (server === 'lid') {
    domainType = WAJIDDomains.LID;
  } else if (server === 'hosted') {
    domainType = WAJIDDomains.HOSTED;
  } else if (server === 'hosted.lid') {
    domainType = WAJIDDomains.HOSTED_LID;
  } else if (agent) {
    domainType = parseInt(agent);
  }

  return {
    server: server as JidServer,
    user: user!,
    domainType,
    device: device ? +device : undefined,
  };
};

export const isLidUser = (jid: string | undefined) => jid?.endsWith('@lid');
export const isPnUser = (jid: string | undefined) => jid?.endsWith('@s.whatsapp.net');
export const isJidGroup = (jid: string | undefined) => jid?.endsWith('@g.us');
