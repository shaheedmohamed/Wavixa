<?php

namespace App\Jobs;

use App\Modules\MetaTest\Models\MetaTestCampaign;
use App\Modules\MetaTest\Models\MetaTestCampaignRecipient;
use App\Modules\MetaTest\Models\MetaTestTemplate;
use App\Modules\MetaTest\Services\MetaTestMessagingService;
use App\Services\SubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sends a Meta WhatsApp (Cloud API) bulk campaign recipient-by-recipient.
 * Runs until the recipient list is drained, the campaign is paused, or
 * the user runs out of message credits.
 */
class ProcessMetaTestCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $campaignId;
    public int $tries = 5;
    public int $timeout = 7200; // 2 hours

    public function __construct(int $campaignId)
    {
        $this->campaignId = $campaignId;
    }

    public function handle(SubscriptionService $subscriptionService): void
    {
        $campaign = MetaTestCampaign::with(['phoneNumber.waba.connection', 'template'])->find($this->campaignId);

        if (!$campaign || $campaign->status !== MetaTestCampaign::STATUS_RUNNING) {
            return;
        }

        $phone = $campaign->phoneNumber;
        $connection = $phone?->waba?->connection;
        $user = $campaign->user;

        if (!$phone || !$connection || empty($connection->access_token)) {
            $campaign->update([
                'status' => MetaTestCampaign::STATUS_FAILED,
                'error_message' => 'No active Meta connection or phone number.',
            ]);
            return;
        }

        $messaging = (new MetaTestMessagingService($campaign->user_id))
            ->setApiKeyId($campaign->api_key_id);
        $delay = max(1, (int) ($campaign->delay_seconds ?? 2));
        $processed = 0;

        while (true) {
            $campaign->refresh();
            if ($campaign->status !== MetaTestCampaign::STATUS_RUNNING) {
                break;
            }

            if ($user && !$subscriptionService->hasRemainingMessages($user)) {
                $campaign->update([
                    'status' => MetaTestCampaign::STATUS_PAUSED,
                    'error_message' => 'Paused: out of message credits.',
                ]);
                break;
            }

            $recipient = MetaTestCampaignRecipient::where('campaign_id', $campaign->id)
                ->where('status', MetaTestCampaignRecipient::STATUS_PENDING)
                ->orderBy('id')
                ->first();

            if (!$recipient) {
                $campaign->recomputeStats();
                break;
            }

            try {
                if ($campaign->message_type === 'auto') {
                    $message = $this->sendAuto($messaging, $campaign, $phone, $recipient);
                } elseif ($campaign->message_type === 'text' || !$campaign->template) {
                    $text = $this->resolveFreeFormText($campaign, $recipient) ?? '';
                    $message = $messaging->sendTextMessage($phone, $recipient->phone, $text);
                } elseif ($campaign->template) {
                    $message = $messaging->sendTemplateMessage(
                        $phone,
                        $recipient->phone,
                        $campaign->template,
                        $this->buildComponents($campaign->template, $recipient)
                    );
                } else {
                    $text = $this->renderText($campaign->text ?? '', $recipient);
                    $message = $messaging->sendTextMessage($phone, $recipient->phone, $text);
                }

                $recipient->update([
                    'status' => MetaTestCampaignRecipient::STATUS_SENT,
                    'meta_message_id' => $message->meta_message_id,
                    'message_id' => $message->id,
                    'sent_at' => now(),
                    'attempts' => $recipient->attempts + 1,
                    'error_message' => null,
                ]);

                if ($user) {
                    $subscriptionService->consumeMessage($user);
                }
            } catch (\Throwable $e) {
                $recipient->update([
                    'status' => MetaTestCampaignRecipient::STATUS_FAILED,
                    'failed_at' => now(),
                    'attempts' => $recipient->attempts + 1,
                    'error_message' => mb_substr($e->getMessage(), 0, 1000),
                ]);
            }

            // Cheap rolling counters; the webhook + recomputeStats keep them exact.
            $campaign->decrement('pending_count');

            // Refresh the visible counters periodically so the campaign page
            // shows live sent/failed numbers instead of zeros while running.
            if ((++$processed % 20) === 0) {
                $campaign->recomputeStats();
            }

            sleep($delay);
        }
    }

    /**
     * Map recipient variables to WhatsApp template BODY parameters.
     * {{1}}..{{n}} positional variables plus the recipient name as a
     * sensible default for a single-variable template.
     */
    protected function buildComponents(MetaTestTemplate $template, MetaTestCampaignRecipient $recipient): array
    {
        $components = [];
        $variables = $recipient->variables ?? [];

        foreach (($template->components ?? []) as $component) {
            if (($component['type'] ?? '') !== 'BODY') {
                continue;
            }

            $body = $component['text'] ?? '';
            $count = preg_match_all('/\{\{\d+\}\}/', $body);

            $params = [];
            for ($i = 1; $i <= $count; $i++) {
                $value = $variables[$i - 1] ?? ($i === 1 ? ($recipient->name ?? 'Customer') : '');
                $params[] = ['type' => 'text', 'text' => (string) $value];
            }

            if (!empty($params)) {
                $components[] = ['type' => 'body', 'parameters' => $params];
            }
        }

        return $components;
    }

    protected function renderText(string $text, MetaTestCampaignRecipient $recipient): string
    {
        $name = trim($recipient->name ?? '');
        $firstName = $name !== '' ? trim(explode(' ', $name)[0]) : 'Customer';

        return str_replace(['{{name}}', '{name}'], $firstName, $text);
    }

    /**
     * "auto" campaigns always try free-form text first. When Meta rejects
     * it with a closed-window error (131047 / 132xxx re-engagement family)
     * we fall back to the approved template. Trying first is more accurate
     * than checking local inbound history, which only works when the
     * webhook is already receiving messages.
     */
    protected function sendAuto(MetaTestMessagingService $messaging, MetaTestCampaign $campaign, $phone, MetaTestCampaignRecipient $recipient)
    {
        $text = $this->resolveFreeFormText($campaign, $recipient);

        if (!empty($text)) {
            try {
                return $messaging->sendTextMessage($phone, $recipient->phone, $text);
            } catch (\Throwable $e) {
                if (!$campaign->template || !$this->isWindowError($e)) {
                    throw $e;
                }
                // Outside the 24h window — fall through to the template.
            }
        }

        if (!$campaign->template) {
            throw new \RuntimeException('No free-form text and no template fallback.');
        }

        return $messaging->sendTemplateMessage(
            $phone,
            $recipient->phone,
            $campaign->template,
            $this->buildComponents($campaign->template, $recipient)
        );
    }

    protected function isWindowError(\Throwable $e): bool
    {
        $code = (int) $e->getCode();
        if (in_array($code, [131047, 131026, 132000, 132001, 132016], true)) {
            return true;
        }

        return (bool) preg_match('/re-?engagement|outside.*(24|window)|24.?hour/i', $e->getMessage());
    }

    /**
     * Free-form body, in priority order: per-recipient custom text,
     * campaign text, then the template BODY rendered with variables.
     */
    protected function resolveFreeFormText(MetaTestCampaign $campaign, MetaTestCampaignRecipient $recipient): ?string
    {
        $text = $recipient->custom_text ?: $campaign->text;

        if (empty($text) && $campaign->template) {
            foreach (($campaign->template->components ?? []) as $component) {
                if (($component['type'] ?? '') === 'BODY') {
                    $text = $component['text'] ?? '';
                    break;
                }
            }
        }

        if (empty($text)) {
            return null;
        }

        $text = $this->renderText($text, $recipient);

        // Substitute {{1}}..{{n}} template variables with recipient values.
        $variables = $recipient->variables ?? [];
        foreach ($variables as $i => $value) {
            $text = str_replace('{{' . ($i + 1) . '}}', (string) $value, $text);
        }

        return $text;
    }
}
