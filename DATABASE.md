# Wavixa WhatsApp Engine — Database & Storage Architecture

## 1. Storage Architecture Overview
The engine adopts a decoupled two-tier storage model:
1. **Redis (Fast Distributed Layer)**:
   - In-memory distributed locks: `lock:session:{sessionId}` (Redlock lease model).
   - Real-time session routing & active worker sets: `active_sessions`.
   - Idempotency & deduplication cache: `message:{sessionId}:{clientMessageId}` (24h TTL).
   - Fast queue buffers.
2. **MySQL (Persistent Relational Layer)**:
   - Device registry, encrypted cryptographic credentials, receipt audit trails, and health records.

---

## 2. Table Schemas

### `whatsapp_devices`
Tracks all linked companion devices per user:
- `id` (BIGINT AUTO_INCREMENT)
- `user_id` (FK to `users.id`)
- `session_id` (VARCHAR(64) UNIQUE)
- `phone_number` (VARCHAR(32))
- `push_name` (VARCHAR(128))
- `platform` (VARCHAR(64))
- `is_active` (BOOLEAN)
- `created_at`, `updated_at`

### `whatsapp_credentials`
Stores encrypted key bundles (zero unencrypted private keys):
- `id` (BIGINT AUTO_INCREMENT)
- `session_id` (VARCHAR(64) UNIQUE)
- `registration_id` (INT UNSIGNED)
- `identity_key_public` (BLOB)
- `encrypted_key_bundle` (MEDIUMTEXT, Base64 AES-256-GCM ciphertext)
- `key_bundle_iv` (VARCHAR(64))
- `key_bundle_tag` (VARCHAR(64))
- `version` (INT UNSIGNED)

### `whatsapp_message_statuses`
Audit log of message status receipts:
- `id` (BIGINT AUTO_INCREMENT)
- `session_id` (VARCHAR(64))
- `message_id` (VARCHAR(128))
- `status` (`queued`, `sending`, `sent`, `delivered`, `read`, `failed`)
- `error_code` (VARCHAR(64))
- `error_message` (TEXT)
- `timestamp` (TIMESTAMP)

### `whatsapp_health`
Worker telemetry and session health metrics:
- `id` (BIGINT AUTO_INCREMENT)
- `session_id` (VARCHAR(64) UNIQUE)
- `worker_id` (VARCHAR(64))
- `status` (`HEALTHY`, `DEGRADED`, `UNHEALTHY`, `OFFLINE`)
- `ping_latency_ms` (INT UNSIGNED)
- `queue_size` (INT UNSIGNED)
- `error_count` (INT UNSIGNED)
- `reconnect_count` (INT UNSIGNED)
- `last_seen_at` (TIMESTAMP)

### `whatsapp_audit_logs`
Security and administrative event trail:
- `id` (BIGINT AUTO_INCREMENT)
- `session_id` (VARCHAR(64))
- `event` (VARCHAR(64))
- `details` (JSON)
- `ip_address` (VARCHAR(45))
- `created_at` (TIMESTAMP)
