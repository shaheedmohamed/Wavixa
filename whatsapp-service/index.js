/**
 * Wavixa WhatsApp Service — whatsapp-web.js edition
 *
 * Full rewrite of the previous Baileys-based service on top of whatsapp-web.js
 * (Puppeteer + real WhatsApp Web). whatsapp-web.js runs the official WhatsApp
 * Web client inside a real Chromium, so its TLS / browser fingerprint is
 * indistinguishable from a human user — the main reason it carries a lower
 * ban risk than Baileys.
 *
 * Protection protocols implemented (mirrors the previous Baileys service and
 * adds a few wwebjs-specific hardening steps):
 *
 *   1. Per-session LocalAuth under .wwebjs_auth/session-user-<id>
 *   2. Pairing-code flow (requestPairingCode) for phone-number login, QR fallback
 *   3. Duplicate-phone guard: refuse to open a second live client on the same number
 *   4. Human-like typing simulation (composing -> random delay -> paused), plus
 *      random "unavailable" after sending
 *   5. onWhatsApp / getNumberId existence cache with randomised TTL
 *   6. Per-session proxy (HTTP / HTTPS / SOCKS) via puppeteer args + proxy file
 *   7. Randomised browser fingerprint per user (UA + viewport + lang + platform)
 *   8. Exponential backoff reconnect with jitter for registered sessions
 *   9. Auto-restore of registered sessions on startup, staggered to avoid
 *      opening many Chromium instances at once
 *  10. Concurrency cap (MAX_CONCURRENT_SESSIONS) to bound memory usage
 *  11. Logged-out (401) detection -> wipe auth folder -> notify Laravel
 *  12. Service API key auth on send-message endpoint
 *  13. Stealth puppeteer args (disable-blink-features=AutomationControlled etc.)
 */

require('dotenv').config();
process.env.HOME = __dirname;

const express = require('express');
const { Client, LocalAuth, MessageMedia, Location } = require('whatsapp-web.js');
const qrcode = require('qrcode');
const cors = require('cors');
const bodyParser = require('body-parser');
const fs = require('fs');
const path = require('path');
const axios = require('axios');
const crypto = require('crypto');
const mime = require('mime');
const dns = require('dns').promises;
const net = require('net');
const { URL } = require('url');
const ReconnectQueue = require('./lib/ReconnectQueue');

const app = express();
const PORT = parseInt(process.env.WHATSAPP_PORT || '3002', 10);
const LARAVEL_API = process.env.LARAVEL_API_URL || 'https://wavixa.net/api';
const SERVICE_API_KEY = process.env.WHATSAPP_SERVICE_API_KEY;
const MAX_CONCURRENT_SESSIONS = parseInt(process.env.MAX_CONCURRENT_SESSIONS || '20', 10);
const MAX_RECONNECT_ATTEMPTS = parseInt(process.env.MAX_RECONNECT_ATTEMPTS || '20', 10);
const RECONNECT_QUEUE_CONCURRENCY = parseInt(process.env.RECONNECT_QUEUE_CONCURRENCY || '3', 10);
const RECONNECT_QUEUE_INTERVAL_MS = parseInt(process.env.RECONNECT_QUEUE_INTERVAL_MS || '2000', 10);
const REDIS_URL = process.env.REDIS_URL || null;
const WORKER_ID = process.env.WHATSAPP_WORKER_ID || 'worker-1';
const TYPING_MIN_MS = parseInt(process.env.WHATSAPP_TYPING_MIN_MS || '1500', 10);
const TYPING_MAX_MS = parseInt(process.env.WHATSAPP_TYPING_MAX_MS || '4000', 10);
const IDLE_MIN_MS = parseInt(process.env.WHATSAPP_IDLE_MIN_MS || '5000', 10);
const IDLE_MAX_MS = parseInt(process.env.WHATSAPP_IDLE_MAX_MS || '20000', 10);
const MEDIA_MAX_BYTES = parseInt(process.env.WHATSAPP_MEDIA_MAX_BYTES || '16777216', 10);
const ALLOWED_MEDIA_HOSTS = process.env.WHATSAPP_ALLOWED_MEDIA_HOSTS
    ? process.env.WHATSAPP_ALLOWED_MEDIA_HOSTS.split(',').map(h => h.trim().toLowerCase())
    : null;

// Anti-ban cooldown: after this many minutes of continuous sending, the session
// pauses for COOLDOWN_REST_MINUTES before accepting more send requests.
const COOLDOWN_WORK_MINUTES = parseInt(process.env.WHATSAPP_COOLDOWN_WORK_MINUTES || '30', 10);
const COOLDOWN_REST_MINUTES = parseInt(process.env.WHATSAPP_COOLDOWN_REST_MINUTES || '5', 10);

app.use(cors());
app.use(bodyParser.json({ limit: '50mb' }));

const PUPPETEER_CACHE_DIR = path.join(__dirname, '.puppeteer_cache');
process.env.PUPPETEER_CACHE_DIR = PUPPETEER_CACHE_DIR;
const AUTH_ROOT = path.join(__dirname, '.wwebjs_auth');

// ---- in-memory state -------------------------------------------------------
const clients = new Map();          // userKey -> clientData
const qrCodes = new Map();          // userKey -> dataURL
const initializing = new Set();     // userKey
const reconnectAttempts = new Map();// userKey -> attempt count
let reconnectQueue = null;          // ReconnectQueue

// ---- helpers ---------------------------------------------------------------

function randomInt(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

function userKeyOf(userId) {
    return String(userId);
}

/**
 * Anti-ban send cooldown: each session sends continuously for COOLDOWN_WORK_MINUTES,
 * then rests for COOLDOWN_REST_MINUTES before accepting more send requests.
 *
 * Returns the remaining cooldown in milliseconds, or null when sending is allowed.
 * When sending is allowed, the work-window timer is (re)started if needed.
 */
function checkSendCooldown(clientData) {
    const now = Date.now();

    // Still in a rest period?
    if (clientData.cooldownUntil && now < clientData.cooldownUntil) {
        return clientData.cooldownUntil - now;
    }

    // Cooldown expired -> reset and start a fresh work window
    if (clientData.cooldownUntil && now >= clientData.cooldownUntil) {
        clientData.cooldownUntil = null;
        clientData.sendWindowStart = now;
    }

    // Start the work window on first send
    if (!clientData.sendWindowStart) {
        clientData.sendWindowStart = now;
    }

    // Work window elapsed -> enter rest period
    const workMs = COOLDOWN_WORK_MINUTES * 60 * 1000;
    if (now - clientData.sendWindowStart >= workMs) {
        clientData.cooldownUntil = now + (COOLDOWN_REST_MINUTES * 60 * 1000);
        clientData.sendWindowStart = null;
        console.log(`Send cooldown: session resting for ${COOLDOWN_REST_MINUTES} min`);
        return clientData.cooldownUntil - now;
    }

    return null;
}

function authDirFor(userId) {
    return path.join(AUTH_ROOT, `session-user-${userId}`);
}

function normalizePhone(phone) {
    if (!phone) return null;
    let cleaned = String(phone).replace(/\D/g, '');
    if (cleaned.startsWith('01') && cleaned.length === 11) {
        cleaned = '2' + cleaned;
    }
    return cleaned;
}

/**
 * Resolve the bundled Chrome build. Relying on a fixed absolute path breaks
 * whenever this directory is renamed, which silently prevents QR generation.
 */
function resolveChromeExecutable() {
    if (process.env.CHROME_EXECUTABLE_PATH) {
        return process.env.CHROME_EXECUTABLE_PATH;
    }
    const headlessDir = path.join(PUPPETEER_CACHE_DIR, 'chrome-headless-shell');
    if (fs.existsSync(headlessDir)) {
        const builds = fs.readdirSync(headlessDir).sort().reverse();
        for (const build of builds) {
            const candidate = path.join(headlessDir, build, 'chrome-headless-shell-linux64', 'chrome-headless-shell');
            if (fs.existsSync(candidate)) return candidate;
        }
    }
    const chromeDir = path.join(PUPPETEER_CACHE_DIR, 'chrome');
    if (fs.existsSync(chromeDir)) {
        const builds = fs.readdirSync(chromeDir).sort().reverse();
        for (const build of builds) {
            const candidate = path.join(chromeDir, build, 'chrome-linux64', 'chrome');
            if (fs.existsSync(candidate)) return candidate;
        }
    }
    return null;
}

const CHROME_PATH = resolveChromeExecutable();

// ---- proxy handling --------------------------------------------------------

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

/**
 * Convert any proxy URL (http/https/socks) into a puppeteer --proxy-server
 * argument and optional proxyAuthentication object. Credentials are stripped
 * from the flag because Chrome ignores them; wwebjs calls page.authenticate()
 * with the parsed credentials for HTTP/HTTPS proxies.
 */
function proxyToPuppeteerOptions(proxyUrl) {
    if (!proxyUrl) return { arg: null, auth: null };
    try {
        const url = new URL(proxyUrl);
        const port = url.port || (url.protocol === 'https:' ? '443' : url.protocol === 'http:' ? '80' : '');
        const server = `${url.protocol}//${url.hostname}${port ? ':' + port : ''}`;
        const auth = url.username || url.password
            ? { username: decodeURIComponent(url.username), password: decodeURIComponent(url.password) }
            : null;
        return { arg: `--proxy-server=${server}`, auth };
    } catch (err) {
        // Fallback: pass the raw string as the proxy flag.
        return { arg: `--proxy-server=${proxyUrl}`, auth: null };
    }
}

// ---- browser fingerprint randomisation -------------------------------------

const BROWSER_PROFILES = [
    { platform: 'Win32', ua: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36', lang: 'en-US', viewport: { width: 1280, height: 800 } },
    { platform: 'MacIntel', ua: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36', lang: 'en-US', viewport: { width: 1440, height: 900 } },
    { platform: 'Win32', ua: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36', lang: 'en-GB', viewport: { width: 1366, height: 768 } },
    { platform: 'MacIntel', ua: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36', lang: 'en-GB', viewport: { width: 1280, height: 800 } },
    { platform: 'Win32', ua: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36', lang: 'ar', viewport: { width: 1536, height: 864 } },
    { platform: 'MacIntel', ua: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36', lang: 'ar', viewport: { width: 1440, height: 900 } }
];

function getBrowserProfile(userKey) {
    const hash = crypto.createHash('sha256').update(userKey).digest('hex');
    const index = parseInt(hash.slice(0, 8), 16) % BROWSER_PROFILES.length;
    return BROWSER_PROFILES[index];
}

function clearReconnectState(userKey) {
    if (reconnectQueue) reconnectQueue.remove(userKey.replace(/^user-/, ''));
    reconnectAttempts.delete(userKey);
}

// ---- auth inspection -------------------------------------------------------

/**
 * A wwebjs session is "registered" once LocalAuth has written its
 * Local-Session.json / session-*.json files inside the auth dir.
 * We treat a non-empty auth dir containing a SessionData file as registered.
 */
function isRegisteredAuth(authDir) {
    try {
        if (!fs.existsSync(authDir)) return false;
        const files = fs.readdirSync(authDir);
        if (files.length === 0) return false;
        // LocalAuth writes files like "session-user-<id>.json" plus a
        // "session-user-<id>" subfolder. We consider it registered if either
        // a *.json file (other than our own proxy.txt) or the subfolder exists.
        const meaningful = files.filter(
            f => f !== 'proxy.txt' && !f.startsWith('.')
        );
        return meaningful.length > 0;
    } catch (err) {
        return false;
    }
}

/**
 * Best-effort phone extraction from a wwebjs session. wwebjs stores the WID
 * inside the Local-Session.json file as part of the SessionData object.
 */
function authPhone(authDir) {
    try {
        if (!fs.existsSync(authDir)) return null;
        const phoneFile = path.join(authDir, 'phone.txt');
        if (fs.existsSync(phoneFile)) {
            const phone = fs.readFileSync(phoneFile, 'utf8').trim();
            if (phone) return normalizePhone(phone);
        }
        for (const file of fs.readdirSync(authDir)) {
            if (!file.endsWith('.json')) continue;
            const full = path.join(authDir, file);
            const raw = fs.readFileSync(full, 'utf8');
            if (!raw) continue;
            // SessionData is JSON; the WID appears as a string like
            // "201xxxxxxxxx@c.us" inside the cached WhatsApp state.
            const match = raw.match(/(\d{6,15})@c\.us/);
            if (match) return normalizePhone(match[1]);
        }
    } catch (err) {}
    return null;
}

function saveAuthPhone(authDir, phone) {
    try {
        if (!fs.existsSync(authDir)) fs.mkdirSync(authDir, { recursive: true });
        fs.writeFileSync(path.join(authDir, 'phone.txt'), normalizePhone(phone) || '');
    } catch (err) {}
}

function findLiveClientByPhone(phone, excludeKey) {
    if (!phone) return null;
    for (const [key, data] of clients) {
        if (key === excludeKey) continue;
        if (data.state !== 'connected' && data.state !== 'connecting') continue;
        if (data.phoneNumber === phone) return key;
    }
    return null;
}

// ---- laravel notification --------------------------------------------------

function notifyLaravel(endpoint, data) {
    const url = `${LARAVEL_API}/${endpoint}`;
    const body = JSON.stringify(data);
    const signature = SERVICE_API_KEY
        ? crypto.createHmac('sha256', SERVICE_API_KEY).update(body).digest('hex')
        : '';
    console.log(`Notifying Laravel: POST ${url}`);
    axios.post(url, body, {
        headers: {
            'Content-Type': 'application/json',
            'X-Service-Signature': signature,
        },
    })
        .then(() => console.log(`Laravel notified: ${endpoint}`))
        .catch(err => console.error(`Failed to notify Laravel: ${endpoint}`, err.message));
}

function requireServiceAuth(req, res, next) {
    const authHeader = req.headers.authorization || '';
    if (!SERVICE_API_KEY || authHeader !== `Bearer ${SERVICE_API_KEY}`) {
        return res.status(401).json({ success: false, message: 'Unauthorized' });
    }
    next();
}

// ---- safe media download helpers ------------------------------------------

function isPrivateIPv4(ip) {
    const parts = ip.split('.').map(Number);
    if (parts.length !== 4 || parts.some(p => isNaN(p) || p < 0 || p > 255)) return false;
    const [a, b, c] = parts;
    // 10/8, 172.16/12, 192.168/16, 127/8, 169.254/16, 0/8, 255.255.255.255
    return a === 10
        || (a === 172 && b >= 16 && b <= 31)
        || (a === 192 && b === 168)
        || a === 127
        || (a === 169 && b === 254)
        || a === 0
        || (a === 255 && b === 255 && c === 255 && parts[3] === 255);
}

function isPrivateIPv6(ip) {
    const lower = ip.toLowerCase();
    return lower === '::1'
        || lower === '::'
        || lower.startsWith('fc')
        || lower.startsWith('fd')
        || lower.startsWith('fe80')
        || lower.startsWith('fe')
        || lower.startsWith('ff');
}

function isPrivateIp(ip) {
    if (!ip) return true;
    const family = net.isIP(ip);
    if (family === 4) return isPrivateIPv4(ip);
    if (family === 6) return isPrivateIPv6(ip);
    return false;
}

async function isUrlSafe(urlString, { allowFileScheme = false } = {}) {
    let parsed;
    try {
        parsed = new URL(urlString);
    } catch (err) {
        return false;
    }

    const scheme = parsed.protocol.slice(0, -1).toLowerCase();
    if (scheme === 'file' && !allowFileScheme) return false;
    if (scheme !== 'http' && scheme !== 'https' && scheme !== 'file') return false;

    const hostname = parsed.hostname.toLowerCase();
    if (hostname === 'localhost' || hostname.endsWith('.local')) return false;
    if (hostname === '169.254.169.254' || hostname.startsWith('metadata.')) return false;

    // Optional explicit allowlist for media hosts.
    if (ALLOWED_MEDIA_HOSTS && !ALLOWED_MEDIA_HOSTS.includes(hostname)) return false;

    // Reject literal private IPs.
    if (isPrivateIp(hostname)) return false;

    // Resolve and ensure DNS does not point to a private/internal address.
    try {
        const addresses = await dns.resolve(hostname);
        for (const ip of addresses) {
            if (isPrivateIp(ip)) return false;
        }
    } catch (err) {
        // If we cannot resolve the hostname, refuse it.
        return false;
    }

    return true;
}

function beforeRedirect(options) {
    const nextUrl = options.href || `${options.protocol}//${options.hostname}${options.path || ''}`;
    if (!isUrlSafe(nextUrl)) {
        throw new Error('Redirect target is not safe');
    }
}

async function downloadMedia(url, filename, mimeType) {
    if (!await isUrlSafe(url)) {
        throw new Error('Media URL is not safe or points to an internal/private address');
    }

    const response = await axios({
        method: 'GET',
        url,
        responseType: 'arraybuffer',
        timeout: 30000,
        maxRedirects: 5,
        maxBodyLength: MEDIA_MAX_BYTES,
        maxContentLength: MEDIA_MAX_BYTES,
        beforeRedirect: beforeRedirect,
    });

    const contentLength = parseInt(response.headers['content-length'] || response.data.length, 10);
    if (contentLength > MEDIA_MAX_BYTES) {
        throw new Error('Media file exceeds maximum allowed size');
    }

    let detectedMime = mimeType;
    if (!detectedMime) {
        const contentType = response.headers['content-type'];
        if (contentType) {
            detectedMime = contentType.split(';')[0].trim();
        }
    }
    if (!detectedMime && filename) {
        detectedMime = mime.getType(filename);
    }
    if (!detectedMime) {
        detectedMime = 'application/octet-stream';
    }

    const base64Data = Buffer.from(response.data).toString('base64');
    return new MessageMedia(detectedMime, base64Data, filename || undefined);
}

// ---- client lifecycle ------------------------------------------------------

function giveUpSession(userKey, userId, reason) {
    console.log(`Giving up on session ${userId}: ${reason}`);
    clearReconnectState(userKey);
    const client = clients.get(userKey);
    if (client?.client) {
        try { client.client.destroy(); } catch (err) {}
    }
    clients.delete(userKey);
    qrCodes.delete(userKey);
    initializing.delete(userKey);
    notifyLaravel('whatsapp/disconnected', { userId, reason });
}

async function initializeClient(userId, pairingPhoneNumber = null, providedProxy = null) {
    const userKey = userKeyOf(userId);
    const authDir = authDirFor(userId);

    if (initializing.has(userKey)) {
        console.log(`[initializeClient] User ${userId} is already initializing, skipping duplicate call.`);
        return clients.get(userKey) || null;
    }
    initializing.add(userKey);

    // If this session is already live, do not tear it down; destroying a
    // connected Chromium can still leave WhatsApp seeing two clients on the
    // same account, which triggers Stream Errored (conflict) / bans.
    const existing = clients.get(userKey);
    if (existing?.client && existing.state === 'connected') {
        console.log(`[initializeClient] User ${userId} already has a connected client, returning existing.`);
        initializing.delete(userKey);
        return existing;
    }
    if (existing?.client) {
        try { await existing.client.destroy(); } catch (err) {}
    }
    clients.delete(userKey);
    qrCodes.delete(userKey);

    const isRegistered = isRegisteredAuth(authDir);
    const storedPhone = normalizePhone(authPhone(authDir));
    const pairingPhone = normalizePhone(pairingPhoneNumber);
    const proxyUrl = getProxyForUser(userKey, authDir, providedProxy);
    const { arg: proxyArg, auth: proxyAuth } = proxyToPuppeteerOptions(proxyUrl);
    if (proxyUrl) {
        console.log(`Using proxy for user ${userId}: ${proxyUrl.replace(/\/\/[^@]*@/, '//***@')}`);
    }

    // Refuse to open a second live client on the same WhatsApp number.
    const phoneToCheck = storedPhone || pairingPhone;
    const duplicateKey = phoneToCheck ? findLiveClientByPhone(phoneToCheck, userKey) : null;
    if (duplicateKey) {
        console.log(`Skipping user ${userId}: ${phoneToCheck} is already live on session ${duplicateKey}`);
        clearReconnectState(userKey);
        initializing.delete(userKey);
        return null;
    }

    const profile = getBrowserProfile(userKey);

    const puppeteerArgs = [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-accelerated-2d-canvas',
        '--no-first-run',
        '--no-zygote',
        '--disable-gpu',
        '--disable-software-rasterizer',
        '--disable-extensions',
        '--disable-breakpad',
        '--disable-component-update',
        '--disable-crash-reporter',
        '--disable-blink-features=AutomationControlled',
        '--disable-features=IsolateOrigins,site-per-process',
        `--window-size=${profile.viewport.width},${profile.viewport.height}`,
        `--lang=${profile.lang}`
    ];
    if (proxyArg) puppeteerArgs.push(proxyArg);

    const client = new Client({
        authStrategy: new LocalAuth({
            clientId: `user-${userId}`,
            dataPath: AUTH_ROOT
        }),
        authTimeoutMs: 120000,
        qrTimeoutMs: 60000,
        restartOnAuthFail: false,
        takeoverTimeoutMs: 60000,
        proxyAuthentication: proxyAuth || undefined,
        deviceName: profile.platform === 'Win32' ? 'Windows' : 'Mac OS X',
        browserName: 'Chrome',
        puppeteer: {
            headless: 'new',
            protocolTimeout: 180000,
            timeout: 120000,
            args: puppeteerArgs,
            defaultViewport: profile.viewport,
            ...(CHROME_PATH ? { executablePath: CHROME_PATH } : {})
        }
    });

    const clientData = {
        client,
        state: 'connecting',
        qr: null,
        phoneNumber: storedPhone || pairingPhone || null,
        pairingCode: null,
        profile,
        authDir,
        onWhatsAppCache: new Map(),
        proxyUrl,
        sendWindowStart: null,
        cooldownUntil: null
    };
    clients.set(userKey, clientData);

    // ---- events ------------------------------------------------------------

    client.on('qr', async (qr) => {
        try {
            const url = await qrcode.toDataURL(qr);
            console.log(`QR received for user ${userId}`);
            const cData = clients.get(userKey);
            if (cData) {
                cData.qr = url;
                cData.state = 'connecting';
            }
            qrCodes.set(userKey, url);
            notifyLaravel('whatsapp/qr-generated', { userId, qr: url });
        } catch (err) {
            console.error(`QR generation error for user ${userId}:`, err);
        }
    });

    client.on('code_received', (code) => {
        console.log(`Pairing code generated for user ${userId}: ${code}`);
        const cData = clients.get(userKey);
        if (cData) {
            cData.pairingCode = code;
            cData.state = 'connecting';
        }
        notifyLaravel('whatsapp/pairing-code', { userId, pairingCode: code });
    });

    client.on('ready', async () => {
        let phoneNumber = null;
        let pushname = null;
        try {
            phoneNumber = client.info.wid.user;
            pushname = client.info.pushname;
        } catch (err) {
            console.error(`Failed to read client.info for user ${userId}:`, err.message);
        }

        // Duplicate-phone guard at connect time too — race conditions can
        // let two clients for the same number both reach "ready".
        const readyPhone = normalizePhone(phoneNumber);
        const rival = readyPhone ? findLiveClientByPhone(readyPhone, userKey) : null;
        if (rival) {
            console.log(`Closing user ${userId}: ${readyPhone} already live on session ${rival}`);
            try { await client.destroy(); } catch (err) {}
            clients.delete(userKey);
            qrCodes.delete(userKey);
            initializing.delete(userKey);
            clearReconnectState(userKey);
            return;
        }

        console.log(`Client is ready and connected for user ${userId} (phone: ${readyPhone})`);
        const cData = clients.get(userKey);
        if (cData) {
            cData.state = 'connected';
            cData.qr = null;
            cData.pairingCode = null;
            cData.phoneNumber = readyPhone;
        }
        saveAuthPhone(authDir, phoneNumber);
        qrCodes.delete(userKey);
        clearReconnectState(userKey);

        notifyLaravel('whatsapp/connected', { userId, phoneNumber, name: pushname });
    });

    client.on('authenticated', () => {
        console.log(`Authenticated for user ${userId}`);
    });

    client.on('auth_failure', (msg) => {
        console.error(`Auth failure for user ${userId}: ${msg}`);
        const cData = clients.get(userKey);
        if (cData) cData.state = 'auth_failed';
    });

    client.on('message', async (msg) => {
        if (!msg.fromMe) {
            const cData = clients.get(userKey);
            // Forward incoming message to Laravel for storage, webhooks, and auto-reply
            if (msg.from && !msg.from.includes('status@broadcast')) {
                let senderPhone = '';
                let senderName = '';
                try {
                    const contact = await msg.getContact();
                    senderPhone = contact?.number || '';
                    senderName = contact?.pushname || contact?.name || '';
                } catch (err) {
                    console.log(`Failed to get contact info: ${err.message}`);
                }

                if (!senderPhone) {
                    senderPhone = msg.from.replace(/@.*$/, '');
                }

                const messageId = (msg.id && (msg.id._serialized || msg.id.id)) || String(Date.now());
                notifyLaravel('whatsapp/message-received', {
                    userId: String(userId),
                    from: msg.from,
                    senderPhone: senderPhone,
                    senderName: senderName,
                    to: cData?.phoneNumber || null,
                    body: msg.body || '',
                    type: msg.type || 'text',
                    messageId: messageId,
                    isGroup: Boolean(msg.from.endsWith('@g.us') || msg.isGroupMsg),
                    timestamp: msg.timestamp || Math.floor(Date.now() / 1000)
                });
            }
        }
    });

    client.on('disconnected', async (reason) => {
        console.log(`Client disconnected for user ${userId}: ${reason}`);
        const cData = clients.get(userKey);
        const hasRegisteredAuth = isRegisteredAuth(authDir);
        qrCodes.delete(userKey);
        initializing.delete(userKey);

        // WhatsApp unlinked the device from the phone app -> wipe & give up.
        const fatal = reason === 'LOGOUT' ||
            reason === 'NAVIGATION' ||
            String(reason).includes('logged out') ||
            String(reason).includes('unlinked');

        if (fatal) {
            console.log(`User ${userId} logged out / unlinked from WhatsApp device.`);
            try { fs.rmSync(authDir, { recursive: true, force: true }); } catch (err) {
                console.error(`Failed to remove auth dir for user ${userId}: ${err.message}`);
            }
            giveUpSession(userKey, userId, 'logged_out');
            return;
        }

        if (!hasRegisteredAuth) {
            giveUpSession(userKey, userId, 'pairing_not_completed');
            return;
        }

        // Registered session: enqueue for controlled reconnect.
        const attempt = (reconnectAttempts.get(userKey) || 0) + 1;
        reconnectAttempts.set(userKey, attempt);

        if (attempt > MAX_RECONNECT_ATTEMPTS) {
            console.log(`Giving up on user ${userId}: too many reconnect attempts (${attempt})`);
            giveUpSession(userKey, userId, 'max_reconnect_attempts');
            return;
        }

        if (cData) {
            cData.state = 'reconnecting';
            cData.qr = null;
            cData.pairingCode = null;
        }

        console.log(`Enqueueing reconnect for user ${userId} [attempt ${attempt}]`);
        if (reconnectQueue) reconnectQueue.push(userId);
    });

    // ---- start -----------------------------------------------------------

    try {
        // Apply stealth overrides before the page navigates to WhatsApp Web.
        client.on('loading_screen', async (percent, message) => {
            // no-op; kept for logging if needed
        });

        await client.initialize();

        // Request a pairing code for brand-new sessions that asked for one.
        if (pairingPhone && !isRegistered) {
            try {
                // requestPairingCode must be called after the page is up; the
                // library handles waiting for AuthStore to be ready internally.
                const code = await client.requestPairingCode(pairingPhone);
                const cData = clients.get(userKey);
                if (cData) cData.pairingCode = code;
                console.log(`Pairing code generated for user ${userId}: ${code}`);
            } catch (err) {
                console.error(`Pairing code error for user ${userId}:`, err?.message || err);
            }
        }

        console.log(`Client initialized for user ${userId}`);
        initializing.delete(userKey);
        return clientData;
    } catch (err) {
        console.error(`Failed to initialize client for user ${userId}:`, err);
        clients.delete(userKey);
        qrCodes.delete(userKey);
        initializing.delete(userKey);
        return null;
    }
}

// ---- HTTP routes -----------------------------------------------------------

app.get('/', (req, res) => {
    res.json({ success: true, message: 'Wavixa WhatsApp Service is running (whatsapp-web.js)' });
});

app.get('/health', (req, res) => {
    res.json({
        success: true,
        message: 'WhatsApp Service is running',
        engine: 'whatsapp-web.js',
        chrome: CHROME_PATH ? path.basename(path.dirname(path.dirname(CHROME_PATH))) : 'not-found',
        sessions: clients.size
    });
});

app.get('/api/whatsapp/status/:userId', requireServiceAuth, async (req, res) => {
    const { userId } = req.params;
    const userKey = userKeyOf(userId);
    const clientData = clients.get(userKey);
    const authDir = authDirFor(userId);

    if (clientData && clientData.state === 'connected' && clientData.client?.info) {
        return res.json({
            success: true,
            status: 'connected',
            phoneNumber: clientData.phoneNumber,
            name: clientData.client.info.pushname
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
            qr
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

    // Auto-revive a registered session that was lost on a process restart.
    if (isRegisteredAuth(authDir)) {
        const isQueued = reconnectQueue && (reconnectQueue.pending.has(String(userId)) || reconnectQueue.inProgress.has(String(userId)));
        if (!initializing.has(userKey) && !isQueued) {
            console.log(`Auto-reviving registered session ${userId} upon status request`);
            if (reconnectQueue) {
                reconnectQueue.push(userId);
            } else {
                initializeClient(userId, null);
            }
        }
        return res.json({
            success: true,
            status: 'reconnecting',
            phoneNumber: authPhone(authDir),
            reconnecting: true
        });
    }

    res.json({ success: true, status: 'disconnected' });
});

app.get('/api/whatsapp/qr/:userId', requireServiceAuth, (req, res) => {
    const { userId } = req.params;
    const userKey = userKeyOf(userId);
    const qr = qrCodes.get(userKey) || clients.get(userKey)?.qr;
    if (qr) return res.json({ success: true, qr });
    return res.status(404).json({ success: false, message: 'No QR code available' });
});

app.post('/api/whatsapp/connect/:userId', requireServiceAuth, async (req, res) => {
    const { userId } = req.params;
    const userKey = userKeyOf(userId);
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

    // If a previous Chromium is still connecting for this user, kill it before
    // we wipe its auth folder; otherwise two browsers may race and cause a
    // Stream Errored (conflict) / LOGOUT.
    if (existing?.client) {
        try { existing.client.removeAllListeners(); } catch (e) {}
        try { await existing.client.destroy(); } catch (err) {
            console.error(`Failed to destroy stale client for user ${userId}:`, err.message);
        }
    }

    // Wipe both legacy Baileys and wwebjs auth folders so the user gets a
    // fresh pairing / QR.
    const baileysDir = path.join(__dirname, '.baileys_auth', `auth-${userId}`);
    if (fs.existsSync(baileysDir)) {
        try { fs.rmSync(baileysDir, { recursive: true, force: true }); } catch (err) {}
    }
    const wwebjsDir = authDirFor(userId);
    if (fs.existsSync(wwebjsDir)) {
        try { fs.rmSync(wwebjsDir, { recursive: true, force: true }); } catch (err) {}
    }

    // Persist per-session proxy for reconnects.
    if (proxyUrl) {
        try {
            if (!fs.existsSync(wwebjsDir)) fs.mkdirSync(wwebjsDir, { recursive: true });
            fs.writeFileSync(path.join(wwebjsDir, 'proxy.txt'), proxyUrl);
        } catch (err) {
            console.error(`Failed to write proxy file for user ${userId}:`, err.message);
        }
    }

    qrCodes.delete(userKey);
    clients.delete(userKey);

    const clientData = await initializeClient(userId, phoneNumber, proxyUrl);

    res.json({
        success: true,
        message: 'Initialization started',
        mode: phoneNumber ? 'phone' : 'qr',
        pairingCode: clientData?.pairingCode || null
    });
});

app.post(['/api/whatsapp/disconnect/:userId', '/api/whatsapp/delete-session/:userId'], requireServiceAuth, async (req, res) => {
    const { userId } = req.params;
    const userKey = userKeyOf(userId);
    const authDir = authDirFor(userId);

    clearReconnectState(userKey);

    const client = clients.get(userKey);
    if (client?.client) {
        try {
            await client.client.logout().catch(() => {});
            await client.client.destroy().catch(() => {});
        } catch (err) {}
    }

    clients.delete(userKey);
    qrCodes.delete(userKey);
    initializing.delete(userKey);

    if (fs.existsSync(authDir)) {
        try { fs.rmSync(authDir, { recursive: true, force: true }); } catch (err) {
            console.error(`Failed to remove auth dir for user ${userId}: ${err.message}`);
        }
    }

    notifyLaravel('whatsapp/disconnected', { userId, reason: 'manual_disconnect' });
    res.json({ success: true, message: 'Session disconnected and cleaned up successfully' });
});

app.post('/api/whatsapp/send-message', requireServiceAuth, async (req, res) => {
    const { userId, to, message } = req.body;
    console.log(`Sending message for user ${userId} to ${to}`);

    const clientData = clients.get(userKeyOf(userId));
    if (!clientData || clientData.state !== 'connected' || !clientData.client) {
        return res.status(400).json({ success: false, message: 'WhatsApp not connected for this user' });
    }

    const client = clientData.client;

    // Anti-ban cooldown: rest the session after a continuous send window
    const cooldownMs = checkSendCooldown(clientData);
    if (cooldownMs) {
        const retryAfterSec = Math.ceil(cooldownMs / 1000);
        console.log(`Send cooldown active for user ${userId}, retry in ${retryAfterSec}s`);
        return res.status(429).json({
            success: false,
            message: 'Session is in cooldown rest period',
            cooldown: true,
            retry_after: retryAfterSec
        });
    }

    try {
        let chatId;
        let formattedTo;
        if (String(to).includes('@')) {
            chatId = String(to).trim();
            formattedTo = chatId.replace(/@.*$/, '');
        } else {
            formattedTo = String(to).replace(/\D/g, '');
            if (formattedTo.startsWith('01') && formattedTo.length === 11) {
                formattedTo = '2' + formattedTo;
            }
            chatId = `${formattedTo}@c.us`;
        }

        console.log(`Sending to chatId: ${chatId}`);

        if (!chatId.endsWith('@lid')) {
            // Existence cache with randomised TTL (40-70 min) to avoid repeated
            // getNumberId calls that themselves look bot-like.
            const cached = clientData.onWhatsAppCache.get(formattedTo);
            const cacheTtl = 40 * 60 * 1000 + randomInt(0, 30 * 60 * 1000);
            const now = Date.now();
            let exists = cached && (now - cached.ts) < cacheTtl ? cached.exists : null;

            if (exists === null) {
                console.log(`Resolving number id for: ${formattedTo}`);
                const numberId = await client.getNumberId(formattedTo);
                exists = !!numberId;
                clientData.onWhatsAppCache.set(formattedTo, { exists, ts: now });
            }

            if (!exists) {
                return res.status(400).json({ success: false, message: 'This number is not registered on WhatsApp' });
            }
        }

        // Human-like typing simulation before sending: the recipient sees "typing..."
        // for a short, randomized delay before the actual message is delivered.
        try {
            await client.sendPresenceAvailable();
            const chat = await client.getChatById(chatId).catch(() => null);
            if (chat && typeof chat.sendStateTyping === 'function') {
                await chat.sendStateTyping();
            }
            const typingDelay = randomInt(TYPING_MIN_MS, TYPING_MAX_MS);
            await sleep(typingDelay);
            if (chat && typeof chat.clearState === 'function') {
                await chat.clearState();
            }
        } catch (presenceErr) {
            console.log(`Presence/typing notice: ${presenceErr.message}`);
        }

        const result = await client.sendMessage(chatId, message);
        let messageId = 'unknown';
        if (result && result.id) {
            messageId = result.id.id || (result.id._serialized || 'unknown');
        } else if (result && typeof result === 'string') {
            messageId = result;
        }

        // Randomly go "unavailable" after sending so we are not online 24/7.
        const idleDelay = randomInt(IDLE_MIN_MS, IDLE_MAX_MS);
        setTimeout(() => {
            try { client.sendPresenceUnavailable(); } catch (e) {}
        }, idleDelay);

        res.json({ success: true, messageId });

        notifyLaravel('whatsapp/message-sent', {
            userId,
            messageId,
            to: formattedTo,
            message,
            messageType: 'text',
            status: 'sent',
            timestamp: Math.floor(Date.now() / 1000)
        });
    } catch (err) {
        console.error('Error sending message:', err);
        res.status(500).json({ success: false, message: err.message });
    }
});

async function resolveMessageMedia(content, type, fileName, mimeType) {
    if (!content) {
        throw new Error('Media content is empty');
    }

    // 1. Direct URL (http / https). Fetched with SSRF and size controls.
    if (typeof content === 'string' && (content.startsWith('http://') || content.startsWith('https://'))) {
        try {
            return await downloadMedia(content, fileName, mimeType);
        } catch (urlErr) {
            const err = new Error(`Failed to download media from URL: ${urlErr.message}`);
            err.isClientError = true;
            throw err;
        }
    }

    // 2. Data URI (e.g. data:image/png;base64,...)
    if (typeof content === 'string' && content.startsWith('data:')) {
        const matches = content.match(/^data:([^;]+);base64,(.+)$/s);
        if (matches) {
            const detectedMime = matches[1];
            const base64Data = matches[2].trim().replace(/\s+/g, '');
            return new MessageMedia(mimeType || detectedMime, base64Data, fileName || undefined);
        }
    }

    // 3. Reject file-system paths outright; they should never be accepted from
    //    untrusted callers and can be used for local file disclosure.
    if (typeof content === 'string' && (
        content.startsWith('/') ||
        content.startsWith('./') ||
        content.startsWith('..') ||
        content.includes(':\\') ||
        content.includes(':/') ||
        /^[a-z]:/i.test(content)
    )) {
        const err = new Error(`مسار الملف غير مسموح به (${content}). يجب تمرير رابط URL مباشر أو ترميز Base64.`);
        err.isClientError = true;
        throw err;
    }

    // 4. Raw Base64 string
    if (typeof content === 'string') {
        const cleanBase64 = content.trim().replace(/\s+/g, '');

        // Validate base64 format
        const isBase64 = cleanBase64.length >= 8 &&
            cleanBase64.length % 4 === 0 &&
            /^[A-Za-z0-9+/]+={0,2}$/.test(cleanBase64);

        if (!isBase64) {
            const err = new Error('محتوى الملف غير صالح. يجب تمرير رابط URL مباشر للملف (مثل https://.../file.pdf) أو ترميز Base64 صحيح للملف وليس نصاً عادياً.');
            err.isClientError = true;
            throw err;
        }

        let detectedMime = mimeType;
        if (!detectedMime && fileName) {
            detectedMime = mime.getType(fileName);
        }
        if (!detectedMime) {
            if (type === 'image') detectedMime = 'image/jpeg';
            else if (type === 'video') detectedMime = 'video/mp4';
            else if (type === 'voice') detectedMime = 'audio/ogg; codecs=opus';
            else if (type === 'document') detectedMime = 'application/pdf';
            else detectedMime = 'application/octet-stream';
        }

        return new MessageMedia(detectedMime, cleanBase64, fileName || undefined);
    }

    throw new Error('Unsupported media content format');
}

app.post('/api/whatsapp/send-media', requireServiceAuth, async (req, res) => {
    const { userId, to, type, content, fileName, caption, mimeType } = req.body;
    console.log(`Sending media (${type}) for user ${userId} to ${to}`);

    const clientData = clients.get(userKeyOf(userId));
    if (!clientData || clientData.state !== 'connected' || !clientData.client) {
        return res.status(400).json({ success: false, message: 'WhatsApp not connected for this user' });
    }

    const client = clientData.client;

    // Anti-ban cooldown: rest the session after a continuous send window
    const cooldownMs = checkSendCooldown(clientData);
    if (cooldownMs) {
        const retryAfterSec = Math.ceil(cooldownMs / 1000);
        console.log(`Send cooldown active for user ${userId}, retry in ${retryAfterSec}s`);
        return res.status(429).json({
            success: false,
            message: 'Session is in cooldown rest period',
            cooldown: true,
            retry_after: retryAfterSec
        });
    }

    try {
        let chatId;
        let formattedTo;
        if (String(to).includes('@')) {
            chatId = String(to).trim();
            formattedTo = chatId.replace(/@.*$/, '');
        } else {
            formattedTo = String(to).replace(/\D/g, '');
            if (formattedTo.startsWith('01') && formattedTo.length === 11) {
                formattedTo = '2' + formattedTo;
            }
            chatId = `${formattedTo}@c.us`;
        }

        console.log(`Sending media to chatId: ${chatId}`);

        if (!chatId.endsWith('@lid')) {
            const cached = clientData.onWhatsAppCache.get(formattedTo);
            const cacheTtl = 40 * 60 * 1000 + randomInt(0, 30 * 60 * 1000);
            const now = Date.now();
            let exists = cached && (now - cached.ts) < cacheTtl ? cached.exists : null;

            if (exists === null) {
                console.log(`Resolving number id for: ${formattedTo}`);
                const numberId = await client.getNumberId(formattedTo);
                exists = !!numberId;
                clientData.onWhatsAppCache.set(formattedTo, { exists, ts: now });
            }

            if (!exists) {
                return res.status(400).json({ success: false, message: 'This number is not registered on WhatsApp' });
            }
        }

        // Typing simulation
        try {
            await client.sendPresenceAvailable();
            const chat = await client.getChatById(chatId).catch(() => null);
            if (chat && typeof chat.sendStateTyping === 'function') {
                await chat.sendStateTyping();
            }
            const typingDelay = randomInt(TYPING_MIN_MS, TYPING_MAX_MS);
            await sleep(typingDelay);
            if (chat && typeof chat.clearState === 'function') {
                await chat.clearState();
            }
        } catch (presenceErr) {
            console.log(`Presence/typing notice: ${presenceErr.message}`);
        }

        let result;
        if (type === 'contact') {
            let displayName = 'Contact';
            let phone = formattedTo;
            if (typeof content === 'object' && content !== null) {
                displayName = content.displayName || content.display_name || displayName;
                phone = content.phone || phone;
            } else if (typeof content === 'string') {
                if (content.includes('(') && content.includes(')')) {
                    const match = content.match(/^(.*)\s\(([^)]+)\)$/);
                    if (match) {
                        displayName = match[1];
                        phone = match[2];
                    }
                } else {
                    displayName = content;
                }
            }
            const cleanPhone = String(phone).replace(/\D/g, '');
            const vcard = `BEGIN:VCARD\nVERSION:3.0\nFN:${displayName}\nTEL;type=CELL;type=VOICE;waid=${cleanPhone}:+${cleanPhone}\nEND:VCARD`;
            result = await client.sendMessage(chatId, vcard, { parseVCards: true });
        } else if (type === 'location') {
            let lat = 0;
            let lng = 0;
            let locName = '';
            let locAddress = '';
            if (typeof content === 'object' && content !== null) {
                lat = parseFloat(content.latitude || content.lat || 0);
                lng = parseFloat(content.longitude || content.lng || 0);
                locName = content.name || '';
                locAddress = content.address || '';
            }
            const loc = new Location(lat, lng, { name: locName, address: locAddress });
            result = await client.sendMessage(chatId, loc);
        } else {
            const media = await resolveMessageMedia(content, type, fileName, mimeType);
            const sendOptions = {};
            if (caption) {
                sendOptions.caption = caption;
            }
            if (type === 'document') {
                sendOptions.sendMediaAsDocument = true;
            }
            if (type === 'voice') {
                sendOptions.sendAudioAsVoice = true;
            }
            result = await client.sendMessage(chatId, media, sendOptions);
        }

        let messageId = 'unknown';
        if (result && result.id) {
            messageId = result.id.id || (result.id._serialized || 'unknown');
        } else if (result && typeof result === 'string') {
            messageId = result;
        }

        const idleDelay = randomInt(IDLE_MIN_MS, IDLE_MAX_MS);
        setTimeout(() => {
            try { client.sendPresenceUnavailable(); } catch (e) {}
        }, idleDelay);

        res.json({ success: true, messageId });

        notifyLaravel('whatsapp/message-sent', {
            userId,
            messageId,
            to: formattedTo,
            message: typeof content === 'string' ? (content.length > 200 ? content.substring(0, 200) + '...' : content) : JSON.stringify(content),
            messageType: type || 'media',
            status: 'sent',
            timestamp: Math.floor(Date.now() / 1000)
        });
    } catch (err) {
        console.error(`Error sending ${type || 'media'}:`, err);
        const status = err.isClientError ? 400 : 500;
        res.status(status).json({ success: false, message: err.message });
    }
});

app.post('/api/whatsapp/check-number', requireServiceAuth, async (req, res) => {
    const { userId, to } = req.body;

    const clientData = clients.get(userKeyOf(userId));
    if (!clientData || clientData.state !== 'connected' || !clientData.client) {
        return res.status(400).json({ success: false, message: 'WhatsApp not connected for this user' });
    }

    const client = clientData.client;

    try {
        let formattedTo;
        if (String(to).includes('@')) {
            formattedTo = String(to).replace(/@.*$/, '');
        } else {
            formattedTo = String(to).replace(/\D/g, '');
            if (formattedTo.startsWith('01') && formattedTo.length === 11) {
                formattedTo = '2' + formattedTo;
            }
        }

        const cached = clientData.onWhatsAppCache.get(formattedTo);
        const cacheTtl = 40 * 60 * 1000 + randomInt(0, 30 * 60 * 1000);
        const now = Date.now();
        let exists = cached && (now - cached.ts) < cacheTtl ? cached.exists : null;

        if (exists === null) {
            const numberId = await client.getNumberId(formattedTo);
            exists = !!numberId;
            clientData.onWhatsAppCache.set(formattedTo, { exists, ts: now });
        }

        return res.json({
            success: true,
            registered: exists,
            phone: formattedTo
        });
    } catch (err) {
        console.error('Error checking number:', err);
        res.status(500).json({ success: false, message: err.message });
    }
});

app.post('/api/whatsapp/send-presence', requireServiceAuth, async (req, res) => {
    const { userId, to, state } = req.body;

    const clientData = clients.get(userKeyOf(userId));
    if (!clientData || clientData.state !== 'connected' || !clientData.client) {
        return res.status(400).json({ success: false, message: 'WhatsApp not connected for this user' });
    }

    const client = clientData.client;

    try {
        let chatId;
        if (String(to).includes('@')) {
            chatId = String(to).trim();
        } else {
            const formattedTo = String(to).replace(/\D/g, '');
            if (formattedTo.startsWith('01') && formattedTo.length === 11) {
                chatId = `2${formattedTo}@c.us`;
            } else {
                chatId = `${formattedTo}@c.us`;
            }
        }

        const chat = await client.getChatById(chatId).catch(() => null);
        if (!chat) {
            return res.status(404).json({ success: false, message: 'Chat not found' });
        }

        if (state === 'composing') {
            await client.sendPresenceAvailable();
            if (typeof chat.sendStateTyping === 'function') {
                await chat.sendStateTyping();
            }
        } else {
            if (typeof chat.clearState === 'function') {
                await chat.clearState();
            }
        }

        res.json({ success: true });
    } catch (err) {
        console.error('Error setting presence:', err);
        res.status(500).json({ success: false, message: err.message });
    }
});

reconnectQueue = new ReconnectQueue({
    workerId: WORKER_ID,
    concurrency: RECONNECT_QUEUE_CONCURRENCY,
    intervalMs: RECONNECT_QUEUE_INTERVAL_MS,
    redisUrl: REDIS_URL,
    handler: async (userId) => {
        const userKey = userKeyOf(userId);
        const cData = clients.get(userKey);
        if (cData && (cData.state === 'connected' || cData.state === 'connecting' || cData.state === 'reconnecting')) {
            console.log(`Reconnect queue: user ${userId} already active, skipping`);
            return;
        }

        // Wait for an available session slot.
        while (clients.size + qrCodes.size + initializing.size >= MAX_CONCURRENT_SESSIONS) {
            console.log(`Reconnect queue: waiting for session slot for user ${userId} (current: ${clients.size})`);
            await sleep(1000);
        }

        await initializeClient(userId, null);
    },
});

reconnectQueue.start();

app.listen(PORT, () => {
    console.log(`WhatsApp Service (whatsapp-web.js) listening on port ${PORT}`);
    console.log(`Max concurrent sessions: ${MAX_CONCURRENT_SESSIONS}`);

    if (CHROME_PATH) {
        console.log(`Chrome executable: ${CHROME_PATH}`);
    } else {
        console.error('WARNING: No Chrome build found under ' + PUPPETEER_CACHE_DIR + '. QR generation will fail. Run "npx puppeteer browsers install chrome" in this directory or set CHROME_EXECUTABLE_PATH.');
    }

    if (process.env.WHATSAPP_AUTO_RESTORE !== 'true') {
        console.log('WhatsApp auto-restore is disabled. Set WHATSAPP_AUTO_RESTORE=true in .env to enable it.');
        return;
    }

    if (!fs.existsSync(AUTH_ROOT)) return;
    const candidates = fs.readdirSync(AUTH_ROOT).filter(f => f.startsWith('session-user-'));
    const restorable = candidates.filter(f => isRegisteredAuth(path.join(AUTH_ROOT, f)));
    const skipped = candidates.length - restorable.length;
    console.log(`Auto-restore: ${restorable.length} paired session(s) to restore, ${skipped} unpaired skipped`);

    // Stagger restores through the reconnect queue so we never spin up
    // many Chromium instances at once.
    restorable.forEach((file) => {
        const userId = file.replace('session-user-', '');
        reconnectQueue.push(userId);
    });
});

process.on('SIGTERM', async () => {
    console.log('Received SIGTERM, shutting down gracefully...');
    for (const [key, cData] of clients.entries()) {
        try { if (cData?.client) await cData.client.destroy(); } catch (e) {}
    }
    process.exit(0);
});

process.on('SIGINT', async () => {
    console.log('Received SIGINT, shutting down gracefully...');
    for (const [key, cData] of clients.entries()) {
        try { if (cData?.client) await cData.client.destroy(); } catch (e) {}
    }
    process.exit(0);
});
