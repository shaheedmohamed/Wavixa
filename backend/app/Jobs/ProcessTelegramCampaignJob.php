<?php

namespace App\Jobs;

use App\Models\TelegramCampaign;
use App\Models\TelegramCampaignRecipient;
use App\Models\TelegramMessage;
use App\Services\SubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessTelegramCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $campaignId;
    public int $tries = 1;
    public int $timeout = 7200; // 2 hours

    public function __construct(int $campaignId)
    {
        $this->campaignId = $campaignId;
    }

    public function handle(SubscriptionService $subscriptionService): void
    {
        $campaign = TelegramCampaign::find($this->campaignId);
        if (!$campaign || $campaign->status !== TelegramCampaign::STATUS_RUNNING) {
            return;
        }

        $session = $campaign->session;
        if (!$session) {
            $campaign->update(['status' => TelegramCampaign::STATUS_FAILED]);
            return;
        }

        $user = $campaign->user;
        $delay = max(1, $campaign->delay_seconds ?? 2);
        $serviceUrl = rtrim(config('services.telegram.url', env('TELEGRAM_SERVICE_URL', 'http://127.0.0.1:3003')), '/') . '/api/telegram';

        while (true) {
            $campaign->refresh();
            if ($campaign->status !== TelegramCampaign::STATUS_RUNNING) {
                break;
            }

            if ($user && !$subscriptionService->hasRemainingMessages($user)) {
                $campaign->update(['status' => TelegramCampaign::STATUS_PAUSED]);
                break;
            }

            $recipient = TelegramCampaignRecipient::where('campaign_id', $campaign->id)
                ->where('status', 'pending')
                ->first();

            if (!$recipient) {
                $campaign->update([
                    'status' => TelegramCampaign::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);
                break;
            }

            $personalizedMessage = $campaign->message;
            if (!empty($recipient->name)) {
                $personalizedMessage = str_replace('{name}', $recipient->name, $personalizedMessage);
            }

            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . config('services.telegram.api_key', 'wavixa-telegram-service-secret-key-2026'),
                ])->timeout(30)->post("{$serviceUrl}/send-message", [
                    'userId' => (string) $campaign->user_id,
                    'to' => $recipient->chat_id,
                    'message' => $personalizedMessage,
                    'type' => $campaign->type,
                    'mediaUrl' => $campaign->media_url,
                    'buttonText' => $campaign->button_text,
                    'buttonUrl' => $campaign->button_url,
                    'parseMode' => $campaign->parse_mode,
                ]);

                if ($response->successful()) {
                    $resData = $response->json('data') ?? [];
                    $msgId = $resData['messageId'] ?? null;

                    $recipient->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                        'error_message' => null,
                    ]);

                    $campaign->increment('success_count');
                    $campaign->decrement('pending_count');

                    TelegramMessage::create([
                        'user_id' => $campaign->user_id,
                        'telegram_session_id' => $session->id,
                        'campaign_id' => $campaign->id,
                        'chat_id' => $recipient->chat_id,
                        'direction' => 'outgoing',
                        'type' => $campaign->type,
                        'message' => $personalizedMessage,
                        'media_url' => $campaign->media_url,
                        'parse_mode' => $campaign->parse_mode,
                        'button_text' => $campaign->button_text,
                        'button_url' => $campaign->button_url,
                        'status' => 'sent',
                        'telegram_message_id' => $msgId,
                        'sent_at' => now(),
                        'cost' => 1,
                    ]);

                    $session->increment('messages_sent_count');
                    if ($user) {
                        $subscriptionService->consumeMessage($user);
                    }
                } else {
                    $errMsg = $response->json('message') ?? 'Telegram send failed';
                    $recipient->update([
                        'status' => 'failed',
                        'error_message' => $errMsg,
                    ]);

                    $campaign->increment('failed_count');
                    $campaign->decrement('pending_count');
                }
            } catch (\Throwable $e) {
                $recipient->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);

                $campaign->increment('failed_count');
                $campaign->decrement('pending_count');
            }

            $session->update(['last_activity_at' => now()]);
            sleep($delay);
        }
    }
}
