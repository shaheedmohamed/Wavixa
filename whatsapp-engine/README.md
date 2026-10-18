# Wavixa WhatsApp Session Engine

High-performance, completely independent WhatsApp Multi-Device Session Engine built in **Node.js + TypeScript (Strict Mode)** from scratch for the Wavixa SaaS platform.

---

## Key Highlights

- **Zero External Dependencies**: Built without `Baileys`, `whatsapp-web.js`, `open-wa`, `venom`, or their wrappers.
- **Pure Cryptography Stack**:
  - `Noise_XX_25519_AESGCM_SHA256` handshake and transport framing.
  - `BinaryNode` encoder/decoder with single-byte & double-byte token dictionary.
  - Signal Protocol Double Ratchet session cipher.
  - Ed25519 identity keypairs + Curve25519 prekeys.
  - AES-256-GCM encrypted credential vault with zero private key leakage.
- **Enterprise Scalability**:
  - Designed for horizontal clustering (1,000 to 100,000+ sessions).
  - Distributed Redis locking (`session:{id}:lock`) with lease renewal preventing split-brain.
  - Ultra-lightweight: ~30-50 MB RAM per worker (eliminates Chromium overhead).
- **Safety & Anti-Abuse**:
  - Multi-dimensional sliding window rate limiter (user, device, destination).
  - Dynamic Risk Engine (0-100 score: SAFE, LOW, MEDIUM, HIGH, BLOCK SEND).
  - Recipient opt-in consent enforcement.
  - Idempotency deduplication (`message:{sessionId}:{clientMessageId}`).
- **Observability**:
  - Structured JSON logs with correlation IDs.
  - OpenMetrics / Prometheus endpoint (`/internal/metrics`).
  - Webhooks signed with HMAC-SHA256 and replay protection.

---

## Quick Start

```bash
cd /www/wwwroot/wavixa.net/whatsapp-engine
npm install
npm run build
npm test
npm start
```

---

## Documentation Index

- [Architecture Specification (ARCHITECTURE.md)](../ARCHITECTURE.md)
- [Zero-Downtime Migration Plan (MIGRATION.md)](../MIGRATION.md)
- [Protocol Specification (PROTOCOL.md)](PROTOCOL.md)
- [Security Model (SECURITY.md)](SECURITY.md)
- [Database Schema (DATABASE.md)](DATABASE.md)
- [REST API Reference (API.md)](API.md)
- [Deployment Guide (DEPLOYMENT.md)](DEPLOYMENT.md)
- [Troubleshooting (TROUBLESHOOTING.md)](TROUBLESHOOTING.md)
- [Testing Guide (TESTING.md)](TESTING.md)
