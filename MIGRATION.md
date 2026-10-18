# Wavixa WhatsApp Engine — Zero-Downtime Migration Plan

## 1. Overview & Objectives

This document establishes the step-by-step strategy for migrating Wavixa from the current WhatsApp infrastructure to the new, custom-built **Wavixa WhatsApp Session Engine** without any service interruption, loss of user credentials, or disruption to active campaigns.

### Guiding Principles
1. **Zero Downtime**: Existing WhatsApp sessions, campaigns, and OTP dispatches must remain operational throughout.
2. **Backward Compatibility**: Laravel API endpoints, webhooks, frontend UI, and billing systems remain unchanged.
3. **Safety & Fallback**: Every rollout step includes an instant, automated rollback path.
4. **No Premature Deletion**: Legacy services (`whatsapp-service` running `whatsapp-web.js` and backup `index.baileys.backup.js`) are left intact until the new engine has demonstrated 100% stability under production load.

---

## 2. Current State Audit & Dependency Matrix

### 2.1 Services & Processes Running
| Service | Technology | Port | Systemd Unit | Status |
| :--- | :--- | :--- | :--- | :--- |
| **Current WhatsApp Service** | Node.js + `whatsapp-web.js` (Puppeteer/Chromium) | `3002` | `wavixa-whatsapp.service` | Active (Runs Chromium instances per session) |
| **Legacy Baileys Implementation** | Node.js + `@whiskeysockets/baileys` | N/A | Preserved in `index.baileys.backup.js` | Inactive backup |
| **Laravel Backend** | PHP 8.2+ / PHP-FPM / Nginx | `80/443` | `php8.5-fpm` | Production API Server |
| **Queue Worker** | Laravel Queue Worker (`database` queue) | N/A | `wavixa-queue.service` | Processes `SendWhatsAppMessageJob` |
| **Frontend** | Vue.js 3 + Vite | Served by Nginx | N/A | SPA Dashboard |

### 2.2 In-Depth Dependency Catalog

#### Backend Controllers & Jobs Depending on WhatsApp Service
1. **`app/Modules/WhatsApp/Controllers/WhatsAppController.php`**:
   - `getStatus()`: Calls `GET /api/whatsapp/status/{nodeUserId}` on port 3002.
   - `connect()`: Calls `POST /api/whatsapp/connect/{userId}` on port 3002.
   - `disconnect()`: Calls `POST /api/whatsapp/disconnect/{nodeUserId}` on port 3002.
   - `getQR()`: Calls `GET /api/whatsapp/qr/{userId}` on port 3002.
2. **`app/Jobs/SendWhatsAppMessageJob.php`**:
   - Dispatches single and campaign messages.
   - Probes `GET /api/whatsapp/status/{nodeUserId}`.
   - Calls `POST /api/whatsapp/check-number`.
   - Dispatches `POST /api/whatsapp/send-message` and `POST /api/whatsapp/send-media`.
3. **`app/Http/Controllers/PublicApi/PublicWhatsAppController.php`**:
   - Public API key endpoints for external integrations.
   - Dispatches text, images, files, videos, audio, contacts, and OTPs.
   - Initiates remote pairing sessions via `POST /api/whatsapp/connect/{sessionToken}`.
4. **`app/Services/SystemWhatsAppService.php`**:
   - Critical system OTP router. Dispatches OTPs exclusively through the designated admin number (`+201011679726`).
5. **`app/Http/Controllers/WhatsAppWebhookController.php`**:
   - Receives HMAC-SHA256 signed webhooks from Node.js service:
     - `POST /api/whatsapp/connected`
     - `POST /api/whatsapp/disconnected`
     - `POST /api/whatsapp/qr-generated`
     - `POST /api/whatsapp/message-received` (routes to auto-reply, AI reply, and customer webhooks)
     - `POST /api/whatsapp/message-sent`
     - `POST /api/whatsapp/message-delivered`
     - `POST /api/whatsapp/message-read`
6. **`app/Console/Commands/SyncWhatsAppSessions.php`**:
   - Reconciles database rows with service health via `GET /health` and `GET /api/whatsapp/status/{id}`.

#### Frontend Modules Depending on WhatsApp
1. **`frontend/src/pages/WhatsAppConnect.vue`**:
   - Handles QR display and pairing code flow.
   - Polls `/api/whatsapp/status` every 4 seconds.
2. **`frontend/src/pages/Devices.vue`**:
   - Lists connected WhatsApp devices and provides disconnect triggers.
3. **`frontend/src/pages/SendMessage.vue`**:
   - Direct message sending UI.
4. **`frontend/src/pages/CampaignCreate.vue` & `Campaigns.vue`**:
   - Broadcast campaign execution and recipient validation.
5. **`frontend/src/pages/AutoReply.vue`**:
   - AI bot and static auto-replies triggered by incoming WhatsApp messages.

---

## 3. Migration Roadmap (Phased Execution)

### Phase 1: Architectural Foundation & Codebase Freeze (Current)
- Completed audit of all dependencies and production state.
- Generated `ARCHITECTURE.md` and `MIGRATION.md`.
- No production files altered; existing service remains untouched.

### Phase 2: Engine Development in `/whatsapp-engine`
- Initialize isolated TypeScript project in `/whatsapp-engine`:
  - `TransportLayer`: WebSocket + heartbeat.
  - `NoiseProtocol`: Noise_XX handshake with Curve25519, AES-GCM, SHA-256.
  - `BinaryProtocol`: BinaryNode encoder/decoder with token dictionary.
  - `DeviceIdentityManager`: Ed25519 identity key generation & secure encrypted vault.
  - `SessionStateMachine`: Robust multi-device lifecycle.
  - `MessageManager`: Text and media handling.
  - `RateLimiter`: Wavixa risk scoring & opt-in consent protection.
- Build complete unit test suite (target >90% coverage on crypto & protocol layers).

### Phase 3: Dual-Stack Deployment (Shadow Engine)
- Run the new engine as an independent systemd service on port **3005** (`wavixa-engine.service`).
- Port 3002 (`wavixa-whatsapp.service`) continues handling all production traffic.
- Health probes and synthetic loopback tests run on port 3005 to verify transport stability and low memory footprint (<40MB baseline vs. ~1.5GB Puppeteer).

### Phase 4: Laravel Gateway Adapter (Dual-Engine Routing)
- Introduce an abstraction layer in Laravel: `WhatsAppEngineManager`.
- Configuration switch in `backend/config/services.php`:
  ```php
  'whatsapp' => [
      'driver' => env('WHATSAPP_ENGINE_DRIVER', 'legacy'), // 'legacy' or 'engine_v2'
      'legacy_url' => env('WHATSAPP_LEGACY_URL', 'http://127.0.0.1:3002'),
      'engine_v2_url' => env('WHATSAPP_ENGINE_URL', 'http://127.0.0.1:3005'),
      'api_key' => env('WHATSAPP_SERVICE_API_KEY'),
  ]
  ```
- Both engines expose the identical REST & Webhook interface contracts, enabling per-session or per-user driver selection.

### Phase 5: Controlled Canary Rollout
1. **Step 5.1 (Internal Admin Testing)**:
   - Route admin test session to `engine_v2`.
   - Verify QR scan, multi-device handshake, message send, incoming auto-reply, and delivery receipts.
2. **Step 5.2 (10% New Device Pairings)**:
   - Any newly created device connection is routed to `engine_v2`.
   - Existing connected sessions remain on legacy service to avoid force-disconnecting paying customers.
3. **Step 5.3 (System OTP Switch)**:
   - When verified stable for 7 consecutive days, re-pair admin number `+201011679726` onto `engine_v2`.
4. **Step 5.4 (100% Migration)**:
   - All new sessions default to `engine_v2`.
   - When older legacy sessions disconnect or expire, users re-link seamlessly into `engine_v2`.

### Phase 6: Decommissioning of Legacy Service
- When active sessions on port 3002 reach 0:
  - Stop and disable `wavixa-whatsapp.service`.
  - Reclaim server memory (~200MB per Chromium process eliminated).
  - Update Nginx and port bindings.
  - Archive legacy code safely.

---

## 4. Rollback Plan

If at any point during Phase 4 or 5 an issue occurs:
1. Set `WHATSAPP_ENGINE_DRIVER=legacy` in `backend/.env`.
2. Reload PHP-FPM: `service php8.5-fpm reload`.
3. All requests immediately revert to port 3002.
4. Total rollback time: < 3 seconds with zero data loss.
