require('dotenv').config();
const crypto = require('crypto');
if (!globalThis.crypto) {
    globalThis.crypto = crypto.webcrypto;
}
const express = require('express');
const qrcode = require('qrcode');
const cors = require('cors');
const bodyParser = require('body-parser');
const fs = require('fs');
const path = require('path');
const axios = require('axios');
const pino = require('pino');

const app = express();
const PORT = process.env.WHATSAPP_PORT || 3002;
const LARAVEL_API = process.env.LARAVEL_API_URL || 'https://wavixa.net/api';
const SERVICE_API_KEY = process.env.WHATSAPP_SERVICE_API_KEY;
const MAX_CONCURRENT_SESSIONS = parseInt(process.env.MAX_CONCURRENT_SESSIONS || '50', 10);
const RECONNECT_BASE_DELAY_MS = parseInt(process.env.RECONNECT_BASE_DELAY_MS || '3000', 10);
const RECONNECT_MAX_DELAY_MS = parseInt(process.env.RECONNECT_MAX_DELAY_MS || '60000', 10);

app.use(cors());
app.use(bodyParser.json({ limit: '50mb' }));

const clients = new Map();
const qrCodes = new Map();
const initializing = new Set();
const reconnectAttempts = new Map();
const reconnectTimers = new Map();
let latestVersion = null;

const logger = pino({ level: process.env.LOG_LEVEL || 'warn' });

function randomInt(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

function createProxyAgent(proxyUrl) {
    if (!proxyUrl) return null;
    try {
        if (proxyUrl.startsWith('http://') || proxyUrl.startsWith('https://')) {
            const { HttpsProxyAgent } = require('https-proxy-agent');
            return new HttpsProxyAgent(proxyUrl);
        }
        if (proxyUrl.startsWith('socks://') || proxyUrl.startsWith('socks5://') || proxyUrl.startsWith('socks4://')) {
            const { SocksProxyAgent } = require('socks-proxy-agent');
            return new SocksProxyAgent(proxyUrl);
        }
    } catch (err) {
        console.error('Failed to create proxy agent for', proxyUrl, ':', err.message);
    }
    return null;
}

let cachedProxyList = null;
function loadProxyList() {
    if (cachedProxyList) return cachedProxyList;
    const list = [];
    if (process.env.WHATSAPP_PROXY_LIST) {
        list.push(...process.env.WHATSAPP_PROXY_LIST.split(',').map(p => p.trim()).filter(Boolean));
    }
    const listFile = path.join(__dirname, '.whatsapp_proxies.json');
    if (fs.existsSync(listFile)) {
        try {
            const parsed = JSON.parse(fs.readFileSync(listFile, 'utf8'));
            if (Array.isArray(parsed)) list.push(...parsed.filter(p => typeof p === 'string' && p));
        } catch (err) {}
    }
    cachedProxyList = list;
    return list;
}

function pickProxyForUser(userKey) {
    const list = loadProxyList();
    if (list.length === 0) return null;
    const hash = crypto.createHash('sha256').update(userKey).digest('hex');
    const index = parseInt(hash.slice(0, 8), 16) % list.length;
    return list[index];
}

function getProxyForUser(userKey, authDir, providedProxy = null) {
    if (providedProxy) return providedProxy;
    try {
        const proxyFile = path.join(authDir, 'proxy.txt');
        if (fs.existsSync(proxyFile)) return fs.readFileSync(proxyFile, 'utf8').trim();
    } catch (err) {}
    const envProxy = process.env.WHATSAPP_PROXY;
    if (envProxy) return envProxy;
    return pickProxyForUser(userKey);
}

function knownJidsFile(authDir) {
    return path.join(authDir, 'known_jids.json');
}

function loadKnownJids(authDir) {
    const file = knownJidsFile(authDir);
    try {
        if (fs.existsSync(file)) {
            const list = JSON.parse(fs.readFileSync(file, 'utf8'));
            if (Array.isArray(list)) return new Set(list);
        }
    } catch (err) {}
    return new Set();
}

function saveKnownJids(clientData) {
    try {
        fs.writeFileSync(clientData.knownJidsFile, JSON.stringify([...clientData.knownJids]));
    } catch (err) {}
}

function addKnownJid(clientData, jid) {
    if (!jid) return;
    clientData.knownJids.add(jid);
    saveKnownJids(clientData);
}

/**
 * Exponential backoff delay with jitter, capped at RECONNECT_MAX_DELAY_MS.
 */
function reconnectDelay(attempt) {
    const exponential = RECONNECT_BASE_DELAY_MS * Math.pow(1.5, Math.min(attempt - 1, 10));
    const capped = Math.min(exponential, RECONNECT_MAX_DELAY_MS);
    return Math.floor(capped + Math.random() * 1000);
}

function clearReconnectState(userKey) {
    const timer = reconnectTimers.get(userKey);
    if (timer) clearTimeout(timer);
    reconnectTimers.delete(userKey);
    reconnectAttempts.delete(userKey);
}

const BROWSER_FINGERPRINTS = [
    ['Windows', 'Chrome', '10.0.22631'],
    ['Mac OS', 'Chrome', '14.4.1'],
    ['Linux', 'Chrome', '6.5.0'],
    ['Windows', 'Firefox', '10.0.22631'],
    ['Mac OS', 'Firefox', '14.4.1'],
    ['Windows', 'Edge', '10.0.22631'],
    ['Linux', 'Firefox', '6.5.0']
];

function getBrowserFingerprint(userKey) {
    const hash = crypto.createHash('sha256').update(userKey).digest('hex');
    const index = parseInt(hash.slice(0, 8), 16) % BROWSER_FINGERPRINTS.length;
    return BROWSER_FINGERPRINTS[index];
}

/**
 * Tear down a session: stop retrying, drop local state and tell Laravel.
 */
function giveUpSession(userKey, userId, reason) {
    console.log(`Giving up on session ${userId}: ${reason}`);
    clearReconnectState(userKey);
    const client = clients.get(userKey);
    if (client?.sock) {
        try {
            client.sock.ev.removeAllListeners();
            client.sock.end();
        } catch (err) {}
    }
    clients.delete(userKey);
    qrCodes.delete(userKey);
    initializing.delete(userKey);
    notifyLaravel('whatsapp/disconnected', { userId, reason });
}

/**
 * A session is registered if it has saved valid credentials with me.id & accountSignature.
 */
function isRegisteredAuth(authDir) {
    try {
        const credsPath = path.join(authDir, 'creds.json');
        if (!fs.existsSync(credsPath)) return false;
        const creds = JSON.parse(fs.readFileSync(credsPath, 'utf8'));
        return !!(creds.me?.id && creds.account?.accountSignature);
    } catch (err) {
        console.error(`Unreadable creds in ${authDir}: ${err.message}`);
        return false;
    }
}

/**
 * The phone number an auth folder belongs to, read straight from stored creds.
 */
function authPhone(authDir) {
    try {
        const credsPath = path.join(authDir, 'creds.json');
        if (!fs.existsSync(credsPath)) return null;
        const creds = JSON.parse(fs.readFileSync(credsPath, 'utf8'));
        return jidToPhone(creds.me?.id);
    } catch (err) {
        return null;
    }
}

/**
 * Check if another session is already holding the same WhatsApp number.
 */
function findLiveClientByPhone(phone, excludeKey) {
    if (!phone) return null;

    for (const [key, data] of clients) {
        if (key === excludeKey) continue;
        if (data.state !== 'connected' && data.state !== 'connecting') continue;
        if (data.phoneNumber === phone) return key;
    }

    return null;
}

const VERSION_CACHE_FILE = path.join(__dirname, '.baileys_version_cache.json');

function loadCachedVersion() {
    try {
        if (fs.existsSync(VERSION_CACHE_FILE)) {
            const cached = JSON.parse(fs.readFileSync(VERSION_CACHE_FILE, 'utf8'));
            if (Array.isArray(cached.version) && cached.version.length === 3) {
                return cached.version;
            }
        }
    } catch (err) {
        console.error('Failed to load cached Baileys version:', err.message);
    }
    return null;
}

function saveCachedVersion(version) {
    try {
        fs.writeFileSync(VERSION_CACHE_FILE, JSON.stringify({ version, ts: Date.now() }));
    } catch (err) {
        console.error('Failed to save cached Baileys version:', err.message);
    }
}

async function getLatestVersion(baileys) {
    if (latestVersion) return latestVersion;

    const cached = loadCachedVersion();
    if (cached) {
        latestVersion = cached;
        console.log(`Using cached Baileys version: ${cached.join('.')}`);
        return cached;
    }

    try {
        const result = await baileys.fetchLatestBaileysVersion();
        latestVersion = result.version;
        console.log(`Latest Baileys version: ${latestVersion.join('.')}`);
        saveCachedVersion(latestVersion);
    } catch (err) {
        console.error('Failed to fetch latest Baileys version, using fallback:', err.message);
        latestVersion = [2, 3000, 1043857760];
    }
    return latestVersion;
}

function requireServiceAuth(req, res, next) {
    const authHeader = req.headers.authorization || '';
    if (!SERVICE_API_KEY || authHeader !== `Bearer ${SERVICE_API_KEY}`) {
        return res.status(401).json({ success: false, message: 'Unauthorized' });
    }
    next();
}

function notifyLaravel(endpoint, data) {
    const url = `${LARAVEL_API}/${endpoint}`;
    console.log(`Notifying Laravel: POST ${url}`);
    axios.post(url, data)
        .then(res => console.log(`Laravel notified: ${endpoint}`))
        .catch(err => console.error(`Failed to notify Laravel: ${endpoint}`, err.message));
}

function jidToPhone(jid) {
    if (!jid) return null;
    return jid.split(':')[0].split('@')[0];
}

async function initializeClient(userId, pairingPhoneNumber = null, providedProxy = null) {
    const userKey = String(userId);
    const authDir = path.join(__dirname, '.baileys_auth', `auth-${userId}`);

    // Clean up any lingering socket/listeners for this userKey before creating new one
    const existingClient = clients.get(userKey);
    if (existingClient?.sock) {
        try {
            existingClient.sock.ev.removeAllListeners();
            existingClient.sock.end();
        } catch (e) {}
    }

    const isRegistered = isRegisteredAuth(authDir);
    const storedPhone = authPhone(authDir);
    const proxyUrl = getProxyForUser(userKey, authDir, providedProxy);
    const proxyAgent = proxyUrl ? createProxyAgent(proxyUrl) : null;
    if (proxyUrl && !proxyAgent) {
        console.warn(`Proxy configured for user ${userId} but agent could not be created: ${proxyUrl}`);
    }

    // Refuse to open a second socket on a number that is already live.
    const duplicateKey = findLiveClientByPhone(storedPhone, userKey);
    if (duplicateKey) {
        console.log(`Skipping user ${userId}: ${storedPhone} is already live on session ${duplicateKey}`);
        clearReconnectState(userKey);
        initializing.delete(userKey);
        return null;
    }

    try {
        const baileysModule = await import('@whiskeysockets/baileys');
        const baileys = baileysModule.default;
        const { state, saveCreds } = await baileys.useMultiFileAuthState(authDir);
        const version = await getLatestVersion(baileys);
        const browserFingerprint = getBrowserFingerprint(userKey);
        const firstSyncMarker = path.join(authDir, '.synced');
        const isFirstSync = !fs.existsSync(firstSyncMarker);

        const sock = baileys.makeWASocket({
            auth: state,
            version,
            logger,
            printQRInTerminal: false,
            connectTimeoutMs: 60000,
            defaultQueryTimeoutMs: 60000,
            keepAliveIntervalMs: 25000,
            markOnlineOnConnect: false,
            syncFullHistory: isFirstSync,
            shouldSyncHistoryMessage: (msg) => isFirstSync ? !msg?.key?.fromMe : false,
            shouldIgnoreJid: (jid) => jid?.endsWith('@broadcast') || jid?.includes('newsletter'),
            browser: browserFingerprint,
            agent: proxyAgent || undefined
        });

        const clientData = {
            sock,
            state: 'connecting',
            qr: null,
            phoneNumber: storedPhone || pairingPhoneNumber || null,
            pairingCode: null,
            browserFingerprint,
            knownJids: loadKnownJids(authDir),
            knownJidsFile: knownJidsFile(authDir),
            onWhatsAppCache: new Map()
        };
        clients.set(userKey, clientData);

        // ONLY request pairing code for a NEW session that is NOT already registered
        if (pairingPhoneNumber && !isRegistered && !state.creds.registered && !state.creds.me?.id) {
            try {
                await sock.waitForSocketOpen();
                const code = await sock.requestPairingCode(pairingPhoneNumber);
                clientData.pairingCode = code;
                console.log(`Pairing code generated for user ${userId}: ${code}`);
            } catch (err) {
                console.error(`Pairing code error for user ${userId}:`, err?.message || err);
            }
        }

        sock.ev.on('creds.update', saveCreds);

        // Track numbers that have messaged us so we can distinguish warm from cold contacts
        sock.ev.on('messages.upsert', ({ messages = [] }) => {
            for (const m of messages) {
                if (!m.key.fromMe) {
                    const cData = clients.get(userKey);
                    if (cData) addKnownJid(cData, m.key.remoteJid);
                }
            }
        });

        sock.ev.on('connection.update', (update) => {
            const { connection, lastDisconnect, qr } = update;

            if (qr) {
                qrcode.toDataURL(qr)
                    .then(url => {
                        console.log(`QR received for user ${userId}`);
                        const cData = clients.get(userKey);
                        if (cData) cData.qr = url;
                        qrCodes.set(userKey, url);

                        notifyLaravel('whatsapp/qr-generated', {
                            userId: userId,
                            qr: url
                        });
                    })
                    .catch(err => console.error(`QR generation error for user ${userId}:`, err));
            }

            if (connection === 'open') {
                const phoneNumber = jidToPhone(sock.user?.id);

                const rival = findLiveClientByPhone(phoneNumber, userKey);
                if (rival) {
                    console.log(`Closing user ${userId}: ${phoneNumber} already live on session ${rival}`);
                    clients.delete(userKey);
                    qrCodes.delete(userKey);
                    initializing.delete(userKey);
                    clearReconnectState(userKey);
                    try { sock.end(); } catch (err) {}
                    return;
                }

                console.log(`Client is ready and connected for user ${userId} (phone: ${phoneNumber})`);
                if (isFirstSync) {
                    try {
                        fs.writeFileSync(firstSyncMarker, '1');
                    } catch (err) {
                        console.error(`Failed to write sync marker for user ${userId}:`, err.message);
                    }
                }
                const cData = clients.get(userKey);
                if (cData) {
                    cData.state = 'connected';
                    cData.qr = null;
                    cData.pairingCode = null;
                    cData.phoneNumber = phoneNumber;
                }
                qrCodes.delete(userKey);
                clearReconnectState(userKey);

                notifyLaravel('whatsapp/connected', {
                    userId: userId,
                    phoneNumber: phoneNumber
                });
            } else if (connection === 'close') {
                const statusCode = lastDisconnect?.error?.output?.statusCode ?? lastDisconnect?.error?.statusCode;
                const errorMsg = lastDisconnect?.error?.message || 'unknown';
                const loggedOut = statusCode === baileys.DisconnectReason.loggedOut;
                console.log(`Client disconnected for user ${userId}: ${errorMsg} (status: ${statusCode ?? 'none'})`);

                const cData = clients.get(userKey);
                const hasRegisteredAuth = isRegisteredAuth(authDir);

                qrCodes.delete(userKey);
                initializing.delete(userKey);

                // If user unlinked device directly in WhatsApp mobile app
                if (loggedOut) {
                    console.log(`User ${userId} logged out from WhatsApp device.`);
                    try {
                        fs.rmSync(authDir, { recursive: true, force: true });
                    } catch (err) {
                        console.error(`Failed to remove auth dir for user ${userId}: ${err.message}`);
                    }
                    giveUpSession(userKey, userId, 'logged_out');
                    return;
                }

                // If session was never paired and closed, don't keep reconnecting
                if (!hasRegisteredAuth) {
                    giveUpSession(userKey, userId, 'pairing_not_completed');
                    return;
                }

                // REGISTERED SESSION: Reconnect indefinitely!
                const attempt = (reconnectAttempts.get(userKey) || 0) + 1;
                reconnectAttempts.set(userKey, attempt);

                if (cData) {
                    cData.state = 'reconnecting';
                    cData.qr = null;
                    cData.pairingCode = null;
                    cData.sock = null;
                }

                const delay = statusCode === baileys.DisconnectReason.restartRequired
                    ? 1000
                    : reconnectDelay(attempt);

                console.log(`Reconnecting registered user ${userId} in ${delay}ms [attempt ${attempt}]`);

                const existingTimer = reconnectTimers.get(userKey);
                if (existingTimer) clearTimeout(existingTimer);
                reconnectTimers.set(userKey, setTimeout(() => {
                    reconnectTimers.delete(userKey);
                    // NEVER pass pairingPhoneNumber on reconnect so it never tries to re-request pairing codes
                    initializeClient(userId, null);
                }, delay));
            }
        });

        console.log(`Client initialized for user ${userId}`);
        return clientData;
    } catch (err) {
        console.error(`Failed to initialize client for user ${userId}:`, err);
        clients.delete(userKey);
        qrCodes.delete(userKey);
        initializing.delete(userKey);
        return null;
    }
}

app.get('/', (req, res) => {
    res.json({ success: true, message: 'Wavixa WhatsApp Service is running (Baileys)' });
});

app.get('/health', (req, res) => {
    res.json({ success: true, message: 'WhatsApp Service is running' });
});

app.get('/api/whatsapp/status/:userId', async (req, res) => {
    const { userId } = req.params;
    const userKey = String(userId);
    const clientData = clients.get(userKey);
    const authDir = path.join(__dirname, '.baileys_auth', `auth-${userId}`);

    if (clientData && clientData.state === 'connected') {
        return res.json({
            success: true,
            status: 'connected',
            phoneNumber: clientData.phoneNumber,
            name: clientData.phoneNumber
        });
    }

    if (clientData && clientData.pairingCode) {
        return res.json({
            success: true,
            status: 'pending',
            mode: 'phone',
            pairingCode: clientData.pairingCode,
            phoneNumber: clientData.phoneNumber
        });
    }

    const qr = clientData?.qr || qrCodes.get(userKey);
    if (qr) {
        return res.json({
            success: true,
            status: 'pending',
            mode: 'qr',
            qr: qr
        });
    }

    if (clientData && (clientData.state === 'connecting' || clientData.state === 'reconnecting')) {
        return res.json({
            success: true,
            status: clientData.state === 'reconnecting' ? 'reconnecting' : 'pending',
            mode: clientData.phoneNumber ? 'phone' : 'qr',
            phoneNumber: clientData.phoneNumber,
            reconnecting: true
        });
    }

    // If session has valid registered credentials on disk but not in memory, restore it automatically!
    if (isRegisteredAuth(authDir)) {
        if (!initializing.has(userKey) && !reconnectTimers.has(userKey)) {
            console.log(`Auto-reviving registered session ${userId} upon status request`);
            initializeClient(userId, null);
        }
        return res.json({
            success: true,
            status: 'reconnecting',
            phoneNumber: authPhone(authDir),
            reconnecting: true
        });
    }

    res.json({
        success: true,
        status: 'disconnected'
    });
});

app.get('/api/whatsapp/qr/:userId', (req, res) => {
    const { userId } = req.params;
    const userKey = String(userId);
    const qr = qrCodes.get(userKey) || clients.get(userKey)?.qr;

    if (qr) {
        return res.json({
            success: true,
            qr: qr
        });
    }

    return res.status(404).json({
        success: false,
        message: 'No QR code available'
    });
});

app.post('/api/whatsapp/connect/:userId', async (req, res) => {
    const { userId } = req.params;
    const userKey = String(userId);
    const phoneNumber = req.body.phone_number || null;
    const proxyUrl = req.body.proxy ? String(req.body.proxy).trim() : null;

    const existing = clients.get(userKey);
    if (existing && existing.state === 'connected') {
        return res.json({ success: true, message: 'Already connected', phoneNumber: existing.phoneNumber });
    }

    const activeCount = clients.size + qrCodes.size + initializing.size;
    if (activeCount >= MAX_CONCURRENT_SESSIONS) {
        return res.status(503).json({
            success: false,
            message: `Maximum active WhatsApp sessions (${MAX_CONCURRENT_SESSIONS}) reached. Try again later or increase MAX_CONCURRENT_SESSIONS in .env.`
        });
    }

    if (initializing.has(userKey)) {
        return res.json({ success: true, message: 'Initialization already in progress' });
    }

    clearReconnectState(userKey);

    const baileysDir = path.join(__dirname, '.baileys_auth', `auth-${userId}`);
    if (fs.existsSync(baileysDir)) {
        fs.rmSync(baileysDir, { recursive: true, force: true });
    }
    const wwebjsDir = path.join(__dirname, '.wwebjs_auth', `session-user-${userId}`);
    if (fs.existsSync(wwebjsDir)) {
        fs.rmSync(wwebjsDir, { recursive: true, force: true });
    }

    // Persist per-session proxy for reconnects
    if (proxyUrl) {
        try {
            if (!fs.existsSync(baileysDir)) {
                fs.mkdirSync(baileysDir, { recursive: true });
            }
            fs.writeFileSync(path.join(baileysDir, 'proxy.txt'), proxyUrl);
        } catch (err) {
            console.error(`Failed to write proxy file for user ${userId}:`, err.message);
        }
    }

    qrCodes.delete(userKey);
    clients.delete(userKey);
    initializing.add(userKey);

    const clientData = await initializeClient(userId, phoneNumber, proxyUrl);
    initializing.delete(userKey);
    res.json({
        success: true,
        message: 'Initialization started',
        mode: phoneNumber ? 'phone' : 'qr',
        pairingCode: clientData?.pairingCode || null
    });
});

// Explicit disconnect endpoints
app.post(['/api/whatsapp/disconnect/:userId', '/api/whatsapp/delete-session/:userId'], async (req, res) => {
    const { userId } = req.params;
    const userKey = String(userId);
    const authDir = path.join(__dirname, '.baileys_auth', `auth-${userId}`);

    clearReconnectState(userKey);

    const client = clients.get(userKey);
    if (client?.sock) {
        try {
            client.sock.ev.removeAllListeners();
            await client.sock.logout().catch(() => {});
            client.sock.end();
        } catch (err) {}
    }

    clients.delete(userKey);
    qrCodes.delete(userKey);
    initializing.delete(userKey);

    if (fs.existsSync(authDir)) {
        try {
            fs.rmSync(authDir, { recursive: true, force: true });
        } catch (err) {
            console.error(`Failed to remove auth dir for user ${userId}:`, err.message);
        }
    }

    notifyLaravel('whatsapp/disconnected', { userId, reason: 'manual_disconnect' });

    res.json({ success: true, message: 'Session disconnected and cleaned up successfully' });
});

app.post('/api/whatsapp/send-message', requireServiceAuth, async (req, res) => {
    const { userId, to, message } = req.body;
    console.log(`Sending message for user ${userId} to ${to}`);

    const clientData = clients.get(String(userId));
    if (!clientData || clientData.state !== 'connected' || !clientData.sock) {
        return res.status(400).json({ success: false, message: 'WhatsApp not connected for this user' });
    }

    try {
        let formattedTo = to.replace(/\D/g, '');
        if (formattedTo.startsWith('01') && formattedTo.length === 11) {
            formattedTo = '2' + formattedTo;
        }

        const jid = `${formattedTo}@s.whatsapp.net`;
        const cached = clientData.onWhatsAppCache.get(formattedTo);
        const cacheTtl = 40 * 60 * 1000 + randomInt(0, 30 * 60 * 1000); // 40-70 min
        const now = Date.now();

        let exists = cached && (now - cached.ts) < cacheTtl ? cached.exists : null;

        if (exists === null) {
            console.log(`Resolving number id for: ${formattedTo}`);
            const onWhatsApp = await clientData.sock.onWhatsApp(formattedTo);
            exists = !!onWhatsApp[0]?.exists;
            clientData.onWhatsAppCache.set(formattedTo, { exists, ts: now });
        }

        if (!exists) {
            return res.status(400).json({ success: false, message: 'This number is not registered on WhatsApp' });
        }

        const isKnown = clientData.knownJids.has(jid);
        console.log(`Sending to jid: ${jid} (known=${isKnown})`);

        if (process.env.WHATSAPP_BLOCK_COLD_MESSAGES === 'true' && !isKnown) {
            return res.status(400).json({ success: false, message: 'Refusing to send cold message: no prior chat with this number' });
        }

        // Human-like typing only for contacts we have already interacted with
        if (isKnown) {
            try {
                await clientData.sock.sendPresenceUpdate('available');
                await clientData.sock.sendPresenceUpdate('composing', jid);
                const typingDelay = randomInt(1500, 35000);
                await sleep(typingDelay);
                await clientData.sock.sendPresenceUpdate('paused', jid);
            } catch (presenceErr) {
                console.log(`Presence update notice: ${presenceErr.message}`);
            }
        }

        const result = await clientData.sock.sendMessage(jid, { text: message, linkPreview: false });
        const messageId = result?.key?.id || 'unknown';

        addKnownJid(clientData, jid);

        // Randomly mark unavailable after sending so we are not "online" non-stop
        const idleDelay = randomInt(5000, 20000);
        setTimeout(() => {
            try {
                clientData.sock.sendPresenceUpdate('unavailable');
            } catch (e) {}
        }, idleDelay);

        res.json({ success: true, messageId: messageId });

        notifyLaravel('whatsapp/message-sent', {
            userId: userId,
            messageId: messageId,
            to: formattedTo,
            message: message,
            messageType: 'text',
            status: 'sent',
            timestamp: Math.floor(Date.now() / 1000)
        });
    } catch (err) {
        console.error('Error sending message:', err);
        res.status(500).json({ success: false, message: err.message });
    }
});

app.listen(PORT, () => {
    console.log(`WhatsApp Service (Baileys) listening on port ${PORT}`);

    if (process.env.WHATSAPP_AUTO_RESTORE !== 'true') {
        console.log('WhatsApp auto-restore is disabled. Set WHATSAPP_AUTO_RESTORE=true in .env to enable it.');
        return;
    }

    const baileysDir = path.join(__dirname, '.baileys_auth');
    if (!fs.existsSync(baileysDir)) return;

    const candidates = fs.readdirSync(baileysDir).filter(f => f.startsWith('auth-'));
    const restorable = candidates.filter(f => isRegisteredAuth(path.join(baileysDir, f)));
    const skipped = candidates.length - restorable.length;

    console.log(`Auto-restore: ${restorable.length} paired session(s) to restore, ${skipped} unpaired skipped`);

    // Stagger restores so we never open many sockets to WhatsApp at once.
    restorable.forEach((file, index) => {
        const userId = file.replace('auth-', '');
        setTimeout(() => {
            if (clients.size + qrCodes.size + initializing.size < MAX_CONCURRENT_SESSIONS) {
                initializeClient(userId, null);
            } else {
                console.log(`Auto-restore: skipping ${userId}, concurrency limit reached`);
            }
        }, index * 2000);
    });
});
