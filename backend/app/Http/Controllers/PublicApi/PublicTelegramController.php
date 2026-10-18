<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Models\TelegramMessage;
use App\Models\TelegramSession;
use App\Services\SubscriptionService;
use App\Services\TelegramService;
use Illuminate\Http\Request;

class PublicTelegramController extends Controller
{
    protected TelegramService $telegramService;
    protected SubscriptionService $subscriptionService;

    public function __construct(TelegramService $telegramService, SubscriptionService $subscriptionService)
    {
        $this->telegramService = $telegramService;
        $this->subscriptionService = $subscriptionService;
    }

    public function sessions(Request $request)
    {
        $user = $request->attributes->get('api_key_user');
        $sessions = TelegramSession::where('user_id', $user->id)
            ->select('id', 'bot_username', 'first_name', 'device_name', 'status', 'connected_at', 'messages_sent_count')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    public function sendMessage(Request $request)
    {
        $user = $request->attributes->get('api_key_user');

        $data = $request->validate([
            'session_id' => 'nullable|integer',
            'to' => 'required|string',
            'message' => 'nullable|string',
            'type' => 'nullable|string|in:text,photo,document',
            'media_url' => 'nullable|string',
            'button_text' => 'nullable|string',
            'button_url' => 'nullable|url',
            'parse_mode' => 'nullable|string|in:HTML,Markdown,MarkdownV2',
        ]);

        $session = null;
        if (!empty($data['session_id'])) {
            $session = TelegramSession::where('id', $data['session_id'])->where('user_id', $user->id)->first();
        } else {
            $session = TelegramSession::where('user_id', $user->id)->where('status', 'connected')->latest()->first();
        }

        if (!$session || $session->status !== 'connected') {
            return response()->json([
                'success' => false,
                'message' => 'No active Telegram bot session found.',
            ], 422);
        }

        if ($this->subscriptionService->isOutOfCredits($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Out of message credits.',
            ], 402);
        }

        $type = $data['type'] ?? 'text';
        $chatId = trim($data['to']);
        $text = $data['message'] ?? '';
        $mediaUrl = $data['media_url'] ?? null;
        $parseMode = $data['parse_mode'] ?? 'HTML';

        $options = [
            'parse_mode' => $parseMode,
            'button_text' => $data['button_text'] ?? null,
            'button_url' => $data['button_url'] ?? null,
        ];

        if ($type === 'photo' && $mediaUrl) {
            $result = $this->telegramService->sendPhoto($session->bot_token, $chatId, $mediaUrl, $text, $options);
        } elseif ($type === 'document' && $mediaUrl) {
            $result = $this->telegramService->sendDocument($session->bot_token, $chatId, $mediaUrl, $text, $options);
        } else {
            $result = $this->telegramService->sendMessage($session->bot_token, $chatId, $text, $options);
        }

        if ($result['success']) {
            $msgId = $result['result']['message_id'] ?? null;
            $msg = TelegramMessage::create([
                'user_id' => $user->id,
                'telegram_session_id' => $session->id,
                'chat_id' => $chatId,
                'telegram_message_id' => $msgId,
                'direction' => 'outgoing',
                'type' => $type,
                'message' => $text,
                'media_url' => $mediaUrl,
                'button_text' => $data['button_text'] ?? null,
                'button_url' => $data['button_url'] ?? null,
                'parse_mode' => $parseMode,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $session->increment('messages_sent_count');
            $this->subscriptionService->decrementMessages($user);

            return response()->json([
                'success' => true,
                'message' => 'Telegram message sent successfully.',
                'data' => [
                    'id' => $msg->id,
                    'telegram_message_id' => $msgId,
                    'status' => 'sent',
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error'] ?? 'Failed to send telegram message.',
        ], 400);
    }
}
