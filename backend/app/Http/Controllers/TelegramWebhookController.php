<?php

namespace App\Http\Controllers;

use App\Models\TelegramAutoReply;
use App\Models\TelegramAutoReplyLog;
use App\Models\TelegramMessage;
use App\Models\TelegramSession;
use App\Services\AIReplyService;
use App\Services\SubscriptionService;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramWebhookController extends Controller
{
    protected AIReplyService $aiReplyService;
    protected SubscriptionService $subscriptionService;

    public function __construct(AIReplyService $aiReplyService, SubscriptionService $subscriptionService)
    {
        $this->aiReplyService = $aiReplyService;
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Handlers for telegram-service events
     */

    public function connected(Request $request)
    {
        $userId = $request->input('userId');
        $phone = $request->input('phoneNumber');
        $firstName = $request->input('firstName');
        $username = $request->input('username');
        $tgId = $request->input('id');

        if ($userId) {
            TelegramSession::updateOrCreate(
                ['user_id' => $userId],
                [
                    'phone_number' => $phone,
                    'first_name' => $firstName,
                    'bot_username' => $username,
                    'bot_id' => (string)$tgId,
                    'session_token' => Str::random(40),
                    'status' => 'connected',
                    'connected_at' => now(),
                    'last_activity_at' => now(),
                ]
            );
        }

        return response()->json(['success' => true]);
    }

    public function disconnected(Request $request)
    {
        $userId = $request->input('userId');
        if ($userId) {
            TelegramSession::where('user_id', $userId)->update([
                'status' => 'disconnected',
                'connected_at' => null,
                'last_activity_at' => now(),
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function qrGenerated(Request $request)
    {
        return response()->json(['success' => true]);
    }

    public function messageReceived(Request $request)
    {
        $userId = $request->input('userId');
        $chatId = (string) $request->input('chatId');
        $senderName = $request->input('senderName');
        $senderPhone = $request->input('senderPhone');
        $senderUsername = $request->input('senderUsername');
        $text = $request->input('message');
        $messageId = $request->input('messageId');

        $session = TelegramSession::where('user_id', $userId)->first();
        if (!$session) {
            return response()->json(['success' => true]);
        }

        // Record incoming message
        TelegramMessage::create([
            'user_id' => $session->user_id,
            'telegram_session_id' => $session->id,
            'chat_id' => $chatId,
            'telegram_message_id' => $messageId,
            'direction' => 'incoming',
            'type' => 'text',
            'message' => $text,
            'status' => 'delivered',
            'metadata' => [
                'sender_name' => $senderName,
                'sender_phone' => $senderPhone,
                'sender_username' => $senderUsername,
            ],
            'sent_at' => now(),
        ]);

        $session->update(['last_activity_at' => now()]);

        // Dispatch User Webhooks
        try {
            WebhookDispatcher::dispatch($session->user_id, 'telegram.message_received', [
                'phone_number' => $session->phone_number,
                'chat_id' => $chatId,
                'sender_name' => $senderName,
                'sender_phone' => $senderPhone,
                'sender_username' => $senderUsername,
                'message' => $text,
                'message_id' => $messageId,
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {}

        // Handle Auto Reply / AI
        $this->processAutoReply($session, $chatId, $senderName, $text);

        return response()->json(['success' => true]);
    }

    protected function processAutoReply(TelegramSession $session, string $chatId, ?string $senderName, ?string $text)
    {
        if (empty($text)) return;

        $autoReply = TelegramAutoReply::where('telegram_session_id', $session->id)
            ->where('is_enabled', true)
            ->first();

        if (!$autoReply) {
            $autoReply = TelegramAutoReply::where('user_id', $session->user_id)
                ->where('is_enabled', true)
                ->first();
        }

        if (!$autoReply) return;

        $cleanText = trim($text);

        if ($autoReply->mode === 'rules') {
            $rules = $autoReply->rules ?? [];
            foreach ($rules as $rule) {
                $keywords = $rule['keywords'] ?? [];
                $replyContent = $rule['reply'] ?? '';
                $matchType = $rule['match_type'] ?? 'contains';

                if (empty($replyContent) || empty($keywords)) continue;

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
                    $this->sendReply($session, $autoReply, $chatId, $senderName, $cleanText, $replyContent, 'rules', null, 0);
                    return;
                }
            }

            if (!empty($autoReply->ai_fallback_message)) {
                $this->sendReply($session, $autoReply, $chatId, $senderName, $cleanText, $autoReply->ai_fallback_message, 'rules_fallback', null, 0);
            }
        } elseif ($autoReply->mode === 'ai') {
            try {
                $sampleAutoReply = new \App\Models\WhatsAppAutoReply([
                    'user_id' => $session->user_id,
                    'ai_system_prompt' => $autoReply->ai_system_prompt,
                    'ai_business_info' => $autoReply->ai_business_info,
                    'ai_tone' => $autoReply->ai_tone ?? 'friendly',
                    'ai_language' => $autoReply->ai_language ?? 'ar',
                    'ai_model' => $autoReply->ai_model,
                ]);

                $contextHistory = TelegramMessage::where('user_id', $session->user_id)
                    ->where('chat_id', $chatId)
                    ->orderBy('created_at', 'desc')
                    ->limit(6)
                    ->get()
                    ->reverse()
                    ->map(fn($m) => [
                        'direction' => $m->direction,
                        'message' => $m->message,
                    ])
                    ->toArray();

                $aiResult = $this->aiReplyService->test($sampleAutoReply, $cleanText, $contextHistory);
                $replyText = $aiResult['reply'] ?? '';
                $tokens = ($aiResult['tokens_input'] ?? 0) + ($aiResult['tokens_output'] ?? 0);
                $model = $aiResult['model'] ?? 'gemini';

                if (!empty($replyText)) {
                    $this->sendReply($session, $autoReply, $chatId, $senderName, $cleanText, $replyText, 'ai', $model, $tokens);
                }
            } catch (\Throwable $e) {
                Log::error('Telegram AI error: ' . $e->getMessage());
            }
        }
    }

    protected function sendReply(TelegramSession $session, TelegramAutoReply $autoReply, string $chatId, ?string $senderName, string $incomingText, string $replyText, string $mode, ?string $model, int $tokens)
    {
        $serviceUrl = rtrim(config('services.telegram.url', env('TELEGRAM_SERVICE_URL', 'http://127.0.0.1:3003')), '/') . '/api/telegram';

        try {
            $res = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.telegram.api_key', 'wavixa-telegram-service-secret-key-2026'),
            ])->post("{$serviceUrl}/send-message", [
                'userId' => (string) $session->user_id,
                'to' => $chatId,
                'message' => $replyText,
                'type' => 'text',
            ]);

            if ($res->successful()) {
                TelegramMessage::create([
                    'user_id' => $session->user_id,
                    'telegram_session_id' => $session->id,
                    'chat_id' => $chatId,
                    'direction' => 'outgoing',
                    'type' => 'text',
                    'message' => $replyText,
                    'status' => 'sent',
                    'sent_at' => now(),
                    'cost' => 1,
                ]);

                TelegramAutoReplyLog::create([
                    'telegram_auto_reply_id' => $autoReply->id,
                    'user_id' => $session->user_id,
                    'telegram_session_id' => $session->id,
                    'chat_id' => $chatId,
                    'sender_name' => $senderName,
                    'incoming_text' => $incomingText,
                    'reply_text' => $replyText,
                    'mode' => $mode,
                    'model' => $model,
                    'tokens_used' => $tokens,
                    'status' => 'sent',
                ]);

                $autoReply->increment('total_replies_sent');
                $autoReply->update(['last_reply_at' => now()]);
                $session->increment('messages_sent_count');
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send auto reply via Telegram: ' . $e->getMessage());
        }
    }
}
