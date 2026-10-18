/**
 * Wavixa Telegram User Account Service — GramJS MTProto + QR Code Login
 *
 * Allows users to connect their real Telegram account/number via QR Code
 * just like WhatsApp Web, without needing a bot!
 */

require('dotenv').config();

const express = require('express');
const cors = require('cors');
const bodyParser = require('body-parser');
const fs = require('fs');
const path = require('path');
const axios = require('axios');
const crypto = require('crypto');
const dns = require('dns').promises;
const net = require('net');
const { URL } = require('url');
const qrcode = require('qrcode');
const { TelegramClient, Api } = require('telegram');
const { StringSession } = require('telegram/sessions');
const { NewMessage } = require('telegram/events');

const app = express();
const PORT = parseInt(process.env.TELEGRAM_PORT || '3003', 10);
const LARAVEL_API = process.env.LARAVEL_API_URL || 'https://wavixa.net/api';
const SERVICE_API_KEY = process.env.TELEGRAM_SERVICE_API_KEY || 'wavixa-telegram-service-secret-key-2026';
const API_ID = parseInt(process.env.TELEGRAM_API_ID || '6', 10);
const API_HASH = process.env.TELEGRAM_API_HASH || 'eb06d4abfb49dc3eeb1aeb98ae0f581e';
const MEDIA_MAX_BYTES = parseInt(process.env.TELEGRAM_MEDIA_MAX_BYTES || '16777216', 10);

app.use(cors());
app.use(bodyParser.json({ limit: '50mb' }));
app.use(bodyParser.urlencoded({ extended: true, limit: '50mb' }));

const AUTH_DIR = path.join(__dirname, '.telegram_auth');
if (!fs.existsSync(AUTH_DIR)) {
    fs.mkdirSync(AUTH_DIR, { recursive: true });
}

// In-memory clients state
const clients = new Map(); // userId -> { client, sessionString, user, status, qr, isConnecting }
const qrCodes = new Map(); // userId -> base64 QR Data URL

function sessionFileFor(userId) {
    return path.join(AUTH_DIR, `session-user-${userId}.json`);
}

function loadSavedSession(userId) {
    const file = sessionFileFor(userId);
    if (fs.existsSync(file)) {
        try {
            return JSON.parse(fs.readFileSync(file, 'utf8'));
        } catch (e) {
            console.error(`Error reading session file for user ${userId}:`, e);
        }
    }
    return null;
}

function saveSession(userId, data) {
    const file = sessionFileFor(userId);
    try {
        fs.writeFileSync(file, JSON.stringify(data, null, 2), 'utf8');
    } catch (e) {
        console.error(`Error saving session file for user ${userId}:`, e);
    }
}

function deleteSavedSession(userId) {
    const file = sessionFileFor(userId);
    if (fs.existsSync(file)) {
        try {
            fs.unlinkSync(file);
        } catch (e) {
            console.error(`Error deleting session file for user ${userId}:`, e);
        }
    }
}

async function notifyLaravel(endpoint, data) {
    try {
        const body = JSON.stringify(data);
        const signature = crypto.createHmac('sha256', SERVICE_API_KEY).update(body).digest('hex');
        await axios.post(`${LARAVEL_API}/telegram/${endpoint}`, body, {
            headers: {
                'Authorization': `Bearer ${SERVICE_API_KEY}`,
                'Content-Type': 'application/json',
                'X-Service-Signature': signature,
            },
            timeout: 10000,
        });
    } catch (e) {
        console.warn(`[Webhook] Failed to notify Laravel ${endpoint}:`, e.message);
    }
}

function requireServiceAuth(req, res, next) {
    const authHeader = req.headers.authorization || '';
    if (!SERVICE_API_KEY || authHeader !== `Bearer ${SERVICE_API_KEY}`) {
        return res.status(401).json({ success: false, message: 'Unauthorized' });
    }
    next();
}

function isPrivateIPv4(ip) {
    const parts = ip.split('.').map(Number);
    if (parts.length !== 4 || parts.some(p => isNaN(p) || p < 0 || p > 255)) return false;
    const [a, b, c] = parts;
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

async function isUrlSafe(urlString) {
    let parsed;
    try {
        parsed = new URL(urlString);
    } catch (err) {
        return false;
    }

    const scheme = parsed.protocol.slice(0, -1).toLowerCase();
    if (scheme !== 'http' && scheme !== 'https') return false;

    const hostname = parsed.hostname.toLowerCase();
    if (hostname === 'localhost' || hostname.endsWith('.local')) return false;
    if (hostname === '169.254.169.254' || hostname.startsWith('metadata.')) return false;
    if (isPrivateIp(hostname)) return false;

    try {
        const addresses = await dns.resolve(hostname);
        for (const ip of addresses) {
            if (isPrivateIp(ip)) return false;
        }
    } catch (err) {
        return false;
    }

    return true;
}

function validateMediaUrl(mediaUrl) {
    if (!mediaUrl) return true;
    return isUrlSafe(mediaUrl);
}

/**
 * Setup event listeners for connected client (incoming messages etc.)
 */
function attachClientHandlers(userId, client) {
    client.addEventHandler(async (event) => {
        try {
            const message = event.message;
            if (!message || message.out) {
                // Ignore outgoing messages sent by self
                return;
            }

            const sender = await message.getSender();
            const senderPhone = sender?.phone ? '+' + sender.phone : null;
            const senderName = [sender?.firstName, sender?.lastName].filter(Boolean).join(' ') || sender?.title || 'Telegram User';
            const senderUsername = sender?.username ? '@' + sender.username : null;
            const chatId = String(message.chatId || sender?.id || '');

            const payload = {
                userId: String(userId),
                chatId: chatId,
                senderName: senderName,
                senderPhone: senderPhone,
                senderUsername: senderUsername,
                message: message.message || message.text || '',
                messageId: String(message.id),
                isGroup: message.isGroup || false,
                isChannel: message.isChannel || false,
                date: new Date(message.date * 1000).toISOString(),
            };

            notifyLaravel('message-received', payload);
        } catch (err) {
            console.error(`[User ${userId}] Error processing incoming Telegram event:`, err.message);
        }
    }, new NewMessage({}));
}

/**
 * Smart peer resolver for phone numbers, @usernames, chat IDs, or 'me'
 */
async function resolvePeer(client, to, userKey) {
    let clean = String(to).trim();

    if (clean.toLowerCase() === 'me' || clean.toLowerCase() === 'self') {
        return 'me';
    }

    // If starts with @ -> username
    if (clean.startsWith('@')) {
        return clean;
    }

    // Check if phone number (digits with optional leading +)
    let digitsOnly = clean.replace(/[^0-9]/g, '');
    const isPhoneNumber = digitsOnly.length >= 8 && digitsOnly.length <= 15 && !clean.includes(' ');

    if (isPhoneNumber) {
        // Fix Egyptian numbers starting with 01... -> +201...
        if (clean.startsWith('01') && clean.length === 11) {
            clean = '+2' + clean;
        } else if (clean.startsWith('+01') && clean.length === 12) {
            clean = '+2' + clean.substring(1);
        }

        let phone = clean.startsWith('+') ? clean : '+' + clean;
        digitsOnly = phone.replace(/[^0-9]/g, '');

        // Check if phone is user's own phone number -> Saved Messages ('me')
        try {
            const myMe = await client.getMe();
            if (myMe && myMe.phone && (phone.endsWith(myMe.phone) || myMe.phone.endsWith(digitsOnly))) {
                return 'me';
            }
        } catch (err) {}

        // Import contact to resolve user entity
        try {
            const importRes = await client.invoke(new Api.contacts.ImportContacts({
                contacts: [
                    new Api.InputPhoneContact({
                        clientId: BigInt(Date.now()),
                        phone: phone,
                        firstName: 'Contact',
                        lastName: '',
                    }),
                ],
            }));

            if (importRes.users && importRes.users.length > 0) {
                return importRes.users[0];
            }
        } catch (err) {
            console.warn(`[User ${userKey}] Contact import warning for ${phone}:`, err.message);
        }

        // If not found via import, check if entity can be resolved
        try {
            return await client.getEntity(phone);
        } catch (err) {
            throw new Error(`الرقم ${phone} غير مسجل على تطبيق Telegram أو لا يمكن العثور عليه.`);
        }
    }

    // Otherwise try getEntity directly (for channel username, chat id, etc.)
    try {
        return await client.getEntity(clean);
    } catch (err) {
        if (/^-?\d+$/.test(clean)) {
            try {
                return await client.getEntity(BigInt(clean));
            } catch (e2) {}
        }
        throw new Error(`تعذر العثور على المستلم (${clean}) في Telegram. يرجى التأكد من صحة رقم الهاتف أو اسم المستخدم @username.`);
    }
}

/**
 * Initialize or restore a user's Telegram client
 */
async function initializeClient(userId, forceNewQr = false) {
    const userKey = String(userId);

    // If client is already active and connected, return existing state
    if (clients.has(userKey)) {
        const existing = clients.get(userKey);
        if (existing.status === 'connected') {
            return existing;
        }
        if (existing.isConnecting && !forceNewQr) {
            return existing;
        }
    }

    const saved = loadSavedSession(userKey);
    const sessionString = saved?.sessionString || '';
    const stringSession = new StringSession(sessionString);

    const client = new TelegramClient(stringSession, API_ID, API_HASH, {
        connectionRetries: 5,
        useWSS: false,
    });

    const clientState = {
        client,
        sessionString: sessionString,
        user: saved?.user || null,
        status: saved?.sessionString ? 'connecting' : 'disconnected',
        qr: null,
        isConnecting: true,
    };
    clients.set(userKey, clientState);

    await client.connect();

    // If we have a saved session string, check if already authorized
    if (sessionString && !forceNewQr) {
        try {
            const isAuth = await client.checkAuthorization();
            if (isAuth) {
                const me = await client.getMe();
                clientState.user = {
                    id: String(me.id),
                    phoneNumber: me.phone ? '+' + me.phone : '',
                    firstName: me.firstName || '',
                    lastName: me.lastName || '',
                    username: me.username || '',
                };
                clientState.status = 'connected';
                clientState.isConnecting = false;
                clientState.qr = null;
                qrCodes.delete(userKey);

                saveSession(userKey, {
                    sessionString: client.session.save(),
                    user: clientState.user,
                    connectedAt: new Date().toISOString(),
                });

                attachClientHandlers(userKey, client);
                notifyLaravel('connected', {
                    userId: userKey,
                    ...clientState.user,
                });

                return clientState;
            }
        } catch (e) {
            console.warn(`[User ${userKey}] Saved session invalid, falling back to QR login:`, e.message);
            deleteSavedSession(userKey);
        }
    }

    // Start QR code login flow
    clientState.status = 'pairing';
    clientState.isConnecting = true;

    // Start QR flow in background
    (async () => {
        try {
            const user = await client.signInUserWithQrCode(
                { apiId: API_ID, apiHash: API_HASH },
                {
                    qrCode: async (code) => {
                        try {
                            const tokenBuf = Buffer.from(code.token);
                            const tokenBase64 = tokenBuf.toString('base64url');
                            const tgUrl = `tg://login?token=${tokenBase64}`;
                            const qrDataUrl = await qrcode.toDataURL(tgUrl, {
                                margin: 2,
                                width: 320,
                                color: {
                                    dark: '#000000',
                                    light: '#ffffff',
                                },
                            });

                            clientState.qr = qrDataUrl;
                            qrCodes.set(userKey, qrDataUrl);
                            notifyLaravel('qr-generated', { userId: userKey, qr: qrDataUrl });
                        } catch (err) {
                            console.error(`[User ${userKey}] Failed to render QR code:`, err);
                        }
                    },
                    onError: async (err) => {
                        console.error(`[User ${userKey}] QR Login error:`, err);
                        clientState.status = 'error';
                        clientState.isConnecting = false;
                    },
                }
            );

            // Successfully authenticated!
            const newSessionString = client.session.save();
            const me = user || await client.getMe();

            clientState.user = {
                id: String(me.id),
                phoneNumber: me.phone ? '+' + me.phone : '',
                firstName: me.firstName || '',
                lastName: me.lastName || '',
                username: me.username || '',
            };
            clientState.sessionString = newSessionString;
            clientState.status = 'connected';
            clientState.isConnecting = false;
            clientState.qr = null;
            qrCodes.delete(userKey);

            saveSession(userKey, {
                sessionString: newSessionString,
                user: clientState.user,
                connectedAt: new Date().toISOString(),
            });

            attachClientHandlers(userKey, client);

            notifyLaravel('connected', {
                userId: userKey,
                ...clientState.user,
            });
            console.log(`[User ${userKey}] Telegram connected successfully: ${clientState.user.phoneNumber}`);
        } catch (err) {
            console.error(`[User ${userKey}] signInUserWithQrCode exception:`, err.message);
            clientState.status = 'disconnected';
            clientState.isConnecting = false;
            clientState.qr = null;
            qrCodes.delete(userKey);
        }
    })();

    return clientState;
}

// ---------------- API Routes ----------------

/**
 * Get Telegram connection status & QR
 */
app.get('/api/telegram/status/:userId', requireServiceAuth, (req, res) => {
    const userId = String(req.params.userId);
    const clientState = clients.get(userId);
    const saved = loadSavedSession(userId);

    const qr = qrCodes.get(userId) || clientState?.qr || null;
    const status = clientState?.status || (saved?.sessionString ? 'connected' : 'disconnected');
    const user = clientState?.user || saved?.user || null;

    return res.json({
        success: true,
        data: {
            status: status,
            phoneNumber: user?.phoneNumber || null,
            name: [user?.firstName, user?.lastName].filter(Boolean).join(' ') || null,
            username: user?.username || null,
            telegramId: user?.id || null,
            qr: qr,
            connectedAt: saved?.connectedAt || null,
        },
    });
});

/**
 * Initialize / Request QR connection
 */
app.post('/api/telegram/connect/:userId', requireServiceAuth, async (req, res) => {
    const userId = String(req.params.userId);

    try {
        const state = await initializeClient(userId, req.query.force === 'true');
        return res.json({
            success: true,
            data: {
                status: state.status,
                phoneNumber: state.user?.phoneNumber || null,
                qr: qrCodes.get(userId) || state.qr,
            },
        });
    } catch (e) {
        return res.status(500).json({
            success: false,
            message: 'Failed to initialize Telegram connection: ' + e.message,
        });
    }
});

/**
 * Disconnect and delete session
 */
app.post('/api/telegram/disconnect/:userId', requireServiceAuth, async (req, res) => {
    const userId = String(req.params.userId);

    try {
        if (clients.has(userId)) {
            const { client } = clients.get(userId);
            try {
                await client.disconnect();
            } catch (err) {}
            clients.delete(userId);
        }
        qrCodes.delete(userId);
        deleteSavedSession(userId);

        notifyLaravel('disconnected', { userId });

        return res.json({
            success: true,
            message: 'Telegram session disconnected successfully',
        });
    } catch (e) {
        return res.status(500).json({
            success: false,
            message: 'Error disconnecting: ' + e.message,
        });
    }
});

/**
 * Send Message through Telegram User Account
 */
app.post('/api/telegram/send-message', requireServiceAuth, async (req, res) => {
    const { userId, to, message, type, mediaUrl, caption, buttonText, buttonUrl, parseMode } = req.body;

    if (!userId || !to) {
        return res.status(400).json({ success: false, message: 'userId and to are required' });
    }

    const userKey = String(userId);
    let clientState = clients.get(userKey);

    // If client in memory not ready, try to initialize from saved session
    if (!clientState || clientState.status !== 'connected') {
        const saved = loadSavedSession(userKey);
        if (saved?.sessionString) {
            clientState = await initializeClient(userKey);
        }
    }

    if (!clientState || clientState.status !== 'connected') {
        return res.status(400).json({
            success: false,
            message: 'Telegram account is not connected for this user',
        });
    }

    const client = clientState.client;

    let replyMarkup = undefined;
    if (buttonText && buttonUrl) {
        replyMarkup = new Api.ReplyInlineMarkup({
            rows: [
                new Api.KeyboardButtonRow({
                    buttons: [
                        new Api.KeyboardButtonUrl({
                            text: buttonText,
                            url: buttonUrl,
                        }),
                    ],
                }),
            ],
        });
    }

    const normalizedParseMode = parseMode ? String(parseMode).toLowerCase() : undefined;

    if (mediaUrl && !(await validateMediaUrl(mediaUrl))) {
        return res.status(400).json({
            success: false,
            message: 'Media URL is not safe or points to a private/internal address',
        });
    }

    try {
        // Resolve Peer smartly (handles phone number, @username, 'me', chat ID)
        const peer = await resolvePeer(client, to, userKey);

        let sentResult;
        if (type === 'photo' && mediaUrl) {
            sentResult = await client.sendFile(peer, {
                file: mediaUrl,
                caption: message || caption || '',
                parseMode: normalizedParseMode,
                buttons: replyMarkup,
            });
        } else if (type === 'document' && mediaUrl) {
            sentResult = await client.sendFile(peer, {
                file: mediaUrl,
                caption: message || caption || '',
                forceDocument: true,
                parseMode: normalizedParseMode,
                buttons: replyMarkup,
            });
        } else {
            const sendOptions = { message: message };
            if (normalizedParseMode) {
                sendOptions.parseMode = normalizedParseMode;
            }
            if (replyMarkup) {
                sendOptions.buttons = replyMarkup;
            }
            sentResult = await client.sendMessage(peer, sendOptions);
        }

        return res.json({
            success: true,
            message: 'Message sent successfully via Telegram account',
            data: {
                messageId: String(sentResult.id),
                date: sentResult.date,
            },
        });
    } catch (e) {
        console.error(`[User ${userKey}] Failed to send Telegram message:`, e);
        return res.status(400).json({
            success: false,
            message: 'Failed to send message via Telegram',
        });
    }
});

// Auto-restore saved sessions on startup
async function restoreSavedSessions() {
    try {
        const files = fs.readdirSync(AUTH_DIR);
        for (const f of files) {
            if (f.startsWith('session-user-') && f.endsWith('.json')) {
                const userId = f.replace('session-user-', '').replace('.json', '');
                console.log(`[Startup] Restoring Telegram session for user ${userId}...`);
                try {
                    await initializeClient(userId);
                } catch (err) {
                    console.error(`Failed to restore user ${userId}:`, err.message);
                }
            }
        }
    } catch (err) {
        console.error('Error reading auth dir on startup:', err);
    }
}

app.listen(PORT, '127.0.0.1', () => {
    console.log(`Wavixa Telegram Service running on http://127.0.0.1:${PORT}`);
    restoreSavedSessions();
});
