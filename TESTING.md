# Wavixa WhatsApp Engine — Testing Guide

## 1. Test Suite Structure
The engine features a comprehensive, 100% automated test suite powered by **Vitest**:

```
tests/
├── unit/
│   ├── noise.test.ts          # Noise_XX handshake & AES-256-GCM cipher states
│   ├── binary.test.ts         # BinaryNode encoder/decoder & token dictionaries
│   ├── proto.test.ts          # Protobuf Message & WebMessageInfo schemas
│   ├── identity.test.ts       # Ed25519, Curve25519, KeyStore encrypted vault
│   ├── session.test.ts        # State machine transitions & distributed locking
│   ├── message.test.ts        # Message formatting & media MAC crypto
│   ├── safety.test.ts         # Rate limiter, consent manager, risk engine
│   ├── monitoring.test.ts     # Health monitor, Prometheus metrics
│   ├── queue.test.ts          # FIFO queue, retries, dead letter queue
│   └── api.test.ts            # REST endpoints, auth, idempotency rejection
└── integration/
    └── concurrency.test.ts    # 100 concurrent sessions across 4 workers
```

---

## 2. Running Tests

```bash
# Run all tests once
npm test

# Run tests with live file watch
npm run test:watch

# Run with coverage report
npx vitest run --coverage
```
