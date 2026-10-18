# Wavixa WhatsApp Engine — Protocol Specification & Architecture

## 1. Network Protocol Overview
The Wavixa WhatsApp Session Engine connects directly to WhatsApp Multi-Device edge servers (`wss://web.whatsapp.com/ws/chat`) using a clean, 4-tier network stack:
1. **WebSocket Framing Layer**
2. **Noise Protocol Framing Layer** (`Noise_XX_25519_AESGCM_SHA256`)
3. **Binary Node Codec Layer** (`BinaryNode`)
4. **Google Protocol Buffer Layer** (`ProtoRegistry`)

---

## 2. Noise Handshake (`Noise_XX`)
WhatsApp MD utilizes mutual Diffie-Hellman authentication over Curve25519 with AES-256-GCM authenticated cipher and SHA-256 hashing.

### Prologue & Pattern
- **Prologue**: `WA\x06\x02` (4 bytes)
- **Pattern**: `Noise_XX(s, rs)`
  - Message 1 (`-> e`): Client generates ephemeral keypair and transmits 32-byte public key `e`.
  - Message 2 (`<- e, ee, s, es`): Server transmits ephemeral key `re`, executes DH `ee`, transmits AES-GCM encrypted server static key `s`, and executes DH `es`.
  - Message 3 (`-> s, se, payload`): Client encrypts static identity key `s`, executes DH `se`, and transmits encrypted `ClientPayload` protobuf.

### Post-Handshake Transport Framing
- Every frame sent over WebSocket is framed as:
  - `[Length: 3 bytes Big-Endian]`
  - `[Ciphertext: Length bytes (including 16-byte AES-GCM auth tag)]`
- Each direction maintains an independent 64-bit monotonically incremented nonce formatted into a 12-byte IV:
  `[0x00, 0x00, 0x00, 0x00, Nonce[0..7]]`

---

## 3. Binary Protocol Structure
All application frames inside decrypted Noise payloads are structured as hierarchical XML-like binary nodes:

```typescript
export interface BinaryNode {
  tag: string;
  attrs: Record<string, string>;
  content?: Buffer | BinaryNode[] | string;
}
```

### Token Dictionary
- 236 single-byte tokens map frequent strings (`iq`, `message`, `receipt`, `user`, `from`, `to`, `type`, etc.) into 1-byte codes (0x01 to 0xEE) to minimize serialization overhead.
- Packed JID encoding (`0xFA`) encodes user and domain tuples compactly (`number@s.whatsapp.net`).

---

## 4. Signal Double Ratchet Encryption
1:1 and Group messages utilize Signal Protocol Double Ratchet:
- **KDF Chain**: Derived via `HKDF-SHA256` with chaining key updates.
- **Message Keys**: Derived via `HMAC-SHA256(ChainKey, 0x01)` for transmission and `0x02` for step advancement.
- **Group Messaging**: Employs Sender Keys broadcast trees so a single encrypted payload is delivered to all participants without O(N) duplicate encryption operations.

---

## 5. Protocol Versioning & Adapter Policy
Any undocumented WhatsApp tokens or frame headers:
- Are strictly isolated into `src/protocol/` adapters.
- Never pollute business logic.
- Include dedicated Vitest roundtrip serialization fixtures.
