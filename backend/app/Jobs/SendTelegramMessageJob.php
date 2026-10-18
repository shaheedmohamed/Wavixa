<?php

namespace App\Jobs;

use App\Models\TelegramMessage;
use App\Models\TelegramSession;
use App\Services\SubscriptionService;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendTelegramMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $messageId;
    public int $tries = 3;

    public function __construct(int $messageId)
    {
        $this->messageId = $messageId;
    }

    public function handle(TelegramService $telegramService, SubscriptionService $subscriptionService): void
    {
        $message = TelegramMessage::find($this->messageId);
        if (!$message || $message->status === 'sent') {
            return;
        }

        $session = $message->session;
        if (!$session || !$session->bot_token) {
            $message->update([
                'status' => 'failed',
                'error_message' => 'Telegram bot session not available',
            ]);
            return;
        }

        $user = $message->user;
        if ($user && $subscriptionService->isOutOfCredits($user)) {
            $message->update([
                'status' => 'failed',
                'error_message' => 'Out of message credits',
            ]);
            return;
        }

        $options = [
            'parse_mode' => $message->parse_mode ?? 'HTML',
            'button_text' => $message->button_text,
            'button_url' => $message->button_url,
        ];

        if ($message->type === 'photo' && $message->media_url) {
            $result = $telegramService->sendPhoto($session->bot_token, $message->chat_id, $message->media_url, $message->message ?? '', $options);
        } elseif ($message->type === 'document' && $message->media_url) {
            $result = $telegramService->sendDocument($session->bot_token, $message->chat_id, $message->media_url, $message->message ?? '', $options);
        } else {
            $result = $telegramService->sendMessage($session->bot_token, $message->chat_id, $message->message ?? '', $options);
        }

        if ($result['success']) {
            $message->update([
                'status' => 'sent',
                'telegram_message_id' => $result['result']['message_id'] ?? null,
                'sent_at' => now(),
            ]);

            $session->increment('messages_sent_count');
            $session->update(['last_activity_at' => now()]);

            if ($user) {
                $subscriptionService->decrementMessages($user);
            }
        } else {
            $message->update([
                'status' => 'failed',
                'error_message' => $result['error'] ?? 'Telegram send error',
            ]);
        }
    }
}
