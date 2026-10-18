<?php

namespace App\Jobs;

use App\Models\Webhook;
use App\Models\WebhookLog;
use App\Services\FraudDetectionService;
use App\Services\SafeUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public WebhookLog $log;

    public function __construct(WebhookLog $log)
    {
        $this->log = $log;
    }

    public function handle(): void
    {
        $webhook = Webhook::find($this->log->webhook_id);

        if (!$webhook || !$webhook->is_active) {
            $this->log->update([
                'status' => 'failed',
                'error_message' => 'Webhook inactive or deleted',
                'sent_at' => now(),
            ]);
            return;
        }

        if (!SafeUrl::isSafe($webhook->url)) {
            FraudDetectionService::report('ssrf_attempt_executed', 'critical', 'Outbound webhook delivery attempted to call an unsafe/internal URL', [
                'url' => $webhook->url,
                'webhook_id' => $webhook->id,
                'user_id' => $webhook->user_id,
                'ip_address' => request()?->ip() ?? '0.0.0.0',
                'user_agent' => request()?->userAgent() ?? 'queue-worker',
            ]);
            $this->log->update([
                'status' => 'failed',
                'error_message' => 'Webhook URL is not safe',
                'sent_at' => now(),
            ]);
            return;
        }

        $payload = json_decode($this->log->payload, true);

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'Wavixa-Webhook/1.0',
            'X-Webhook-Event' => $this->log->event,
            'X-Webhook-Id' => (string) $this->log->id,
            'X-Webhook-Signature' => $this->generateSignature($this->log->payload, $webhook->secret),
        ];

        try {
            $response = Http::timeout(30)
                ->withoutRedirecting()
                ->withHeaders($headers)
                ->post($webhook->url, $payload);

            $body = $response->body();
            if (strlen($body) > 10000) {
                $body = substr($body, 0, 10000) . '... [truncated]';
            }

            $this->log->update([
                'status' => $response->successful() ? 'success' : 'failed',
                'response' => $body,
                'http_status' => $response->status(),
                'attempts' => $this->log->attempts + 1,
                'sent_at' => now(),
                'error_message' => $response->successful() ? null : "HTTP {$response->status()}",
            ]);

            if (!$response->successful() && $this->log->attempts < $webhook->max_retries) {
                $this->scheduleRetry($webhook);
            }
        } catch (\Exception $e) {
            $this->log->update([
                'status' => 'failed',
                'error_message' => 'Webhook delivery failed',
                'attempts' => $this->log->attempts + 1,
                'sent_at' => now(),
            ]);

            Log::error("Webhook {$this->log->id} failed: " . $e->getMessage());

            if ($this->log->attempts < $webhook->max_retries) {
                $this->scheduleRetry($webhook);
            }
        }
    }

    private function scheduleRetry(Webhook $webhook): void
    {
        $delay = min(60 * (2 ** $this->log->attempts), 3600); // 2, 4, 8 minutes... max 1 hour

        $this->log->update(['next_retry_at' => now()->addSeconds($delay)]);

        SendWebhookJob::dispatch($this->log)->delay($delay);
    }

    private function generateSignature(string $payload, ?string $secret): string
    {
        if (!$secret) {
            return '';
        }

        return hash_hmac('sha256', $payload, $secret);
    }
}
