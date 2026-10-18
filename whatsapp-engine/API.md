# Wavixa WhatsApp Engine — API Reference

All protected endpoints require authentication via Bearer Token:
`Authorization: Bearer <ENGINE_INTERNAL_SECRET>`

---

## 1. Internal Session Management

### `POST /internal/sessions`
Initialize a session slot.
- **Request Body**:
  ```json
  {
    "sessionId": "session_user_117",
    "userId": "117",
    "phoneNumber": "201011679726"
  }
  ```
- **Response** (201 Created):
  ```json
  {
    "success": true,
    "message": "Session slot initialized",
    "data": {
      "sessionId": "session_user_117",
      "userId": "117",
      "state": "DISCONNECTED"
    }
  }
  ```

### `GET /internal/sessions/:id`
Retrieve session state, QR representation, or active pairing code.
- **Response** (200 OK):
  ```json
  {
    "success": true,
    "status": "connected",
    "mode": "qr",
    "phoneNumber": "201011679726",
    "name": "Wavixa Admin",
    "qr": "data:image/png;base64,...",
    "pairingCode": null
  }
  ```

### `POST /internal/sessions/:id/connect`
Initiate connection and device linking.
- **Request Body**:
  ```json
  {
    "phoneNumber": "201011679726" // optional for pairing code flow
  }
  ```
- **Response** (200 OK):
  ```json
  {
    "success": true,
    "message": "Connection initialized",
    "mode": "phone",
    "pairingCode": "ABCD-1234",
    "qr": null
  }
  ```

### `POST /internal/sessions/:id/disconnect`
Disconnect live socket and release distributed worker lock.

### `DELETE /internal/sessions/:id`
Wipe credentials, delete stored key bundle, and disconnect.

---

## 2. Messaging Endpoints

### `POST /internal/messages/send`
Enqueue outgoing message with opt-in verification and idempotency protection.
- **Request Body**:
  ```json
  {
    "sessionId": "session_user_117",
    "to": "201012345678",
    "type": "text", // "text" | "image" | "video" | "document" | "location" | "contact"
    "message": "Hello from Wavixa Engine",
    "clientMessageId": "unique-uuid-12345"
  }
  ```
- **Response** (200 OK):
  ```json
  {
    "success": true,
    "messageId": "3EB0ABC123456789",
    "status": "sent"
  }
  ```

---

## 3. Observability & Health

### `GET /health` & `GET /internal/health`
Return cluster operational health summary.

### `GET /internal/metrics`
Expose OpenMetrics / Prometheus telemetry metrics.
