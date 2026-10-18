<?php

namespace App\Services;

use App\Jobs\SendWebhookJob;
use App\Models\Webhook;
use App\Models\WebhookLog;

class WebhookDispatcher
{
    /**
     * Dispatch webhooks for all active webhooks of a user for a given event.
     */
    public static function dispatch(int $userId, string $event, array $payload): void
    {
        $webhooks = Webhook::where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        foreach ($webhooks as $webhook) {
            if (!$webhook->listensTo($event)) {
                continue;
            }

            $fullPayload = array_merge($payload, [
                'event' => $event,
                'timestamp' => now()->toIso8601String(),
                'webhook_id' => $webhook->id,
                'user_id' => $userId,
            ]);

            $log = WebhookLog::create([
                'webhook_id' => $webhook->id,
                'user_id' => $userId,
                'event' => $event,
                'status' => 'pending',
                'payload' => json_encode($fullPayload),
                'attempts' => 0,
            ]);

            SendWebhookJob::dispatch($log);
        }
    }
}
