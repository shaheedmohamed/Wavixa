# Wavixa WhatsApp Engine — Troubleshooting Guide

## 1. Quick Diagnostics

```bash
# Check service health
curl -s http://127.0.0.1:3005/health

# Check Prometheus metrics
curl -s http://127.0.0.1:3005/internal/metrics

# Inspect live logs
journalctl -u wavixa-engine -n 100 -f
```

---

## 2. Common Scenarios & Resolutions

### Problem 1: `Session is locked by another active worker`
- **Cause**: A previous worker node crashed or another worker is actively processing the session.
- **Resolution**:
  - Verify worker heartbeat in Redis.
  - The lock automatically expires after 30 seconds (`ttlMs`).
  - To force-release if worker is dead:
    ```bash
    redis-cli del wavixa:wa:lock:session:<sessionId>
    ```

### Problem 2: `Invalid service authentication secret (401)`
- **Cause**: The `Authorization: Bearer <token>` does not match `ENGINE_INTERNAL_SECRET` in `.env`.
- **Resolution**: Check `ENGINE_INTERNAL_SECRET` in `whatsapp-engine/.env` and `WHATSAPP_SERVICE_API_KEY` in `backend/.env`.

### Problem 3: `Media MAC authentication failure`
- **Cause**: Media file was tampered with, truncated during network transport, or derived key mismatch.
- **Resolution**: Verify file size matches `fileLength` in the Protobuf header and ensure the full buffer was transferred.

### Problem 4: `Device sending rate limit reached (429)`
- **Cause**: Too many messages sent in a sliding window (>30 msg/min).
- **Resolution**: The response includes `retryAfterMs`. Enforce delay in queue or adjust sliding window configuration in `src/safety/MessageRateLimiter.ts`.
