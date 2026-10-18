# Wavixa WhatsApp Engine — Security Specification

## 1. Zero-Leakage Policy
The engine enforces a zero private key leakage guarantee across all components:
1. **Logs**:
   - Structured Pino logger automatically redacts `privateKey`, `secret`, `token`, `credentials`, `keyBundle`, and master secrets via regex path masking.
2. **API Responses**:
   - `DeviceIdentityManager.toJSON()` and `getPublicIdentity()` strictly emit public keys and registration identifiers. Private key bytes never touch serialized responses.
3. **Exceptions & Stack Traces**:
   - Error messages are scrubbed of cryptographic key material.

---

## 2. Encrypted Key Storage (At-Rest Vault)
- Device identity keypairs, Noise static keys, and signed prekeys are encrypted before persistence in database or disk storage.
- **Algorithm**: `AES-256-GCM` with random 96-bit IVs and 128-bit authentication tags.
- **Master Key Derivation**: Managed via `ENGINE_MASTER_KEY` with SHA-256/HKDF key derivation.
- **AAD Integrity**: The session ID is bound as Additional Authenticated Data (`wavixa:session:{sessionId}`) to prevent ciphertext relocation attacks.

---

## 3. Server-Side Request Forgery (SSRF) Protection
- Media downloads from external URLs are passed through `MediaManager.isSafeUrl()`:
  - Rejects `localhost`, `127.0.0.1`, `::1`.
  - Rejects private IPv4 ranges (`10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, `169.254.0.0/16`).
  - Restricts protocol to `http:` and `https:`.

---

## 4. Internal API & Webhook Authentication
- Internal REST endpoints require `Authorization: Bearer <ENGINE_INTERNAL_SECRET>`.
- Outgoing webhooks to Laravel core are signed using `HMAC-SHA256` in the `X-Service-Signature` header with timestamp replay defense.
