# Wavixa WhatsApp Session Engine — System Architecture & Specification

## 1. Executive Summary & Vision

The **Wavixa WhatsApp Session Engine** is an enterprise-grade, independent, and modular session engine designed to power WhatsApp Web / Multi-Device communication natively in **Node.js + TypeScript (Strict Mode)**.

### Fundamental Principles & Constraints
1. **Zero External WhatsApp Library Dependencies**:
   - Absolutely no usage of or code copying from `Baileys`, `whatsapp-web.js`, `open-wa`, `venom`, or their wrappers/forks.
   - Full in-house implementation of the underlying network protocol stack: WebSocket transport, Noise Protocol handshake/framing, binary node serialization, and Signal Protocol encryption.
2. **Protocol Integrity over Anti-Detection Gimmicks**:
   - Strictly **no** anti-ban evasion hacks, fake User-Agent rotations, browser fingerprint forging, or IP spoofing.
   - Long-term reliability is achieved through strict cryptographic accuracy, protocol compliance, defensive internal rate-limiting, opt-in enforcement, and respectful resource handling.
3. **Decoupled SaaS Architecture**:
   - Clean microservices boundary between the Laravel 12 application (handling auth, users, billing, API keys, dashboard, campaign management) and the WhatsApp Session Engine (handling low-level network protocol, crypto, and session states).
4. **Massive Scalability & Fault Tolerance**:
   - Designed from day one to scale horizontally from 100 to 1,000, 10,000, and 100,000+ sessions using stateless worker clusters, Redis distributed locks, and active lease management without split-brain risk.
5. **Zero-Downtime Migration**:
   - Gradual phased transition ensuring existing users, OTP systems, and campaigns remain 100% operational throughout the development and deployment cycles.

---

## 2. High-Level System Architecture

```
┌────────────────────────────────────────────────────────────────────────┐
│                          Client Layer                                  │
│             Vue.js 3 Frontend (Dashboard / Devices / Connect)          │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │ HTTPS / REST / SSE
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                        Wavixa SaaS Core (Laravel 12)                   │
│  ├── Authentication & RBAC        ├── Device Management (API)          │
│  ├── Billing & Subscriptions      ├── Campaign Engine                  │
│  ├── Customer Contacts & Opt-Ins  ├── Webhook Ingestion & Dispatch     │
│  ├── System Settings (Admin OTP)  └── Safety & Reputation Scoring      │
└───────────────────┬────────────────────────────────┬───────────────────┘
                    │                                │
    Internal REST   │                                │ DB / Queue
    HMAC-SHA256     ▼                                ▼
┌───────────────────────────────┐        ┌───────────────────────────────┐
│     Redis Data Infrastructure │        │        MySQL Database         │
│  ├── Distributed Session Locks│        │  ├── whatsapp_sessions        │
│  ├── Per-Session Send Queues  │        │  ├── whatsapp_credentials     │
│  ├── Worker Heartbeat & Leases│        │  ├── whatsapp_messages        │
│  ├── Event Bus (Pub/Sub)      │        │  ├── whatsapp_health          │
│  └── Idempotency Dedup Store  │        │  └── whatsapp_audit_logs      │
└───────────────┬───────────────┘        └───────────────┬───────────────┘
                │                                        │
                └───────────────────┬────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│              Wavixa WhatsApp Session Engine Cluster                    │
│                                                                        │
│  ┌───────────────────────┐ ┌───────────────────────┐ ┌───────────────┐ │
│  │   Engine Worker #1    │ │   Engine Worker #2    │ │ Worker #N     │ │
│  │  (Assigned Sessions)  │ │  (Assigned Sessions)  │ │ ...           │ │
│  └───────────┬───────────┘ └───────────┬───────────┘ └───────┬───────┘ │
│              │                         │                     │         │
│  ════════════╪═════════════════════════╪═════════════════════╪═══════  │
│              ▼                         ▼                     ▼         │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │                    Modular Layered Protocol Stack                │  │
│  │  [Layer 16] Internal REST API & Webhook Dispatcher               │  │
│  │  [Layer 15] Health Monitor & Structured Metrics                  │  │
│  │  [Layer 14] Reconnect Manager (Exp Backoff + Jitter)             │  │
│  │  [Layer 13] Rate Limiter & Risk Engine (0-100 Score)             │  │
│  │  [Layer 12] Per-Session Queue & Distributed Locking             │  │
│  │  [Layer 11] Message & Media Manager (Text, Media, Receipts)      │  │
│  │  [Layer 10] App-State Sync Manager (Patches & Collections)       │  │
│  │  [Layer  9] QR & Device Linking Coordinator                     │  │
│  │  [Layer  8] Session Persistence & Key Vault (AES-256-GCM)        │  │
│  │  [Layer  7] Multi-Device Session State Machine                   │  │
│  │  [Layer  6] Signal Protocol / End-to-End Encryption             │  │
│  │  [Layer  5] Device Identity Manager (Ed25519 / Curve25519)       │  │
│  │  [Layer  4] Protobuf Registry & Serialization                    │  │
│  │  [Layer  3] Binary Protocol (BinaryNode Encoder/Decoder)         │  │
│  │  [Layer  2] Noise Protocol Transport (Noise_XX Handshake)        │  │
│  │  [Layer  1] Base Transport (WebSocket / TCP Abstraction)         │  │
│  └──────────────────────────────────────────────────────────────────┘  │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │ Secure WebSocket (WSS)
                                    ▼
                        WhatsApp Multi-Device Edge
```

---

## 3. Deep Protocol Layer Specifications

### Layer 1: Transport Layer
- **Interface Definition**:
  ```typescript
  export interface ITransport {
    connect(url: string): Promise<void>;
    disconnect(code?: number, reason?: string): Promise<void>;
    send(data: Buffer): Promise<void>;
    onMessage(callback: (data: Buffer) => void): void;
    onClose(callback: (code: number, reason: string) => void): void;
    onError(callback: (error: Error) => void): void;
    readonly isConnected: boolean;
  }
  ```
- **Implementation**:
  - `WebSocketTransport` wrapping native standards-compliant WebSocket (`ws` engine).
  - Explicit ping/pong monitoring with configurable interval (20s) and timeout (10s).
  - Clean error isolation and automatic socket teardown.

### Layer 2: Noise Protocol Layer (`NoiseTransport`)
- **Protocol**: `Noise_XX_25519_AESGCM_SHA256` pattern:
  - Pattern: `XX` (Mutual authentication where both initiator and responder reveal identities to each other with forward secrecy).
  - Diffie-Hellman: Curve25519 (`X25519`).
  - Cipher: `AES-256-GCM` (authenticated encryption with 128-bit tag).
  - Hash & KDF: `SHA-256` and `HKDF-SHA256`.
- **Primitives**: Standard Node.js `crypto` primitives and audited libraries (`@noble/hashes`, `@noble/ciphers`). Zero homegrown crypto algorithms.
- **Handshake Sequence**:
  1. `-> e`: Initiator sends ephemeral public key.
  2. `<- e, ee, s, es`: Responder responds with ephemeral, DH(ee), encrypted static key, DH(es).
  3. `-> s, se`: Initiator sends encrypted static identity key, DH(se).
- **Transport Phase Framing**:
  - Every transport frame is encrypted with AES-GCM using derived symmetric keys (`readKey`, `writeKey`) and 64-bit monotonically incremented nonces.
  - Frame structure: `[3-byte length header] + [AES-GCM ciphertext + 16-byte tag]`.

### Layer 3: Binary Protocol Layer (`BinaryProtocol`)
- **Data Structure**:
  ```typescript
  export interface BinaryNode {
    tag: string;
    attrs: Record<string, string>;
    content?: Buffer | BinaryNode[] | string;
  }
  ```
- **Codec Engine**:
  - `encodeNode(node: BinaryNode): Buffer`
  - `decodeNode(buffer: Buffer): BinaryNode`
- **Token Dictionary**:
  - Single-byte tokens (0x01 through 0xEE) mapping to recurring WhatsApp XML/protocol strings (`message`, `chat`, `receipt`, `iq`, `presence`, etc.).
  - Double-byte dictionary support for extended token namespaces.
  - Fully decoupled from business logic, supported by exhaustive test fixtures.

### Layer 4: Protobuf Layer (`ProtoRegistry`)
- **Architecture**:
  - Typed Google Protocol Buffers compiled from validated schema definitions for Multi-Device synchronization and message transport (`Message`, `WebMessageInfo`, `SyncActionValue`, etc.).
  - Schema catalog is decoupled into `src/protocol/proto/` to allow rapid schema version bumps without altering application controllers.
  - Strictly typed encoders/decoders for end-to-end payloads.

### Layer 5: Device Identity & Key Management (`DeviceIdentityManager`)
- **Key Pairs**:
  - `IdentityKey`: Ed25519 signing keypair + Curve25519 public key equivalent.
  - `SignedPreKey`: Curve25519 keypair signed with the identity key.
  - `OneTimePreKeys`: Pool of pre-generated Curve25519 keys for initial session negotiation.
  - `RegistrationId`: 32-bit random device registration integer.
- **Companion Device Registration**:
  - Standardized multi-device pairing identity payload (`ClientPayload`) containing client version, platform identifiers, and signed device details.
- **Zero-Leakage Security Guarantee**:
  - Private keys and sensitive secrets are stored encrypted at rest with `AES-256-GCM` using an application master secret (`ENGINE_MASTER_KEY`).
  - Private keys are never serialized in API payloads, logs, error stack traces, or metrics.

### Layer 6: Signal Protocol & End-to-End Encryption
- **Encryption Pipeline**:
  - Implementation of Double Ratchet, Diffie-Hellman Prekey Bundles, and Sender Keys (for group broadcast scaling).
  - Outgoing 1:1 messages: encrypted for recipient device JIDs using active session ratchets.
  - Incoming 1:1 messages: decrypted through sender ratchet state, with auto-healing out-of-order counter tracking.

### Layer 7: Multi-Device Session State Machine
```
   [ DISCONNECTED ] ──► (Initiate) ──► [ CONNECTING ]
          ▲                                    │
          │ (Network / Heartbeat Fail)         ▼
   [ RECONNECTING ] ◄───────────────── [ AUTHENTICATING ]
          │                                    │
          │                                    ▼ (Handshake & Sync OK)
          │                             [  CONNECTED  ]
          │                                    │
          │                                    ▼
          │                             [   SYNCING   ]
          │                                    │
          │                                    ▼ (App-State Settled)
          └─────────────────────────── [    READY    ]
                                               │
                                               ▼ (Device unlinked / 401)
                                        [ LOGGED_OUT ]
```
- Each session explicitly maintains: `sessionId`, `deviceIdentity`, `credentials`, `connectionState`, `syncState`, `lastSeen`, `health`.

### Layer 8: Session Store & Persistence
- **Two-Tier Storage Abstraction**:
  - **Fast Distributed Layer (Redis)**: Session leases, distributed mutex locks, real-time presence, rate limit sliding windows, and in-flight send queues.
  - **Persistent Relational Layer (MySQL)**: Encrypted credentials (`whatsapp_credentials`), session status audit logs (`whatsapp_audit_logs`), device metadata (`whatsapp_devices`), and health logs.

### Layer 9: QR & Device Linking Coordinator
- **Pairing Representation**:
  - Companion registration payload represented either as standard alphanumeric pairing code (for direct phone input) or SVG/PNG QR representation.
  - Dynamic expiration (20s - 45s TTL per QR cycle), auto-refreshing up to a strict pairing timeout.
  - Immediate transition to `AUTHENTICATING` upon phone app scan, storing verified credentials upon successful server acknowledgement.

### Layer 10: App-State Synchronization
- **Sync Protocol**:
  - Handles synchronization of mutations and contact states across linked devices via collections (`critical_block`, `critical_unblock_low`, `regular_high`, `regular_low`, `regular`).
  - Verification of patch macs and snapshot hashes to maintain consistent state without memory exhaustion.

### Layer 11: Message Manager
- **Supported Message Types**:
  - `Text`: plain text, formatted Markdown, quoted/reply context, link previews.
  - `Media`: Image, Video, Audio, Voice Note (`PTT`), Document, Contact (`vCard`), Location, Reaction.
- **Delivery Receipts**:
  - Standardized acknowledgement loop: `server-ack` -> `delivered` -> `read` (blue check).
  - Dispatch corresponding webhooks back to Laravel.

### Layer 12: Queue System & Worker Affinity
- **Redis Queue Architecture**:
  ```
  POST /internal/messages/send
               │
               ▼
   [ Session Rate Validation ]
               │
               ▼
   [ Redis Queue: queue:session:{sessionId} ]
               │
               ▼
   [ Worker with active lock: session:{sessionId}:lock ]
               │
               ▼
      [ NoiseTransport Send ]
  ```
- **Strict Invariant**: Exactly **one** worker holds the active lock `session:{sessionId}:lock` at any instant. Concurrent worker takeover is prevented via atomic Redis Redlock/TTL renewal.

### Layer 13: Rate Limiting & Safety Engine (Anti-Abuse)
- **Multi-Dimensional Rate Limiting**:
  - Per-User Limit (e.g. 50 msg/min)
  - Per-Device Limit (e.g. 15 msg/min with randomized pacing)
  - Per-Destination Limit (e.g. max 1 unreplied message/recipient)
  - Per-Campaign Limit (adaptive velocity pacing)
- **Wavixa Safety Engine Risk Score**:
  - Dynamic score `0 - 100` based on failure rates, recipient blocks, opt-out rate, and velocity spikes.
  - `0 - 20`: **SAFE** (Full throughput).
  - `21 - 40`: **LOW** (Standard pacing).
  - `41 - 60`: **MEDIUM** (Enforce 3x delay between sends).
  - `61 - 80`: **HIGH** (Throttled, user notification in dashboard).
  - `81 - 100`: **BLOCK SEND** (Execution halted, campaign paused, requires admin review).
- **Opt-In Protection**:
  - Enforces recipient consent: `UNKNOWN`, `OPTED_IN`, `OPTED_OUT`, `BLOCKED`.
  - Messages to `OPTED_OUT` or `BLOCKED` recipients are instantly rejected before queueing.
- **Idempotency Guarantee**:
  - Unique idempotency key `idempotency:message:{sessionId}:{clientMessageId}` cached for 24h to prevent accidental duplicate dispatches.

### Layer 14: Reconnect Manager
- **Resilience Strategy**:
  - Truncated exponential backoff with full jitter:
    `Delay = min(MAX_DELAY, BASE_DELAY * 2^attempt) * random(0.5, 1.5)`
  - Maximum reconnect attempts (e.g. 15 tries) before entering `DEGRADED` or `OFFLINE` status and notifying device owner.
  - Never executes tight reconnect loops that overwhelm WhatsApp servers.

### Layer 15: Observability & Health Monitoring
- **Structured JSON Logging**:
  - Every log entry includes: `timestamp`, `level`, `request_id`, `session_id`, `user_id`, `message_id`, `worker_id`, `event`.
  - Complete omission of sensitive payload content and cryptographic keys.
- **Metrics Exporter**:
  - OpenMetrics / Prometheus endpoint `/internal/metrics`:
    `sessions_total{status="connected|disconnected|ready"}`
    `messages_processed_total{status="sent|failed|delivered"}`
    `queue_depth{session_id="..."}`
    `reconnect_attempts_total`
    `worker_memory_rss_bytes`
    `worker_cpu_usage_ratio`

### Layer 16: Event Bus & Webhook Layer
- **Unified Webhook Payload**:
  ```json
  {
    "event": "message.received",
    "session_id": "session_117",
    "message_id": "3EB0ABC123456789",
    "from": "201012345678",
    "timestamp": 1726315200,
    "data": {
      "type": "text",
      "body": "Hello Wavixa",
      "is_group": false
    }
  }
  ```
- **Webhook Security**:
  - Every payload signed using `HMAC-SHA256` in header `X-Service-Signature`.
  - Replay attack protection: signed timestamp verified within 60-second window.

### Layer 17: Internal API Contract (Laravel <-> Engine)
All endpoints secured via header `Authorization: Bearer <INTERNAL_API_SECRET>`.
- `POST /internal/sessions` — Initialize a session slot.
- `GET  /internal/sessions/:id` — Retrieve status, QR representation, or pairing code.
- `POST /internal/sessions/:id/connect` — Begin connection / pairing.
- `POST /internal/sessions/:id/disconnect` — Gracefully disconnect socket and release locks.
- `DELETE /internal/sessions/:id` — Wipe credentials and session metadata.
- `POST /internal/messages/send` — Submit message to session queue.
- `GET  /internal/health` — Cluster-wide and worker-level health status.

---

## 4. Database Schema Specifications (Engine Additions)

To support complete independence and high concurrency without mutating existing production tables prematurely:

```sql
-- 1. Devices registered to users
CREATE TABLE IF NOT EXISTS whatsapp_devices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    session_id VARCHAR(64) NOT NULL UNIQUE,
    phone_number VARCHAR(32) NULL,
    push_name VARCHAR(128) NULL,
    platform VARCHAR(64) DEFAULT 'wavixa-engine',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_user_id (user_id)
);

-- 2. Encrypted credentials and Signal keys (zero raw private keys)
CREATE TABLE IF NOT EXISTS whatsapp_credentials (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL UNIQUE,
    registration_id INT UNSIGNED NOT NULL,
    identity_key_public BLOB NOT NULL,
    encrypted_key_bundle MEDIUMBLOB NOT NULL, -- AES-256-GCM encrypted
    key_bundle_iv VARBINARY(16) NOT NULL,
    key_bundle_tag VARBINARY(16) NOT NULL,
    version INT UNSIGNED DEFAULT 1,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

-- 3. Detailed message statuses & receipts
CREATE TABLE IF NOT EXISTS whatsapp_message_statuses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL,
    message_id VARCHAR(128) NOT NULL,
    status ENUM('queued', 'sending', 'sent', 'delivered', 'read', 'failed') NOT NULL,
    error_code VARCHAR(64) NULL,
    error_message TEXT NULL,
    timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session_msg (session_id, message_id)
);

-- 4. Session Health & Real-time Metrics
CREATE TABLE IF NOT EXISTS whatsapp_health (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL UNIQUE,
    worker_id VARCHAR(64) NOT NULL,
    status ENUM('HEALTHY', 'DEGRADED', 'UNHEALTHY', 'OFFLINE') NOT NULL,
    ping_latency_ms INT UNSIGNED DEFAULT 0,
    queue_size INT UNSIGNED DEFAULT 0,
    error_count INT UNSIGNED DEFAULT 0,
    reconnect_count INT UNSIGNED DEFAULT 0,
    last_seen_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

-- 5. Audit logs for administrative security
CREATE TABLE IF NOT EXISTS whatsapp_audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL,
    event VARCHAR(64) NOT NULL,
    details JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session_event (session_id, event)
);
```

---

## 5. Zero-Downtime Migration Strategy

```
Phase 1: Architecture & Current Codebase Audit (Current)
   │  • Full dependency mapping (Baileys & whatsapp-web.js)
   │  • ARCHITECTURE.md and protocol contracts locked
   ▼
Phase 2: Independent Engine Development in /whatsapp-engine
   │  • Node.js + TypeScript (Strict), Vitest test suite
   │  • Transport, Noise Protocol, BinaryNode, Signal layers
   │  • QR / Linking, Messaging, Session State Machine
   ▼
Phase 3: Dual-Stack Shadow Deployment
   │  • New Engine runs alongside existing service (e.g. port 3004)
   │  • Zero traffic routed to production users yet
   │  • Synthetic integration tests & crypto validation tests
   ▼
Phase 4: Selective Pilot Run (Internal Test Sessions)
   │  • Admin test device linked via new Engine
   │  • Test message queues, media transfers, webhooks to Laravel
   │  • Verification of zero false-positive disconnects
   ▼
Phase 5: Progressive Rollout (Canary Migration)
   │  • Route 10% -> 25% -> 50% -> 100% of new device pairings to new Engine
   │  • Old connected sessions remain on legacy service until natural re-pair
   │  • System WhatsApp sender (Admin OTP) switched upon proven stability
   ▼
Phase 6: Full Retirement of Legacy Stack
   │  • Legacy Puppeteer / Chromium / Baileys processes stopped
   │  • Server RAM reclaimed (~150MB per session saved)
   │  • Full system standardized on Wavixa Engine
```

---

## 6. Project Directory Layout (`/whatsapp-engine`)

The new engine will be structured cleanly under `/www/wwwroot/wavixa.net/whatsapp-engine`:

```
/whatsapp-engine
├── package.json
├── tsconfig.json
├── .eslintrc.json
├── .prettierrc
├── vitest.config.ts
├── Dockerfile
├── docker-compose.yml
├── src/
│   ├── index.ts                     # Engine bootstrap & HTTP server
│   ├── config/                      # Environment and tuning parameters
│   ├── transport/                   # Layer 1: WebSocket & socket abstraction
│   │   ├── ITransport.ts
│   │   └── WebSocketTransport.ts
│   ├── noise/                       # Layer 2: Noise_XX handshake & cipher
│   │   ├── NoiseTransport.ts
│   │   ├── NoiseHandshake.ts
│   │   └── CipherState.ts
│   ├── protocol/                    # Layer 3 & 4: Binary node & Protobuf
│   │   ├── binary/
│   │   │   ├── BinaryNode.ts
│   │   │   ├── Encoder.ts
│   │   │   ├── Decoder.ts
│   │   │   └── TokenDictionary.ts
│   │   └── proto/
│   │       ├── ProtoRegistry.ts
│   │       └── generated/
│   ├── identity/                    # Layer 5: Device keys & identity
│   │   ├── DeviceIdentityManager.ts
│   │   └── KeyStore.ts
│   ├── crypto/                      # Layer 6: Signal crypto & primitives
│   │   ├── Curve25519.ts
│   │   ├── AesGcm.ts
│   │   ├── Hkdf.ts
│   │   └── SignalSession.ts
│   ├── session/                     # Layer 7 & 8: Session state & storage
│   │   ├── Session.ts
│   │   ├── SessionStateMachine.ts
│   │   ├── SessionStore.ts
│   │   └── RedisSessionStore.ts
│   ├── linking/                     # Layer 9: QR & pairing code flow
│   │   ├── QRManager.ts
│   │   └── PairingCodeManager.ts
│   ├── sync/                        # Layer 10: App state synchronization
│   │   └── AppStateSyncManager.ts
│   ├── message/                     # Layer 11: Message formatting & media
│   │   ├── MessageManager.ts
│   │   └── MediaManager.ts
│   ├── queue/                       # Layer 12: Redis queue & distributed locks
│   │   ├── MessageQueue.ts
│   │   └── DistributedLock.ts
│   ├── safety/                      # Layer 13: Rate limiter & risk engine
│   │   ├── MessageRateLimiter.ts
│   │   ├── RiskEngine.ts
│   │   └── ConsentManager.ts
│   ├── reconnect/                   # Layer 14: Exponential backoff
│   │   └── ReconnectManager.ts
│   ├── monitoring/                  # Layer 15: Health check & metrics
│   │   ├── HealthMonitor.ts
│   │   └── MetricsCollector.ts
│   ├── events/                      # Layer 16: Event bus & webhook dispatcher
│   │   ├── EventBus.ts
│   │   └── WebhookDispatcher.ts
│   └── api/                         # Layer 17: Internal REST endpoints
│       ├── routes.ts
│       └── middleware/
│           ├── auth.ts
│           └── errorHandler.ts
└── tests/
    ├── unit/
    │   ├── transport.test.ts
    │   ├── noise.test.ts
    │   ├── binary.test.ts
    │   ├── identity.test.ts
    │   └── safety.test.ts
    └── integration/
        ├── session.test.ts
        └── queue.test.ts
```

---

## 7. Unknown & Non-Standard Protocol Protocols Policy

In accordance with strict system rules:
1. If any undocumented WhatsApp binary token or behavior is encountered:
   - It will be isolated immediately into an explicit **Protocol Adapter**.
   - It will be documented clearly in `PROTOCOL.md` with known facts vs. unknowns.
   - Comprehensive unit tests will be created around the adapter.
   - No guesswork or undocumented assumptions will be baked into the business logic.
