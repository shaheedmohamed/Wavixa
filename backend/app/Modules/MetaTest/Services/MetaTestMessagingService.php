<?php

namespace App\Modules\MetaTest\Services;

use App\Models\WhatsAppMessage;
use App\Modules\MetaTest\Exceptions\MetaTestApiException;
use App\Modules\MetaTest\Models\MetaTestMessage;
use App\Modules\MetaTest\Models\MetaTestPhoneNumber;
use App\Modules\MetaTest\Models\MetaTestTemplate;
use App\Modules\MetaTest\Models\MetaTestWaba;
use App\Services\PhoneNormalizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Send and receive WhatsApp messages through the official Cloud API.
 */
class MetaTestMessagingService
{
    protected MetaTestGraphClient $client;
    protected ?int $apiKeyId = null;

    public function __construct(?int $userId = null, ?int $apiKeyId = null)
    {
        $this->client = new MetaTestGraphClient($userId);
        $this->apiKeyId = $apiKeyId;
    }

    /**
     * Attribute subsequently-sent messages to a public API key so admins
     * can report per-key Meta usage and billable cost.
     */
    public function setApiKeyId(?int $apiKeyId): self
    {
        $this->apiKeyId = $apiKeyId;
        return $this;
    }

    /**
     * Send a free-form text message. Only works inside the 24h conversation window.
     */
    public function sendTextMessage(MetaTestPhoneNumber $phone, string $to, string $text): MetaTestMessage
    {
        $connection = $phone->waba->connection;

        if (!$connection) {
            throw new MetaTestApiException('No connected Meta WhatsApp account found.', 404);
        }

        if (!$phone->is_active) {
            throw new MetaTestApiException('Selected phone number is not active.', 400);
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizePhone($to),
            'type' => 'text',
            'text' => [
                'body' => $text,
            ],
        ];

        $result = $this->client->request(
            'POST',
            "/{$phone->meta_phone_number_id}/messages",
            $payload,
            $connection->access_token
        );

        $message = MetaTestMessage::create([
            'user_id' => $phone->user_id,
            'api_key_id' => $this->apiKeyId,
            'phone_number_id' => $phone->id,
            'meta_message_id' => $result['messages'][0]['id'] ?? null,
            'direction' => MetaTestMessage::DIRECTION_OUTGOING,
            'from_phone' => $phone->phone_number,
            'to_phone' => $this->normalizePhone($to),
            'body' => $text,
            'message_type' => 'text',
            'raw_payload' => $result,
            'status' => MetaTestMessage::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $this->mirrorToWhatsAppLog($message, $text);

        return $message;
    }

    /**
     * Send an approved template message. Works for marketing or first message.
     */
    public function sendTemplateMessage(MetaTestPhoneNumber $phone, string $to, MetaTestTemplate $template, array $components = []): MetaTestMessage
    {
        $connection = $phone->waba->connection;

        if (!$connection) {
            throw new MetaTestApiException('No connected Meta WhatsApp account found.', 404);
        }

        if ($template->status !== MetaTestTemplate::STATUS_APPROVED) {
            throw new MetaTestApiException("Template '{$template->name}' is not approved.", 400);
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizePhone($to),
            'type' => 'template',
            'template' => [
                'name' => $template->name,
                'language' => [
                    'code' => $template->language,
                ],
            ],
        ];

        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        $result = $this->client->request(
            'POST',
            "/{$phone->meta_phone_number_id}/messages",
            $payload,
            $connection->access_token
        );

        $message = MetaTestMessage::create([
            'user_id' => $phone->user_id,
            'api_key_id' => $this->apiKeyId,
            'phone_number_id' => $phone->id,
            'meta_message_id' => $result['messages'][0]['id'] ?? null,
            'direction' => MetaTestMessage::DIRECTION_OUTGOING,
            'from_phone' => $phone->phone_number,
            'to_phone' => $this->normalizePhone($to),
            'body' => $template->name,
            'message_type' => 'template',
            'raw_payload' => $result,
            'status' => MetaTestMessage::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $this->mirrorToWhatsAppLog($message, 'template: ' . $template->name);

        return $message;
    }

    /**
     * Upload a media file to Meta and return its media_id.
     * The GraphClient is JSON-only, so media upload uses multipart directly.
     */
    public function uploadMedia(MetaTestPhoneNumber $phone, string $filePath, string $mimeType): string
    {
        $connection = $phone->waba->connection;
        if (!$connection) {
            throw new MetaTestApiException('No connected Meta WhatsApp account found.', 404);
        }

        $url = rtrim(config('meta.graph_url', 'https://graph.facebook.com'), '/')
            . '/' . config('meta.api_version', 'v21.0')
            . "/{$phone->meta_phone_number_id}/media";

        $response = Http::withToken($connection->access_token)
            ->attach('file', file_get_contents($filePath), basename($filePath), ['Content-Type' => $mimeType])
            ->post($url, ['messaging_product' => 'whatsapp']);

        if (!$response->successful() || empty($response->json('id'))) {
            $err = $response->json('error');
            throw new MetaTestApiException(
                $err['error_user_msg'] ?? $err['message'] ?? 'Media upload failed',
                $err['code'] ?? 500,
                is_array($err) ? $err : null
            );
        }

        return $response->json('id');
    }

    /**
     * Send an image / video / audio / document message by uploaded media id.
     * Audio sent with mime audio/ogg renders as a WhatsApp voice note.
     */
    public function sendMediaMessage(MetaTestPhoneNumber $phone, string $to, string $type, string $mediaId, ?string $caption = null, ?string $filename = null, ?string $localPath = null): MetaTestMessage
    {
        $connection = $phone->waba->connection;
        if (!$connection) {
            throw new MetaTestApiException('No connected Meta WhatsApp account found.', 404);
        }

        $mediaBlock = ['id' => $mediaId];
        if ($caption && in_array($type, ['image', 'video', 'document'])) {
            $mediaBlock['caption'] = $caption;
        }
        if ($filename && $type === 'document') {
            $mediaBlock['filename'] = $filename;
        }

        $result = $this->client->request(
            'POST',
            "/{$phone->meta_phone_number_id}/messages",
            [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $this->normalizePhone($to),
                'type' => $type,
                $type => $mediaBlock,
            ],
            $connection->access_token
        );

        $message = MetaTestMessage::create([
            'user_id' => $phone->user_id,
            'api_key_id' => $this->apiKeyId,
            'phone_number_id' => $phone->id,
            'meta_message_id' => $result['messages'][0]['id'] ?? null,
            'direction' => MetaTestMessage::DIRECTION_OUTGOING,
            'from_phone' => $phone->phone_number,
            'to_phone' => $this->normalizePhone($to),
            'body' => $caption,
            'message_type' => $type,
            'raw_payload' => array_merge($result, [$type => $mediaBlock]),
            'status' => MetaTestMessage::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $this->mirrorToWhatsAppLog($message, "[{$type}] " . ($caption ?? $filename ?? $mediaId));

        return $message;
    }

    /**
     * Send a read receipt to Meta so the customer sees blue ticks.
     */
    public function markReadOnMeta(MetaTestPhoneNumber $phone, string $metaMessageId): void
    {
        $connection = $phone->waba->connection;
        if (!$connection) {
            return;
        }

        try {
            $this->client->request(
                'POST',
                "/{$phone->meta_phone_number_id}/messages",
                [
                    'messaging_product' => 'whatsapp',
                    'status' => 'read',
                    'message_id' => $metaMessageId,
                ],
                $connection->access_token
            );
        } catch (\Throwable $e) {
            Log::debug('MetaTest: mark read failed: ' . $e->getMessage());
        }
    }

    /**
     * Resolve an incoming media message to a downloadable Meta URL + mime.
     */
    public function getMediaInfo(MetaTestMessage $message): ?array
    {
        $type = $message->message_type;
        $mediaId = $message->raw_payload[$type]['id'] ?? null;
        if (!$mediaId || !$message->phoneNumber) {
            return null;
        }

        $connection = $message->phoneNumber->waba->connection;
        if (!$connection) {
            return null;
        }

        $info = $this->client->request('GET', "/{$mediaId}", [], $connection->access_token);

        return [
            'url' => $info['url'] ?? null,
            'mime_type' => $info['mime_type'] ?? 'application/octet-stream',
            'token' => $connection->access_token,
            'filename' => $message->raw_payload[$type]['filename'] ?? null,
        ];
    }

    /**
     * Reply to an incoming message. Only works within the 24h window.
     */
    public function replyTo(MetaTestMessage $incomingMessage, string $text): MetaTestMessage
    {
        if (!$this->canReply($incomingMessage)) {
            throw new MetaTestApiException(
                'Cannot reply: 24-hour conversation window has expired. Use an approved template.',
                400
            );
        }

        return $this->sendTextMessage($incomingMessage->phoneNumber, $incomingMessage->from_phone, $text);
    }

    /**
     * Check if a message is inside the 24h conversation window.
     */
    public function canReply(MetaTestMessage $message): bool
    {
        if ($message->direction !== MetaTestMessage::DIRECTION_INCOMING) {
            return false;
        }

        $windowStart = $message->created_at ?? now();

        return $windowStart->diffInHours(now()) < 24;
    }

    /**
     * Store an incoming message from a webhook payload.
     */
    public function storeIncomingMessage(int $userId, array $messageData, string $wabaId): ?MetaTestMessage
    {
        $from = $messageData['from'] ?? null;
        $phoneNumberId = $messageData['phone_number_id'] ?? ($messageData['metadata']['phone_number_id'] ?? null);

        if (empty($from) || empty($phoneNumberId)) {
            return null;
        }

        $phone = MetaTestPhoneNumber::where('meta_phone_number_id', $phoneNumberId)
            ->where('user_id', $userId)
            ->first();

        if (!$phone) {
            // Try to find by WABA and number display
            $waba = MetaTestWaba::where('meta_waba_id', $wabaId)->where('user_id', $userId)->first();
            if ($waba) {
                $phone = MetaTestPhoneNumber::firstOrCreate(
                    [
                        'user_id' => $userId,
                        'meta_phone_number_id' => $phoneNumberId,
                    ],
                    [
                        'waba_id' => $waba->id,
                        'phone_number' => $messageData['metadata']['display_phone_number'] ?? $phoneNumberId,
                        'display_name' => $messageData['metadata']['display_phone_number'] ?? null,
                        'status' => 'CONNECTED',
                        'is_active' => true,
                    ]
                );
            }
        }

        if (!$phone) {
            Log::warning('MetaTestMessagingService: incoming message for unknown phone', [
                'phone_number_id' => $phoneNumberId,
                'waba_id' => $wabaId,
            ]);

            return null;
        }

        // Meta retries webhook delivery; skip messages we already stored.
        $metaMessageId = $messageData['id'] ?? null;
        if ($metaMessageId) {
            $existing = MetaTestMessage::where('meta_message_id', $metaMessageId)
                ->where('direction', MetaTestMessage::DIRECTION_INCOMING)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $body = '';
        $type = $messageData['type'] ?? 'unknown';

        if ($type === 'text') {
            $body = $messageData['text']['body'] ?? '';
        } elseif (isset($messageData[$type]['caption'])) {
            $body = $messageData[$type]['caption'];
        } elseif ($type === 'button') {
            $body = $messageData['button']['text'] ?? '';
        }

        $message = MetaTestMessage::create([
            'user_id' => $phone->user_id,
            'phone_number_id' => $phone->id,
            'meta_message_id' => $metaMessageId,
            'direction' => MetaTestMessage::DIRECTION_INCOMING,
            'from_phone' => $this->normalizePhone($from),
            'to_phone' => $phone->phone_number,
            'body' => $body,
            'message_type' => $type,
            'raw_payload' => $messageData,
            'status' => MetaTestMessage::STATUS_RECEIVED,
        ]);

        // Auto-register the sender's WhatsApp profile name as a contact.
        $profileName = $messageData['_profile_name'] ?? null;
        if ($profileName) {
            try {
                \App\Models\Contact::firstOrCreate(
                    ['user_id' => $phone->user_id, 'phone' => $this->normalizePhone($from)],
                    ['name' => $profileName, 'source' => 'whatsapp', 'channel' => 'meta']
                );
            } catch (\Throwable $e) {
                Log::debug('MetaTest: contact upsert failed: ' . $e->getMessage());
            }
        }

        $this->mirrorToWhatsAppLog($message, $body);

        return $message;
    }

    /**
     * Update message status based on webhook events (sent, delivered, read, failed).
     */
    public function updateMessageStatus(string $metaMessageId, string $status, ?array $error = null, ?array $meta = null): ?MetaTestMessage
    {
        $message = MetaTestMessage::where('meta_message_id', $metaMessageId)->first();

        if (!$message) {
            return null;
        }

        $updates = ['status' => $status];

        // Capture Meta billing info sent with status webhooks.
        if (!empty($meta['conversation']['id']) && empty($message->conversation_id)) {
            $updates['conversation_id'] = $meta['conversation']['id'];
        }
        if (!empty($meta['pricing'])) {
            $updates['pricing_category'] = $meta['pricing']['category'] ?? null;
            $updates['pricing_type'] = $meta['pricing']['type'] ?? null;
            $updates['pricing_model'] = $meta['pricing']['pricing_model'] ?? null;
            $updates['pricing_billable'] = $meta['pricing']['billable'] ?? null;
        }

        switch ($status) {
            case MetaTestMessage::STATUS_SENT:
                $updates['sent_at'] = now();
                break;
            case MetaTestMessage::STATUS_DELIVERED:
                $updates['delivered_at'] = now();
                break;
            case MetaTestMessage::STATUS_READ:
                $updates['read_at'] = now();
                break;
            case MetaTestMessage::STATUS_FAILED:
                $updates['failed_at'] = now();
                $updates['error_message'] = $error['message'] ?? json_encode($error);
                break;
        }

        $message->update($updates);

        // Keep the mirrored whatsapp_messages row in sync so the unified
        // /messages and admin logs show live Meta delivery states.
        if ($message->meta_message_id) {
            $mirror = WhatsAppMessage::where('message_id', $message->meta_message_id)->first();
            if ($mirror) {
                $mirror->update(array_intersect_key($updates, array_flip([
                    'status', 'sent_at', 'delivered_at', 'read_at', 'failed_at', 'error_message',
                ])));
            }
        }

        return $message;
    }

    /**
     * Write a copy of the Meta message into whatsapp_messages so the shared
     * Messages page and admin logs include official-API traffic. Meta rows
     * carry no session_id; their from/to numbers identify the sender.
     */
    protected function mirrorToWhatsAppLog(MetaTestMessage $message, string $text): void
    {
        try {
            $incoming = $message->direction === MetaTestMessage::DIRECTION_INCOMING;

            WhatsAppMessage::create([
                'user_id' => $message->user_id,
                'api_key_id' => $message->api_key_id,
                'session_id' => null,
                'from' => $message->from_phone,
                'to' => $message->to_phone,
                'message' => $text,
                'message_type' => $message->message_type,
                'message_id' => $message->meta_message_id,
                'direction' => $incoming ? 'incoming' : 'outgoing',
                'status' => $incoming ? WhatsAppMessage::STATUS_DELIVERED : WhatsAppMessage::STATUS_SENT,
                'sent_at' => $incoming ? null : now(),
                'timestamp' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('MetaTestMessagingService: whatsapp_messages mirror failed: ' . $e->getMessage());
        }
    }

    public function getInbox(int $userId, ?int $phoneNumberId = null, int $limit = 50)
    {
        $query = MetaTestMessage::where('user_id', $userId)
            ->with('phoneNumber')
            ->orderByDesc('created_at');

        if ($phoneNumberId) {
            $query->where('phone_number_id', $phoneNumberId);
        }

        return $query->paginate($limit);
    }

    public function getConversation(int $userId, string $phone, ?int $phoneNumberId = null)
    {
        $normalized = $this->normalizePhone($phone);

        $query = MetaTestMessage::where('user_id', $userId)
            ->where(function ($q) use ($normalized) {
                $q->where('from_phone', $normalized)
                  ->orWhere('to_phone', $normalized);
            })
            ->with('phoneNumber')
            ->orderBy('created_at');

        if ($phoneNumberId) {
            $query->where('phone_number_id', $phoneNumberId);
        }

        return $query->get();
    }

    protected function normalizePhone(string $phone): string
    {
        return PhoneNormalizer::digits($phone);
    }
}
