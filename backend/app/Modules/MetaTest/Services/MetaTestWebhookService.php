<?php

namespace App\Modules\MetaTest\Services;

use App\Modules\MetaTest\Models\MetaTestCampaignRecipient;
use App\Modules\MetaTest\Models\MetaTestEvent;
use App\Modules\MetaTest\Models\MetaTestMessage;
use App\Modules\MetaTest\Models\MetaTestTemplate;
use App\Modules\MetaTest\Models\MetaTestWaba;
use Illuminate\Support\Facades\Log;

/**
 * Handles Meta webhook verification and event parsing for the Meta Test module.
 */
class MetaTestWebhookService
{
    protected ?string $appSecret;
    protected ?string $verifyToken;
    protected MetaTestMessagingService $messaging;

    public function __construct()
    {
        $this->appSecret = config('meta.app_secret');
        $this->verifyToken = config('meta.webhook_verify_token');
        $this->messaging = new MetaTestMessagingService();
    }

    /**
     * Verify the webhook subscription challenge from Meta.
     */
    public function verifyChallenge(string $mode, string $token, string $challenge): ?string
    {
        if (strtolower($mode) !== 'subscribe') {
            return null;
        }

        if (empty($this->verifyToken) || $token !== $this->verifyToken) {
            return null;
        }

        return $challenge;
    }

    /**
     * Verify the X-Hub-Signature-256 header sent by Meta.
     */
    public function verifySignature(string $payload, ?string $signatureHeader): bool
    {
        if (empty($this->appSecret)) {
            Log::warning('MetaTestWebhookService: app secret missing, signature verification skipped');
            return false;
        }

        if (empty($signatureHeader) || !str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $payload, $this->appSecret);

        return hash_equals($expected, $signatureHeader);
    }

    /**
     * Receive, validate, parse, and store a webhook payload.
     */
    public function handlePayload(string $payload, ?string $signatureHeader, ?int $userId = null): array
    {
        $signatureValid = $this->verifySignature($payload, $signatureHeader);

        $data = json_decode($payload, true);

        if (!is_array($data)) {
            throw new \InvalidArgumentException('Invalid JSON payload');
        }

        $events = [];

        foreach ($data['entry'] ?? [] as $entry) {
            $wabaId = $entry['id'] ?? null;

            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $field = $change['field'] ?? null;

                $eventType = $this->determineEventType($value, $field);
                $messageId = $this->extractMessageId($value);

                $event = MetaTestEvent::create([
                    'user_id' => $userId,
                    'event_type' => $eventType,
                    'payload' => $data,
                    'phone_number' => $this->extractPhone($value),
                    'meta_message_id' => $messageId,
                    'waba_id' => $wabaId,
                    'status' => MetaTestEvent::STATUS_RECEIVED,
                    'meta_signature' => $signatureHeader,
                ]);

                // Meta retries deliveries; record the event but skip re-processing.
                if ($messageId && MetaTestEvent::where('meta_message_id', $messageId)
                    ->where('event_type', $eventType)
                    ->where('status', MetaTestEvent::STATUS_PROCESSED)
                    ->where('id', '!=', $event->id)
                    ->exists()) {
                    $event->update([
                        'status' => MetaTestEvent::STATUS_DUPLICATE,
                        'processed_at' => now(),
                    ]);

                    $events[] = $event;
                    continue;
                }

                $this->processEvent($event, $wabaId, $value, $signatureValid);

                $events[] = $event;
            }
        }

        return [
            'events' => $events,
            'signature_valid' => $signatureValid,
        ];
    }

    protected function determineEventType(array $value, ?string $field): string
    {
        if (!empty($value['messages'])) {
            return 'messages';
        }

        if (!empty($value['statuses'])) {
            return 'message_status';
        }

        if (!empty($value['message_template_status_update'])) {
            return 'template_status';
        }

        if (!empty($value['waba_status_update'])) {
            return 'waba_status';
        }

        if (!empty($value['phone_number_status_update'])) {
            return 'phone_number_status';
        }

        return $field ?? 'unknown';
    }

    protected function extractPhone(array $value): ?string
    {
        if (!empty($value['messages'][0]['from'])) {
            return $value['messages'][0]['from'];
        }

        if (!empty($value['statuses'][0]['recipient_id'])) {
            return $value['statuses'][0]['recipient_id'];
        }

        return null;
    }

    protected function extractMessageId(array $value): ?string
    {
        if (!empty($value['messages'][0]['id'])) {
            return $value['messages'][0]['id'];
        }

        if (!empty($value['statuses'][0]['id'])) {
            return $value['statuses'][0]['id'];
        }

        return null;
    }

    protected function processEvent(MetaTestEvent $event, ?string $wabaId, array $value, bool $signatureValid): void
    {
        if (!$signatureValid) {
            $event->update([
                'status' => MetaTestEvent::STATUS_FAILED,
                'error_message' => 'Webhook signature verification failed',
                'processed_at' => now(),
            ]);

            return;
        }

        try {
            $metadata = $value['metadata'] ?? [];
            $phoneNumberId = $metadata['phone_number_id'] ?? ($value['phone_number_id'] ?? null);

            $waba = null;
            $userId = null;

            if ($wabaId) {
                $waba = MetaTestWaba::where('meta_waba_id', $wabaId)->first();
                if ($waba) {
                    $userId = $waba->user_id;
                    $event->update(['user_id' => $userId, 'waba_id' => $wabaId]);
                }
            }

            if (!empty($value['messages'])) {
                $profileName = $value['contacts'][0]['profile']['name'] ?? null;
                foreach ($value['messages'] as $message) {
                    $message['phone_number_id'] = $phoneNumberId;
                    $message['metadata'] = $metadata;
                    if ($profileName) {
                        $message['_profile_name'] = $profileName;
                    }
                    $this->messaging->storeIncomingMessage($userId ?? 0, $message, $wabaId ?? '');
                }
            }

            if (!empty($value['message_template_status_update'])) {
                $this->updateTemplateStatus($value['message_template_status_update']);
            }

            if (!empty($value['statuses'])) {
                foreach ($value['statuses'] as $status) {
                    $statusValue = match ($status['status'] ?? '') {
                        'sent' => MetaTestMessage::STATUS_SENT,
                        'delivered' => MetaTestMessage::STATUS_DELIVERED,
                        'read' => MetaTestMessage::STATUS_READ,
                        'failed' => MetaTestMessage::STATUS_FAILED,
                        default => $status['status'] ?? 'unknown',
                    };

                    $this->messaging->updateMessageStatus(
                        $status['id'] ?? '',
                        $statusValue,
                        $status['errors'] ?? null,
                        $status
                    );

                    $this->updateCampaignRecipientStatus($status, $statusValue);
                }
            }

            $event->update([
                'status' => MetaTestEvent::STATUS_PROCESSED,
                'processed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $event->update([
                'status' => MetaTestEvent::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'processed_at' => now(),
            ]);

            Log::error('MetaTestWebhookService: event processing failed', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Keep local template status in sync when Meta approves/rejects/pauses
     * a template (message_template_status_update webhook event).
     */
    protected function updateTemplateStatus(array $update): void
    {
        $templateId = $update['message_template_id'] ?? null;
        $event = $update['event'] ?? null;

        if (empty($templateId) || empty($event)) {
            return;
        }

        $status = match (strtoupper($event)) {
            'APPROVED' => 'APPROVED',
            'REJECTED' => 'REJECTED',
            'PAUSED', 'DISABLED' => 'PAUSED',
            'PENDING' => 'PENDING',
            default => null,
        };

        if ($status === null) {
            return;
        }

        MetaTestTemplate::where('meta_template_id', (string) $templateId)
            ->update(['status' => $status]);
    }

    /**
     * Propagate delivery status to campaign recipients so bulk sends track
     * sent/delivered/read/failed in real time.
     */
    protected function updateCampaignRecipientStatus(array $status, string $statusValue): void
    {
        $metaMessageId = $status['id'] ?? null;
        if (empty($metaMessageId)) {
            return;
        }

        $recipient = MetaTestCampaignRecipient::where('meta_message_id', $metaMessageId)->first();
        if (!$recipient) {
            return;
        }

        $now = now();
        $update = match ($statusValue) {
            MetaTestMessage::STATUS_SENT => [
                'status' => MetaTestCampaignRecipient::STATUS_SENT,
                'sent_at' => $recipient->sent_at ?? $now,
            ],
            MetaTestMessage::STATUS_DELIVERED => [
                'status' => MetaTestCampaignRecipient::STATUS_DELIVERED,
                'delivered_at' => $now,
            ],
            MetaTestMessage::STATUS_READ => [
                'status' => MetaTestCampaignRecipient::STATUS_READ,
                'read_at' => $now,
            ],
            MetaTestMessage::STATUS_FAILED => [
                'status' => MetaTestCampaignRecipient::STATUS_FAILED,
                'failed_at' => $now,
                'error_message' => $status['errors'][0]['message'] ?? 'Delivery failed',
                'error_code' => isset($status['errors'][0]['code']) ? (string) $status['errors'][0]['code'] : null,
            ],
            default => [],
        };

        if (!empty($update)) {
            $recipient->update($update);
            $recipient->campaign?->recomputeStats();
        }
    }
}
