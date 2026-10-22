# Wavixa Project Notes

## Frontend Deployment

The production Nginx server serves the frontend from `/var/www/Wavixa/backend/public`. After building the frontend, copy the `dist/` files into `backend/public/`.

```bash
cd /var/www/Wavixa/frontend
npm run build
cp -r dist/* /var/www/Wavixa/backend/public/
chown -R www-data:www-data /var/www/Wavixa/backend/public
nginx -s reload
```

### Production API Base URL

The frontend uses `VITE_API_BASE_URL`. For production, this must be set to the relative path `/api` because the Laravel backend is served on the same domain via Nginx.

The file `frontend/.env.production` contains:

```
VITE_API_BASE_URL=/api
```

Rebuild and redeploy after any API URL change.

## Backend Deployment

Laravel is served by Nginx + PHP-FPM, not by the PHP built-in server. The PHP built-in server is only useful for local development. After code changes, restart PHP-FPM if needed:

```bash
service php8.5-fpm reload
# or
systemctl restart php8.5-fpm
```

## Useful Commands

- Build frontend: `cd frontend && npm run build`
- Copy dist to public: `cp -r frontend/dist/* backend/public/`
- Reload Nginx: `nginx -s reload`
- Restart PHP-FPM: `service php8.5-fpm reload` (or `systemctl restart php8.5-fpm`)
- Run Laravel migrations: `cd backend && php artisan migrate --force`
- Run tests: `cd backend && php artisan config:clear && php artisan test` —
  **always `config:clear` first**. If `bootstrap/cache/config.php` exists, the
  phpunit.xml `DB_CONNECTION=sqlite`/`:memory:` overrides are ignored and tests
  run against production MySQL (read-only in practice — `migrate:fresh` refuses
  in production — but assertions read live rows and break). Re-run
  `php artisan config:cache` afterwards for production.
- Run Laravel seeders: `cd backend && php artisan db:seed --class=PlanSeeder --force`

## System WhatsApp Sender (OTP)

System messages (registration OTP, password reset, subscription notices) must **only** be sent
from the admin number `+201011679726`. This is enforced in
`backend/app/Services/SystemWhatsAppService.php`, which matches connected sessions on the
`system_whatsapp_phone_number` setting. There is deliberately **no fallback** to other connected
devices: sending system OTPs through a customer's phone leaks the traffic and risks getting
their number banned. If the admin device is offline, `sendMessage()` returns false and logs
`sender ... is not connected, refusing to send from another device`.

Matching is done on the phone number, not the session ID, because every re-pair creates a new
`whatsapp_sessions` row with a new ID that is not in `system_whatsapp_session_ids`.

### When OTP stops working

Check whether the admin number has a `connected` session:

```bash
cd backend && php artisan tinker --execute="foreach(\App\Models\WhatsAppSession::where('status','connected')->get() as \$s){ echo \$s->id.' '.\$s->phone_number.PHP_EOL; }"
curl -s http://127.0.0.1:3002/api/whatsapp/status/<session_token>
```

A `LOGOUT` / `NAVIGATION` disconnect reason in `journalctl -u wavixa-whatsapp` means WhatsApp
unlinked the device. The service deletes the auth folder in that case, so the **only** fix is
re-pairing via QR (or pairing code) from the admin dashboard. Nothing can automate this.

Two live clients on one WhatsApp account cause a `Stream Errored (conflict)` / `LOGOUT`, which
wipes the credentials. `whatsapp-service/index.js` guards against this in `initializeClient()`
and on the `ready` event via `findLiveClientByPhone()`.

## WhatsApp Service Engine (whatsapp-web.js)

`whatsapp-service/index.js` runs on **whatsapp-web.js + Puppeteer** (real Chromium running the
official WhatsApp Web client). This carries a lower ban risk than the previous Baileys-based
service because the TLS / browser fingerprint is indistinguishable from a human user.

Each connected session spawns its own Chromium process, so `MAX_CONCURRENT_SESSIONS` defaults to
**20** (down from 50 under Baileys). Do not raise it without checking available RAM — each
Chromium instance uses ~150-250 MB.

### Protection protocols implemented

1. Per-session `LocalAuth` under `.wwebjs_auth/session-user-<id>`
2. Pairing-code flow (`requestPairingCode`) for phone-number login, QR fallback
3. Duplicate-phone guard: refuse to open a second live client on the same number
4. Human-like typing simulation (`composing` -> random delay -> `paused`), plus random `unavailable` after sending
5. `getNumberId` existence cache with randomised 40-70 min TTL
6. Per-session proxy (HTTP / HTTPS / SOCKS) via puppeteer `--proxy-server` + `proxy.txt`
7. Randomised browser fingerprint per user (UA + viewport + lang + platform)
8. Exponential backoff reconnect with jitter for registered sessions
9. Auto-restore of registered sessions on startup, staggered 5s apart
10. Concurrency cap (`MAX_CONCURRENT_SESSIONS`) to bound memory usage
11. Logged-out (`LOGOUT` / `NAVIGATION`) detection -> wipe auth folder -> notify Laravel
12. Service API key auth on `send-message` endpoint
13. Stealth puppeteer args (`--disable-blink-features=AutomationControlled` etc.)

### Backups

The previous Baileys implementation is preserved as `index.baileys.backup.js`. The legacy
`.baileys_auth/` folders are no longer read by the running service; reconnect wipes them on
explicit `connect` calls.

## Official WhatsApp Business API (Meta Cloud)

The `MetaTest` module (`backend/app/Modules/MetaTest/`) is the **live** official WhatsApp
Business integration — the "test" naming is historical. Customers onboard via
`/meta/connect` (Embedded Signup / OAuth), which stores their token, WABAs, phone
numbers, and templates in `meta_test_*` tables.

- Webhook: `GET/POST /api/meta-test/webhook` — verify token `META_TEST_WEBHOOK_VERIFY_TOKEN`.
  Subscribe the Meta app to the `messages` field in the Meta dashboard.
- Each synced WABA is auto-subscribed to the app webhook via `POST /{waba_id}/subscribed_apps`
  inside `MetaTestConnectionManager::syncWABAsAndNumbers()`.
- `META_LOGIN_CONFIG_ID` in `.env` = the "Facebook Login for Business" configuration ID;
  required so new customers can *create* a WABA inside the signup dialog.
- Bulk campaigns: `meta_test_campaigns` + `meta_test_campaign_recipients`, dispatched via
  `ProcessMetaTestCampaignJob` (database queue — `wavixa-queue.service` must be running).
  Delivery statuses flow back through the webhook into campaign recipient rows.
- Public API (X-API-Key): `/api/public/meta/numbers|templates|send-message|send-template|send-bulk|campaigns/{id}|messages/{id}`
  — `PublicMetaWhatsAppController`. Credits consumed via `SubscriptionService`.
- The older `meta_whatsapp_*` tables + `/api/whatsapp/cloud/*` routes are a dormant
  duplicate; the UI points at the meta-test module (Devices.vue calls `/meta-test/*`).

## Logging

`LOG_STACK=daily` with `LOG_LEVEL=info` and 14-day retention. Do not log per-request status
polls at info level; the dashboard polls `/api/whatsapp/status` every minute per open tab and
this previously grew `laravel.log` to 94 MB.

## Live WhatsApp Engine (whatsapp-engine, port 3005)

The CRM and all Laravel send paths (`WHATSAPP_SERVICE_URL=http://127.0.0.1:3005`) talk
to `wavixa-engine.service` — the custom protocol engine in `whatsapp-engine/` (TypeScript,
`npm run build` -> `dist/`). The older `whatsapp-service` (whatsapp-web.js, port 3002)
still runs but is not on the send path.

### Call bridge dial flow (2026-10) — WA Web removed per-chat call buttons

WhatsApp Web no longer renders "Voice call"/"Video call" icons in the chat
header (observed 2026-10-13; only "Send call link" / "New group call" remain
under the ⋮ menu). `whatsapp-callbridge/bridge.js` `doDial` therefore: opens
the chat → tries the legacy header buttons briefly → falls back to menu →
"New group call" → the picker (pre-seeded with the open chat's contact) →
green `Voice`/`Video` button. Group calls pass `{gid}@g.us` (a bare group id
becomes `…@g.us`, never `@c.us` — openChatWindow throws "No LID for user"
otherwise). `/dial` can take 10-40s walking the UI — Laravel
`CrmCallController::dial` uses a 45s timeout; never shorten it below that.

Call reliability notes (2026-10-13, second pass):

- `sessionReady` only tracks wweb.js READY; it stays true when the phone-link
  socket drops (WA shows a "Connecting to WhatsApp / Retrying" banner and
  wweb.js never emits 'disconnected'). The bridge runs a 5s watchdog that
  detects the banner, clicks "Retry now" every ~12s, and reloads the page
  after 60s stuck. `/dial` refuses while `waOnline === false`; `/state`
  exposes `online`.
- WA Web opens calls in a **separate window** — the End/Mute buttons are NOT
  in `client.pupPage`. `domClickButton` sweeps every `*.whatsapp.com` page.
- `page.evaluate` throws "Execution context was destroyed" when WA navigates
  mid-eval; every DOM helper call inside `doDial` is wrapped in try/catch and
  the whole body resets to `idle {reason:'error'}` on crash. A 90s sweeper
  clears stuck `dialing`. `/dial`/`/hangup`/… routes are wrapped so a crash
  returns `{ok:false,err}` JSON, never an Express 500 page.
- `doHangup` gives a stuck `dialing` call ~4s of End-button retries before
  resetting idle — a call that never started has no button to find, and the
  old 12×1s loop made the CRM hangup look dead.

### Dedupe on outgoing message writes (2026-10)

The engine fires `whatsapp/message-sent` the moment a send resolves — at the same time
the caller (CRM `sendMessage`/`sendMedia`, `SendWhatsAppMessageJob`) writes the row.
Both sides must serialize on the shared MySQL named lock
`wa_msg_sent_{sha1(session_id|message_id)}` and do find-or-update. Any NEW code path
that persists an outgoing message after the engine responds must use the same lock,
or duplicates will reappear (they did — same `message_id` stored twice).

### History-sync conversation ordering (2026-10)

`WhatsAppWebhookController::historySync` (both backends) must seed new
`whatsapp_conversations.last_message_at` as **null**, never `now()` — the
newest-message guard (`$newest->gt(last_message_at)`) then fills it with the
real WA timestamp. Seeding `now()` silently pinned every synced chat's order
to chunk-arrival time, so the CRM list showed old chats on top. The
conversation-level `lastMsgTimestamp` fallback can arrive in ms — normalize
(>1e11 → ÷1000) before `Carbon::createFromTimestamp`.

### Message ordering: (timestamp, id) tuple, never bare id (2026-10)

History-sync chunks stream messages NEWEST-first, so `whatsapp_messages.id`
can run opposite to real chronology (older rows get higher ids). Any read
path that pages a thread — `getMessages`, media lists — must order and
cursor by the `(timestamp, id)` tuple, not `id` alone. The API still takes
id-based cursors (`before_id`/`after_id`/`around_id`); the controller
resolves them to a row and compares the tuple. `historySync` also `usort`s
chunk messages ascending before `updateOrCreate` to keep new ids roughly
chronological.

### WhatsApp @username resolution (2026-10)

Peer usernames come from three channels:
1. `Conversation.username` (proto field 43) inside history-sync chunks —
   `HistorySyncProcessor` forwards it, `historySync()` stores it on
   `whatsapp_conversations.contact_username` (both backends).
2. usync `username` protocol query — `WaClient.fetchUsernames(jids)`, exposed
   as `POST /internal/usernames {sessionId, jids}` (max 100). The handle
   arrives as the `<username>` node's BINARY content (attrs only carry
   `state`), not an attribute.
3. Lazy backfill — `WhatsAppLidResolver::fetchUsernames` runs in the
   throttled pass inside `getConversations`. Column semantics: NULL = not
   queried, `''` = checked & none (retried after a day), string = handle.
   On-demand full history sync does NOT resend already-synced chats
   (peerDataOperation responseCode 4), so usync backfill is the only way to
   populate existing rows.

CRM frontend (`CrmChat.vue`): `convTitle(c)` shows `@username` wherever a
bare `+phone` placeholder would render; real names still win.

### Reply / forward contextInfo (2026-10)

`WaClient.sendWAMessage(jid, message, stanzaType, contextInfo)` accepts a
proto.ContextInfo object that is injected into the innermost message-type key.
Plain `conversation` texts are promoted to `extendedTextMessage` so they can
carry it. The send routes accept `replyTo: {stanzaId, participant, remoteJid,
quotedMessage}` (real quoted replies) and `forwarded: true` (official
"Forwarded" label). Laravel `CrmController::buildReplyTo()` builds the quote
payload from a stored whatsapp_messages row; `forwardMessage` re-uploads locally
stored media for media forwards.

### Anti-delete (revoked messages)

"Delete for everyone" arrives as `protocolMessage{type: REVOKE, key}` inside the
decrypted message stanza. `WaClient` intercepts it before the generic protocol drop,
emits `message.revoked` → `Session.onRevoked` → `routes.ts` → EventBus →
`WebhookDispatcher` → `POST /api/whatsapp/message-revoked` →
`WhatsAppWebhookController::messageRevoked` stamps `whatsapp_messages.revoked_at`
(the row is NEVER deleted) and pushes `message.revoked` over the CRM channel.
The bubble stays visible with a red "العميل حذف هذه الرسالة" badge.

Outbound delete-for-everyone (`WaClient.sendRevokeMessage`): one stanza per
known key form, but the stanza `to` is always the resolved @lid form for
migrated accounts — a stanza mixing @lid `to` with a PN `key.remoteJid` gets
ack error 400. `CrmController::deleteMessage` must check the engine response
body (`success`) — Laravel's HTTP client does not throw on non-2xx, and the
previous code stamped `revoked_at` even when the engine rejected the call.

### Retry receipts ("Waiting for this message")

`WaClient.handleRetryReceipt` re-encrypts a cached outbound payload when a device
reports decrypt failure via `<receipt type="retry">`. Sent payloads live in
`sentMessageCache` (in-memory, ~300 entries / 30 min) — so messages sent before an
engine restart cannot be retried. If a previously-sent message stays stuck on
"Waiting for this message", resend it manually.

### WhatsApp Statuses / stories (2026-10-15)

Status updates arrive as ordinary messages addressed to `status@broadcast`
(`from`), with the poster in `sender`/`senderPn` (or `senderLid` for
LID-posted items). They are stored in `whatsapp_statuses` (NOT
`whatsapp_messages` — never let them create a conversation/bubble):
`WhatsAppWebhookController::recordStatus` handles both the live
`message-received` intercept and the `historySync` chunk whose conversation
jid is `status@broadcast` (poster per message = `participant`, resolved via
the chunk's `lidMappings`). Anti-delete: a REVOKE whose key.remoteJid is
`status@broadcast` stamps `whatsapp_statuses.deleted_at` — the row stays.
Async media lands via `whatsapp/message-media` which patches the status row
when no `whatsapp_messages` row matches the messageId. Lazy media:
`GET /api/crm/status-media/{id}?token=` mirrors `/crm/media/{id}` and calls
the engine `/internal/media/download` with the stored `media_meta`.
API: `GET /api/crm/statuses` (mine / recent / viewed grouping by
`viewed_at`), `POST /api/crm/statuses/{id}/view`. Realtime events on the CRM
channel: `status.new`, `status.deleted`, `status.media`. Frontend page:
`pages/crm/CrmStatuses.vue` (`/crm/statuses`).

History-sync mediaKey gotcha: `HistorySyncProcessor` decodes with
`bytes: Buffer`, so `media.mediaKey` in history chunks is a raw Buffer —
`WebhookDispatcher` normalizes it to base64 in the `history.sync` handler
before posting (same as `encodeMediaMeta` does for live messages).

## CRM Split (2026-10-10) — /var/www/WavixaCRM

The WhatsApp CRM is now a **separate app** living in `/var/www/WavixaCRM`
(own Laravel backend + DB `wavixa_crm` + own frontend build + engine source),
served from its own subdomain **`crm.wavixa.net`** (added 2026-10-19):

- `crm.wavixa.net/*` → `/var/www/WavixaCRM/frontend/dist` (SPA in place),
  `/crm-static/*` assets, `/api/crm*` → CRM backend direct fastcgi
- Legacy `wavixa.net/crm/*` → 301 redirect to `crm.wavixa.net` (HTTPS)
- `wavixa.net/api/crm*` still → CRM backend `index.php` (direct fastcgi) for
  backward compatibility
- `http://127.0.0.1:8088` → internal vhost into the CRM backend — the engine
  posts a *copy* of every webhook there via `LARAVEL_API_URLS_EXTRA`
  (`wavixa-engine.service`; fan-out lives in `WebhookDispatcher::notifyOne`).
- `http://127.0.0.1:8088` → internal vhost into the CRM backend — the engine
  posts a *copy* of every webhook there via `LARAVEL_API_URLS_EXTRA`
  (`wavixa-engine.service`; fan-out lives in `WebhookDispatcher::notifyOne`).
- CRM queue worker: `wavixa-crm-queue.service`.
- CRM channel auth: `POST /api/crm/broadcasting/auth` (shared Reverb).

The main repo no longer contains CRM routes/controller/pages
(`CrmController`, `pages/crm/*`, `CrmLayout`, `services/crm.js`,
`services/realtime.js` were removed). `WhatsAppConversation`,
`WhatsAppMessage`, `CrmRealtimeEvent`, `CrmGroup`, `LoginHistory` stay —
still used by the webhook pipeline and platform features.

Frontend deploy for the CRM: `cd /var/www/WavixaCRM/frontend && npm run build`
(dist is served in place — no copy step). Backend changes: edit in place +
`php artisan config:cache` in `/var/www/WavixaCRM/backend`.

PHP-FPM pool (`/etc/php/8.5/fpm/pool.d/www.conf`): `pm.max_children` was
raised 5 → 10 on 2026-10-15 — 5 children wedged the whole CRM under normal
polling (all requests queued → nginx 110 timeouts). Each child ~55 MB; keep
an eye on RAM (box has ~4 GB) before raising further.

CRM frontend realtime gotcha: `CrmLayout` and `CrmChat` both listen on the same
`private-crm.{ownerId}` channel — `echo.private()` returns the SHARED channel
object. Pages must detach with `channel.stopListening('.crm.event', cb)` on
unmount; `echo.leave()` unsubscribes for every listener (it once would have
muted the layout's incoming-message ding). The ding lives in
`frontend/src/services/notifySound.js` (synthesized WebAudio chime,
`crm_notify_sound` localStorage toggle, unread-poll fallback when Reverb is
down).

### CRM WhatsApp auto-reply (chatbot) — 2026-10-15

The platform's auto-reply (`/auto-reply`) is ported into the CRM at
`/crm/auto-reply` (page `pages/crm/CrmAutoReply.vue`, sidebar owner-only):

- API routes live under `/api/crm/auto-reply*` pointing at the same
  `App\Modules\WhatsApp\Controllers\AutoReplyController` (settings, logs,
  test-ai). `whatsapp_auto_replies` + `whatsapp_auto_reply_logs` tables,
  `WhatsAppAutoReply(Log)` models, `AIReplyService`, `GenerateAIReplyJob`,
  `SendWhatsAppMessageJob`, and the webhook trigger block inside
  `WhatsAppWebhookController::messageReceived` were already in the CRM backend.
- **No plan gate**: CRM `SendWhatsAppMessageJob` skips the subscription
  check/consume when `isAutoReply=true`, and the webhook dispatches static
  replies with `isAutoReply=true` (bypasses 60s anti-ban pacing too — replies
  must be instant).
- Human handoff: `CrmController::sendMessage` sets
  `conversation.ai_disabled_until = now()+ai_handoff_minutes` on manual agent
  sends so the bot stays silent while a human is handling the chat.
- AI provider: OpenRouter (`OPENROUTER_API_KEY` shared with main app — it's a
  paid account). `google/gemini-2.5-flash` is the working default; the
  `*:free` gemma slugs hit upstream 429 rate limits often. Gemini direct API
  (`GEMINI_API_KEY`) is empty in both .env files.
