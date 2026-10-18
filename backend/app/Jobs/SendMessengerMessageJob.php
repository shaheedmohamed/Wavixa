<?php

namespace App\Jobs;

use App\Models\MessengerConversation;
use App\Models\MessengerMessage;
use App\Models\MessengerPage;
use App\Services\MessengerService;
use App\Services\SubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendMessengerMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $messageId;
    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(int $messageId)
    {
        $this->messageId = $messageId;
    }

    public function handle(MessengerService $messenger, SubscriptionService $subscriptionService): void
    {
        $message = MessengerMessage::find($this->messageId);
        if (!$message || $message->status === MessengerMessage::STATUS_SENT) {
            return;
        }

        $page = MessengerPage::find($message->page_id);
        if (!$page || $page->status !== 'connected') {
            $message->update([
                'status' => MessengerMessage::STATUS_FAILED,
                'error_message' => 'Messenger page is not connected',
                'failed_at' => now(),
            ]);
            return;
        }

        $user = $message->user;
        if ($user && !$user->is_admin && !$this->isAutoReply() && !$subscriptionService->hasRemainingMessages($user)) {
            $message->update([
                'status' => MessengerMessage::STATUS_FAILED,
                'error_message' => 'No remaining message credits',
                'failed_at' => now(),
            ]);
            return;
        }

        $conversation = $message->conversation;
        $tag = null;

        if ($conversation) {
            try {
                $tag = $messenger->resolveMessagingTag($conversation, $this->isHumanReply());
            } catch (\Throwable $e) {
                $message->update([
                    'status' => MessengerMessage::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                    'failed_at' => now(),
                ]);
                return;
            }
        }

        try {
            if ($message->message_type === 'text') {
                $result = $messenger->sendMessage($page, $message->psid, $message->message ?? '', $tag);
            } elseif (in_array($message->message_type, ['image', 'video', 'audio', 'file'])) {
                $result = $messenger->sendAttachment($page, $message->psid, $message->message_type, $message->media_url ?? '', $tag);
            } else {
                $result = $messenger->sendMessage($page, $message->psid, $message->message ?? '', $tag);
            }

            $message->update([
                'status' => MessengerMessage::STATUS_SENT,
                'external_message_id' => $result['message_id'] ?? null,
                'sent_at' => now(),
                'error_message' => null,
            ]);

            $page->increment('messages_sent_count');
            $page->update(['last_activity_at' => now()]);

            if ($conversation) {
                $conversation->update(['last_message_at' => now()]);
            }

            if ($user && !$user->is_admin && !$this->isAutoReply()) {
                $subscriptionService->consumeMessage($user);
            }
        } catch (\Throwable $e) {
            Log::error('Messenger send failed: ' . $e->getMessage());
            $message->update([
                'status' => MessengerMessage::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'failed_at' => now(),
            ]);
        }
    }

    private function isAutoReply(): bool
    {
        return false; // Jobs for auto replies can set this later if needed.
    }

    private function isHumanReply(): bool
    {
        return !$this->isAutoReply();
    }
}
