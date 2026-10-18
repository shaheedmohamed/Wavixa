<?php

namespace App\Jobs;

use App\Models\MessengerCampaign;
use App\Models\MessengerCampaignRecipient;
use App\Models\MessengerConversation;
use App\Models\MessengerMessage;
use App\Services\MessengerService;
use App\Services\SubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMessengerCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $campaignId;
    public int $tries = 1;
    public int $timeout = 7200; // 2 hours

    public function __construct(int $campaignId)
    {
        $this->campaignId = $campaignId;
    }

    public function handle(MessengerService $messenger, SubscriptionService $subscriptionService): void
    {
        $campaign = MessengerCampaign::find($this->campaignId);
        if (!$campaign || $campaign->status !== MessengerCampaign::STATUS_RUNNING) {
            return;
        }

        $page = $campaign->page;
        if (!$page || $page->status !== 'connected') {
            $campaign->update([
                'status' => MessengerCampaign::STATUS_FAILED,
                'error_message' => 'Messenger page is not connected',
            ]);
            return;
        }

        $user = $campaign->user;

        while (true) {
            $campaign->refresh();
            if ($campaign->status !== MessengerCampaign::STATUS_RUNNING) {
                break;
            }

            if ($user && !$subscriptionService->hasRemainingMessages($user)) {
                $campaign->update(['status' => MessengerCampaign::STATUS_PAUSED]);
                break;
            }

            $recipient = MessengerCampaignRecipient::where('campaign_id', $campaign->id)
                ->where('status', MessengerCampaignRecipient::STATUS_PENDING)
                ->first();

            if (!$recipient) {
                $campaign->update([
                    'status' => MessengerCampaign::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);
                break;
            }

            $conversation = MessengerConversation::where('page_id', $page->id)
                ->where('psid', $recipient->psid)
                ->first();

            // Enforce Meta messaging window for campaigns.
            if (!$conversation || !$conversation->isMessagingWindowOpen()) {
                $recipient->update([
                    'status' => MessengerCampaignRecipient::STATUS_EXCLUDED,
                    'error_message' => 'No active messaging window (user has not messaged within 24 hours)',
                ]);
                $campaign->increment('excluded_count');
                $campaign->decrement('pending_count');
                $campaign->recomputeStats();
                continue;
            }

            $text = $campaign->message;
            if (!empty($recipient->name)) {
                $text = str_replace('{name}', $recipient->name, $text);
            }

            try {
                // Campaigns are not human support replies, so only send if inside 24h window.
                $result = $messenger->sendMessage($page, $recipient->psid, $text);

                $message = MessengerMessage::create([
                    'user_id' => $campaign->user_id,
                    'page_id' => $page->id,
                    'conversation_id' => $conversation->id,
                    'psid' => $recipient->psid,
                    'direction' => 'outgoing',
                    'message_type' => 'text',
                    'message' => $text,
                    'status' => MessengerMessage::STATUS_SENT,
                    'external_message_id' => $result['message_id'] ?? null,
                    'campaign_id' => $campaign->id,
                    'campaign_recipient_id' => $recipient->id,
                    'sent_at' => now(),
                    'cost' => 1,
                ]);

                $recipient->update([
                    'status' => MessengerCampaignRecipient::STATUS_SENT,
                    'sent_at' => now(),
                    'external_message_id' => $result['message_id'] ?? null,
                    'error_message' => null,
                ]);

                $page->increment('messages_sent_count');
                $page->update(['last_activity_at' => now()]);

                if ($user) {
                    $subscriptionService->consumeMessage($user);
                }
            } catch (\Throwable $e) {
                Log::error("Messenger campaign send failed for recipient {$recipient->id}: " . $e->getMessage());
                $recipient->update([
                    'status' => MessengerCampaignRecipient::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                ]);
            }

            $campaign->recomputeStats();

            $delay = rand((int) $campaign->delay_min, (int) $campaign->delay_max);
            sleep(max(1, $delay));
        }
    }
}
