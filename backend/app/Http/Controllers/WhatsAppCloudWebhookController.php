<?php

namespace App\Http\Controllers;

use App\Models\MetaWhatsAppCampaignRecipient;
use App\Models\MetaWhatsAppConnection;
use App\Models\MetaWhatsAppPhoneNumber;
use App\Models\MetaWhatsAppWebhookLog;
use App\Services\FraudDetectionService;
use App\Services\MetaWhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class WhatsAppCloudWebhookController extends Controller
{
    protected MetaWhatsAppService $service;

    public function __construct(MetaWhatsAppService $service)
    {
        $this->service = $service;
    }

    /**
     * Handle the GET challenge verification from Meta.
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub.mode');
        $token = $request->query('hub.verify_token');
        $challenge = $request->query('hub.challenge');

        $result = $this->service->verifyWebhookChallenge($mode, $token, $challenge);

        if ($result === null) {
            Log::warning('Meta WhatsApp webhook verification failed', [
                'mode' => $mode,
                'token' => $token,
            ]);
            return response('Verification failed', 403);
        }

        return response($result);
    }

    /**
     * Receive incoming webhook events from Meta.
     */
    public function receive(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');

        if (!$this->service->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Meta WhatsApp webhook signature invalid');
            FraudDetectionService::report('meta_whatsapp_signature_invalid', 'critical', 'Meta WhatsApp Cloud webhook signature verification failed (possible spoofing attempt)', [
                'signature_header' => substr($signature ?: '', 0, 32),
            ]);
            return response()->json(['success' => false], 403);
        }

        $data = $request->all();

        $log = MetaWhatsAppWebhookLog::create([
            'event_type' => $this->guessEventType($data),
            'payload' => $data,
            'meta_signature' => $signature,
        ]);

        try {
            $events = $this->service->parseWebhookPayload($data);

            foreach ($events as $event) {
                $value = $event['value'] ?? [];

                if (!empty($value['statuses'])) {
                    foreach ($value['statuses'] as $status) {
                        $this->handleStatusUpdate($status);
                    }
                }

                if (!empty($value['messages'])) {
                    $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;
                    foreach ($value['messages'] as $message) {
                        $this->handleIncomingMessage($value, $message, $phoneNumberId);
                    }
                }
            }

            $log->update(['status' => 'processed']);
        } catch (Exception $e) {
            Log::error('Meta WhatsApp webhook processing error: ' . $e->getMessage());
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
        }

        return response()->json(['success' => true]);
    }

    protected function handleStatusUpdate(array $status): void
    {
        $messageId = $status['id'] ?? null;
        $statusValue = $status['status'] ?? 'sent';

        if (!$messageId) {
            return;
        }

        $recipient = MetaWhatsAppCampaignRecipient::where('meta_message_id', $messageId)->first();
        if (!$recipient) {
            return;
        }

        $update = [];
        $now = now();

        switch (strtolower($statusValue)) {
            case 'sent':
                $update = ['status' => MetaWhatsAppCampaignRecipient::STATUS_SENT, 'sent_at' => $now];
                break;
            case 'delivered':
                $update = ['status' => MetaWhatsAppCampaignRecipient::STATUS_DELIVERED, 'delivered_at' => $now];
                break;
            case 'read':
                $update = ['status' => MetaWhatsAppCampaignRecipient::STATUS_READ, 'read_at' => $now];
                break;
            case 'failed':
                $update = [
                    'status' => MetaWhatsAppCampaignRecipient::STATUS_FAILED,
                    'failed_at' => $now,
                    'error_message' => $status['errors'][0]['message'] ?? 'Delivery failed',
                    'error_code' => $status['errors'][0]['code'] ?? null,
                ];
                break;
        }

        if (!empty($update)) {
            $recipient->update($update);
            $recipient->campaign->recomputeStats();
        }
    }

    protected function handleIncomingMessage(array $value, array $message, ?string $phoneNumberId): void
    {
        $phone = MetaWhatsAppPhoneNumber::where('meta_phone_number_id', $phoneNumberId)->first();
        if (!$phone) {
            return;
        }

        $from = $message['from'] ?? null;
        $body = $message['text']['body'] ?? null;
        $messageId = $message['id'] ?? null;
        $timestamp = $message['timestamp'] ?? null;

        if (!$from || $body === null) {
            return;
        }

        // Store a basic webhook log instead of full chat integration for now.
        MetaWhatsAppWebhookLog::create([
            'user_id' => $phone->user_id,
            'event_type' => 'incoming_message',
            'payload' => [
                'phone_number_id' => $phoneNumberId,
                'from' => $from,
                'body' => $body,
                'message_id' => $messageId,
                'timestamp' => $timestamp,
            ],
            'status' => 'processed',
        ]);

        // TODO: Integrate with WhatsAppConversation + WhatsAppMessage + Auto Reply
    }

    protected function guessEventType(array $data): string
    {
        $events = $this->service->parseWebhookPayload($data);
        foreach ($events as $event) {
            $value = $event['value'] ?? [];
            if (!empty($value['messages'])) {
                return 'messages';
            }
            if (!empty($value['statuses'])) {
                return 'message_status';
            }
        }
        return 'unknown';
    }
}
