<?php

namespace App\Modules\Messenger\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\SendMessengerMessageJob;
use App\Models\Contact;
use App\Models\MessengerAutoReply;
use App\Models\MessengerAutoReplyLog;
use App\Models\MessengerContact;
use App\Models\MessengerConversation;
use App\Models\MessengerMessage;
use App\Models\MessengerPage;
use App\Models\WhatsAppAutoReply;
use App\Services\AIReplyService;
use App\Services\FraudDetectionService;
use App\Services\MessengerService;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessengerWebhookController extends Controller
{
    protected MessengerService $messenger;
    protected AIReplyService $aiReply;

    public function __construct(MessengerService $messenger, AIReplyService $aiReply)
    {
        $this->messenger = $messenger;
        $this->aiReply = $aiReply;
    }

    /**
     * Meta webhook verification challenge.
     */
    public function verify(Request $request)
    {
        // Meta sends dot-separated query keys; PHP converts them to underscores in $_GET.
        $mode = $request->input('hub_mode') ?? $request->input('hub.mode');
        $token = $request->input('hub_verify_token') ?? $request->input('hub.verify_token');
        $challenge = $request->input('hub_challenge') ?? $request->input('hub.challenge');

        $response = $this->messenger->verifyWebhookChallenge($mode ?? '', $token ?? '', $challenge ?? '');

        if (is_null($response)) {
            return response()->json(['success' => false], 403);
        }

        return response($response, 200);
    }

    /**
     * Receive and process Messenger webhook events.
     */
    public function receive(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');

        if (!$this->messenger->verifyWebhookSignature($payload, $signature ?? '')) {
            Log::warning('Messenger webhook signature verification failed');
            FraudDetectionService::report('messenger_signature_invalid', 'critical', 'Messenger webhook signature verification failed (possible spoofing attempt)', [
                'signature_header' => substr($signature ?: '', 0, 32),
            ]);
            return response()->json(['success' => false], 403);
        }

        $data = $request->json()->all();
        $events = $this->messenger->parseWebhookPayload($data);

        foreach ($events as $event) {
            try {
                $this->handleEvent($event);
            } catch (\Throwable $e) {
                Log::error('Messenger webhook event error: ' . $e->getMessage());
            }
        }

        return response()->json(['success' => true]);
    }

    protected function handleEvent(array $event): void
    {
        $pageId = $event['page_id'] ?? ($event['recipient']['id'] ?? null);
        if (!$pageId) {
            return;
        }

        $page = MessengerPage::where('page_id', $pageId)
            ->where('status', 'connected')
            ->first();

        if (!$page) {
            Log::info("Messenger webhook for unknown/disconnected page: {$pageId}");
            return;
        }

        // Incoming message
        if (isset($event['message']) && !empty($event['message']['text'])) {
            $this->handleMessage($page, $event);
            return;
        }

        // Delivery/read receipts
        if (isset($event['delivery'])) {
            $this->handleDelivery($page, $event['delivery']);
            return;
        }

        if (isset($event['read'])) {
            $this->handleRead($page, $event['read']);
            return;
        }

        if (isset($event['postback'])) {
            // Could be used for menu interactions in future.
            return;
        }
    }

    protected function handleMessage(MessengerPage $page, array $event): void
    {
        $senderId = $event['sender']['id'] ?? null;
        $text = $event['message']['text'] ?? null;
        $externalMessageId = $event['message']['mid'] ?? null;
        if (!$senderId || !$text) {
            return;
        }

        $user = $page->user;

        DB::transaction(function () use ($page, $user, $senderId, $text, $externalMessageId, $event) {
            // Fetch profile if new contact.
            $contact = MessengerContact::firstOrNew([
                'page_id' => $page->id,
                'psid' => $senderId,
            ], [
                'user_id' => $user->id,
            ]);

            if (!$contact->exists) {
                $profile = $this->messenger->getProfile($page, $senderId);
                $contact->name = $profile['name'] ?? null;
                $contact->profile_pic = $profile['profile_pic'] ?? null;
                $contact->metadata = $profile ?? [];
                $contact->first_contact_at = now();
                $contact->user_id = $user->id;
                $contact->save();
            }

            $contact->update(['last_message_at' => now()]);

            $conversation = MessengerConversation::firstOrNew([
                'page_id' => $page->id,
                'psid' => $senderId,
            ], [
                'user_id' => $user->id,
                'contact_id' => $contact->id,
            ]);

            if (!$conversation->exists) {
                $conversation->user_id = $user->id;
                $conversation->contact_id = $contact->id;
                $conversation->save();
            }

            $conversation->update([
                'last_message_at' => now(),
                // Standard messaging window: 24 hours from now.
                'messaging_window_until' => now()->addHours(24),
            ]);

            $message = MessengerMessage::create([
                'user_id' => $user->id,
                'page_id' => $page->id,
                'conversation_id' => $conversation->id,
                'psid' => $senderId,
                'direction' => 'incoming',
                'message_type' => 'text',
                'message' => $text,
                'external_message_id' => $externalMessageId,
                'status' => 'delivered',
                'delivered_at' => now(),
            ]);

            // Dispatch user-configured webhooks.
            try {
                WebhookDispatcher::dispatch($user->id, 'messenger.message_received', [
                    'page_id' => $page->page_id,
                    'psid' => $senderId,
                    'name' => $contact->name,
                    'message' => $text,
                    'timestamp' => now()->toIso8601String(),
                ]);
            } catch (\Throwable $e) {}

            // Process auto reply / AI.
            $this->processAutoReply($page, $conversation, $contact, $text, $message);
        });
    }

    protected function processAutoReply(MessengerPage $page, MessengerConversation $conversation, MessengerContact $contact, string $text, MessengerMessage $incomingMessage): void
    {
        if ($conversation->isAiDisabled()) {
            return;
        }

        $autoReply = MessengerAutoReply::where('page_id', $page->id)
            ->where('is_enabled', true)
            ->first();

        if (!$autoReply) {
            return;
        }

        $cleanText = trim($text);

        if ($autoReply->mode === MessengerAutoReply::MODE_RULES) {
            $rules = $autoReply->rules ?? [];
            foreach ($rules as $rule) {
                $keywords = $rule['keywords'] ?? [];
                $replyContent = $rule['reply'] ?? '';
                $matchType = $rule['match_type'] ?? 'contains';

                if (empty($replyContent) || empty($keywords)) {
                    continue;
                }

                $matched = false;
                foreach ($keywords as $kw) {
                    $kw = trim($kw);
                    if (empty($kw)) continue;

                    if ($matchType === 'exact' && mb_strtolower($cleanText) === mb_strtolower($kw)) {
                        $matched = true;
                        break;
                    } elseif ($matchType === 'starts_with' && str_starts_with(mb_strtolower($cleanText), mb_strtolower($kw))) {
                        $matched = true;
                        break;
                    } elseif ($matchType === 'contains' && str_contains(mb_strtolower($cleanText), mb_strtolower($kw))) {
                        $matched = true;
                        break;
                    }
                }

                if ($matched) {
                    $this->sendAutoReply($page, $conversation, $contact, $cleanText, $replyContent, 'rules', null, 0);
                    return;
                }
            }

            if (!empty($autoReply->ai_fallback_message)) {
                $this->sendAutoReply($page, $conversation, $contact, $cleanText, $autoReply->ai_fallback_message, 'rules_fallback', null, 0);
            }
        } elseif ($autoReply->mode === MessengerAutoReply::MODE_AI) {
            try {
                $sampleAutoReply = new WhatsAppAutoReply([
                    'user_id' => $page->user_id,
                    'session_id' => null,
                    'ai_system_prompt' => $autoReply->ai_system_prompt,
                    'ai_business_info' => $autoReply->ai_business_info,
                    'ai_tone' => $autoReply->ai_tone ?? 'friendly',
                    'ai_language' => $autoReply->ai_language ?? 'ar',
                    'ai_model' => $autoReply->ai_model,
                ]);

                $contextHistory = MessengerMessage::where('conversation_id', $conversation->id)
                    ->orderByDesc('created_at')
                    ->limit(6)
                    ->get()
                    ->reverse()
                    ->map(fn($m) => [
                        'direction' => $m->direction,
                        'message' => $m->message,
                    ])
                    ->toArray();

                $aiResult = $this->aiReply->test($sampleAutoReply, $cleanText, $contextHistory);
                $replyText = $aiResult['reply'] ?? '';
                $tokens = ($aiResult['tokens_input'] ?? 0) + ($aiResult['tokens_output'] ?? 0);
                $model = $aiResult['model'] ?? 'gemini';

                if (!empty($replyText)) {
                    $this->sendAutoReply($page, $conversation, $contact, $cleanText, $replyText, 'ai', $model, $tokens);
                }
            } catch (\Throwable $e) {
                Log::error('Messenger AI reply failed: ' . $e->getMessage());
            }
        }
    }

    protected function sendAutoReply(MessengerPage $page, MessengerConversation $conversation, MessengerContact $contact, string $incomingText, string $replyText, string $mode, ?string $model, int $tokens): void
    {
        try {
            $result = $this->messenger->sendMessage($page, $conversation->psid, $replyText);

            $message = MessengerMessage::create([
                'user_id' => $page->user_id,
                'page_id' => $page->id,
                'conversation_id' => $conversation->id,
                'psid' => $conversation->psid,
                'direction' => 'outgoing',
                'message_type' => 'text',
                'message' => $replyText,
                'status' => MessengerMessage::STATUS_SENT,
                'external_message_id' => $result['message_id'] ?? null,
                'sent_at' => now(),
                'cost' => 0, // Auto replies do not consume credits.
            ]);

            $page->increment('messages_sent_count');

            MessengerAutoReplyLog::create([
                'messenger_auto_reply_id' => $page->autoReply()->first()?->id,
                'user_id' => $page->user_id,
                'page_id' => $page->id,
                'conversation_id' => $conversation->id,
                'psid' => $conversation->psid,
                'incoming_text' => $incomingText,
                'reply_text' => $replyText,
                'mode' => $mode,
                'model' => $model,
                'tokens_used' => $tokens,
                'status' => 'sent',
            ]);

            $autoReply = MessengerAutoReply::where('page_id', $page->id)->first();
            if ($autoReply) {
                $autoReply->increment('total_replies_sent');
                $autoReply->update(['last_reply_at' => now()]);
            }
        } catch (\Throwable $e) {
            Log::error('Messenger auto reply send failed: ' . $e->getMessage());
        }
    }

    protected function handleDelivery(MessengerPage $page, array $delivery): void
    {
        $mids = $delivery['mids'] ?? [];
        foreach ($mids as $mid) {
            MessengerMessage::where('page_id', $page->id)
                ->where('external_message_id', $mid)
                ->where('direction', 'outgoing')
                ->update(['status' => MessengerMessage::STATUS_DELIVERED, 'delivered_at' => now()]);
        }
    }

    protected function handleRead(MessengerPage $page, array $read): void
    {
        // Optional: mark messages as read based on watermark timestamp.
    }
}
