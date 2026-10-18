<?php

namespace App\Jobs;

use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppSession;
use App\Services\AdminAlertService;
use App\Services\PhoneNormalizer;
use App\Services\SubscriptionService;
use App\Services\WebhookDispatcher;
use App\Services\LinkMaskerService;
use App\Services\UserReputationService;
use App\Services\WhatsAppWarmupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 10;

    /**
     * The unique identifier of the message being processed.
     */
    public int $messageId;

    /**
     * Whether this is a system message (e.g., OTP) and should not consume credits.
     */
    public bool $isSystem = false;

    /**
     * Whether this is an auto-reply and should bypass inter-message throttling.
     */
    public bool $isAutoReply = false;

    /**
     * Create a new job instance.
     */
    public function __construct(int $messageId, bool $isSystem = false, bool $isAutoReply = false)
    {
        $this->messageId = $messageId;
        $this->isSystem = $isSystem;
        $this->isAutoReply = $isAutoReply;
    }

    /**
     * Allow the job to keep retrying for a long time so it can wait in line
     * for its per-device one-minute slot without failing with "attempted too many times".
     */
    public function retryUntil(): \DateTime
    {
        return now()->addHours(48);
    }

    /**
     * Execute the job.
     */
    public function handle(SubscriptionService $subscriptionService): void
    {
        $message = WhatsAppMessage::find($this->messageId);

        if (!$message) {
            Log::warning("WhatsApp queue job: message {$this->messageId} not found");
            return;
        }

        // Prevent concurrent sends to maintain stability and order
        if ($this->isAutoReply && $message->session_id && $message->to) {
            $lockKey = 'whatsapp-send-auto:' . sha1($message->session_id . ':' . $message->to);
        } else {
            $lockKey = 'whatsapp-send-message-' . ($message->session_id ?? $message->user_id);
        }
        $lock = Cache::lock($lockKey, 180);

        if (!$lock->get()) {
            // Release the job back to the queue to retry later
            $this->release(10);
            return;
        }

        try {
            $this->processMessage($message, $subscriptionService);
        } catch (\RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'THROTTLE:')) {
                $delay = (int) substr($e->getMessage(), 9);
                $this->release($delay);
                return;
            }
            $this->fail($e);
            return;
        } catch (\Throwable $e) {
            // Real WhatsApp/connection errors or unexpected exceptions should not be retried endlessly.
            // Mark the job as failed immediately; the failed() method handles the message status.
            $this->fail($e);
            return;
        } finally {
            $lock->release();
        }
    }

    /**
     * Process the actual message sending.
     */
    private function processMessage(WhatsAppMessage $message, SubscriptionService $subscriptionService): void
    {
        // Update status to processing and record time
        $message->update([
            'status' => WhatsAppMessage::STATUS_PROCESSING,
            'processed_at' => now(),
            'attempts' => $message->attempts + 1,
            'job_id' => $this->job ? $this->job->uuid() : null,
        ]);

        $userId = $message->user_id;
        $user = User::find($userId);

        if ($user && !$user->is_admin && !UserReputationService::canSendMessages($user)) {
            throw new \Exception('تم تعليق الحساب مؤقتاً بسبب سلوك مرتبط بمخاطر عالية. يرجى مراجعة الدعم.');
        }

        $to = $this->normalizePhone($message->to);

        // Validate WhatsApp connection - use the session_id directly if available
        if ($message->session_id) {
            $session = WhatsAppSession::find($message->session_id);
            if (!$session || $session->status !== 'connected') {
                throw new \Exception('Selected WhatsApp session is not connected');
            }
        } else {
            $session = WhatsAppSession::where('status', 'connected')->first();
            if (!$session) {
                throw new \Exception('WhatsApp not connected');
            }
            $message->update(['session_id' => $session->id]);
        }

        $serviceUrl = config('services.whatsapp.url', 'http://localhost:3002');
        $nodeUserId = $session->nodeUserId();
        $statusResponse = $this->request(10)->get("{$serviceUrl}/api/whatsapp/status/{$nodeUserId}");

        if (!$statusResponse->successful() || ($statusResponse->json()['status'] ?? 'disconnected') !== 'connected') {
            throw new \Exception('WhatsApp connection is not active');
        }

        // Pre-verification filter: check if number is registered on WhatsApp
        try {
            $checkResponse = $this->request(15)->post("{$serviceUrl}/api/whatsapp/check-number", [
                'userId' => $nodeUserId,
                'to' => $to,
            ]);

            if ($checkResponse->successful()) {
                $checkData = $checkResponse->json();
                if (isset($checkData['registered']) && $checkData['registered'] === false) {
                    throw new \Exception('الرقم غير مسجل على واتساب');
                }
            }
        } catch (\Exception $checkErr) {
            if ($checkErr->getMessage() === 'الرقم غير مسجل على واتساب') {
                throw $checkErr;
            }
            Log::warning("WhatsApp pre-check warning for {$to}: " . $checkErr->getMessage());
        }

        // Anti-ban throttling: enforce delays and rate limits before sending
        $this->throttleSend($session, $to, $message);

        // Check subscription remaining messages before sending
        $user = User::find($message->user_id);
        $isAdmin = $user && $user->is_admin;
        $remaining = null;

        if (!$isAdmin && !$this->isSystem) {
            if (!$subscriptionService->hasRemainingMessages($user)) {
                throw new \Exception('Insufficient subscription messages or subscription inactive.');
            }
        }

        // Apply Spintax randomization to prevent sending identical texts
        $messageContent = $message->message;
        if ($message->message_type === 'text') {
            $messageContent = self::parseSpintax($message->message);
            // Dynamic Link Masking: replace any URLs with unique trackable redirect links
            $messageContent = LinkMaskerService::maskUrlsInText($messageContent, $message->user_id);
        }

        if ($this->isAutoReply) {
            $this->setTyping($session, $to, true);
        }

        $result = match ($message->message_type) {
            'text' => $this->sendTextMessage($serviceUrl, $nodeUserId, $to, $messageContent),
            'image', 'video', 'voice' => $this->sendMediaMessage(
                $serviceUrl,
                $nodeUserId,
                $to,
                $message->message_type,
                $message->message
            ),
            'document' => $this->sendDocumentMessage($serviceUrl, $nodeUserId, $to, $message->message),
            'contact' => $this->sendContactMessage($serviceUrl, $nodeUserId, $to, $message->message),
            'location' => $this->sendLocationMessage($serviceUrl, $nodeUserId, $to, $message->message),
            default => throw new \Exception("Unsupported message type: {$message->message_type}"),
        };

        // Record successful send for anti-ban throttling counters
        $this->recordSendThrottle($session, $to);

        // Deduct one message credit only upon actual successful send
        if (!$isAdmin && !$this->isSystem) {
            $remaining = $subscriptionService->consumeMessage($user);
        }

        // Update message as sent
        $message->update([
            'message' => $messageContent,
            'status' => WhatsAppMessage::STATUS_SENT,
            'message_id' => $result['messageId'] ?? null,
            'completed_at' => now(),
            'sent_at' => now(),
            'error_message' => null,
        ]);

        if ($message->campaign_recipient_id) {
            $recipient = \App\Models\WhatsAppCampaignRecipient::find($message->campaign_recipient_id);
            if ($recipient) {
                $recipient->update([
                    'status' => \App\Models\WhatsAppCampaignRecipient::STATUS_SENT,
                    'message_id' => $message->message_id,
                    'sent_at' => now(),
                ]);
                $recipient->campaign?->recomputeStats();
            }
        }

        if (!$isAdmin && !$this->isSystem && $remaining === 0) {
            $subscriptionService->sendLowBalanceNotification($user);
        }

        if ($user && !$user->is_admin && !$this->isSystem) {
            UserReputationService::recalculateIfStale($user, 60);
        }

        WebhookDispatcher::dispatch($message->user_id, 'message.sent', [
            'message_id' => $message->id,
            'external_message_id' => $message->message_id,
            'to' => $message->to,
            'from' => $message->from,
            'message_type' => $message->message_type,
            'message' => $message->message,
            'status' => $message->status,
            'sent_at' => $message->sent_at,
        ]);
    }

    /**
     * Parse Spintax format (e.g., "{Hello|Hi|Hey} {friend|customer}!") into randomized text.
     */
    public static function parseSpintax(?string $text): string
    {
        if (empty($text)) {
            return '';
        }

        $pattern = '/\{([^{}]+)\}/';
        while (preg_match($pattern, $text)) {
            $text = (string) preg_replace_callback($pattern, function ($matches) {
                $parts = explode('|', $matches[1]);
                return $parts[array_rand($parts)];
            }, $text);
        }

        return $text;
    }

    /**
     * Enforce anti-ban throttling: per-session hourly/daily limits,
     * randomized smart delays, warm-up pacing, batch pauses, and same-recipient cooldown.
     */
    private function throttleSend(WhatsAppSession $session, string $to, ?WhatsAppMessage $message = null): void
    {
        $now = now();

        // System messages (OTP/alerts) only get a short minimum delay to avoid bursts
        if ($this->isSystem) {
            $lastKey = $this->throttleKey('last-send', $session->id);
            $lastSend = Cache::get($lastKey);
            if ($lastSend) {
                $elapsed = $now->getTimestamp() - $lastSend;
                if ($elapsed < 2) {
                    sleep(2 - $elapsed);
                }
            }
            return;
        }

        // Enforce at least a 60-second gap between sends from the same device.
        $minDelay = max(60, (int) SystemSetting::get('whatsapp_min_send_delay_seconds', 60));
        $maxDelay = max(60, (int) SystemSetting::get('whatsapp_max_send_delay_seconds', 60));

        // Allow per-campaign delay overrides, but never drop below the 60-second minimum.
        if ($message && $message->campaign_id) {
            $campaign = \App\Models\WhatsAppCampaign::find($message->campaign_id);
            if ($campaign && $campaign->delay_min > 0 && $campaign->delay_max > 0) {
                $minDelay = max($minDelay, (int) $campaign->delay_min);
                $maxDelay = max($maxDelay, (int) $campaign->delay_max);
            }
        }

        if ($maxDelay < $minDelay) {
            $maxDelay = $minDelay + 5;
        }

        $perHour = (int) SystemSetting::get('whatsapp_max_messages_per_hour', 100);
        $perDay = (int) SystemSetting::get('whatsapp_max_messages_per_day', 1000);
        $sameRecipientCooldown = (int) SystemSetting::get('whatsapp_same_recipient_cooldown_seconds', 60);

        $batchPauseEvery = (int) SystemSetting::get('whatsapp_batch_pause_every', 20);
        $batchPauseDuration = (int) SystemSetting::get('whatsapp_batch_pause_duration_seconds', 45);
        $warmupEnabled = (bool) SystemSetting::get('whatsapp_warmup_enabled', 1);

        // Warm-up calculation for newly connected devices.
        // Auto-replies and system messages bypass the graduated warm-up and
        // use the global anti-ban limits instead.
        if ($warmupEnabled && !$this->isSystem && !$this->isAutoReply) {
            $warmup = WhatsAppWarmupService::limits($session);

            if ($warmup['per_day'] <= 0) {
                throw new \RuntimeException('WhatsApp session is not eligible for warm-up sending.');
            }

            $perHour = min($perHour, $warmup['per_hour']);
            $perDay = min($perDay, $warmup['per_day']);
        }

        $hourKey = $this->throttleKey('hour', $session->id . ':' . $now->format('YmdH'));
        $dayKey = $this->throttleKey('day', $session->id . ':' . $now->format('Ymd'));
        $lastKey = $this->throttleKey('last-send', $session->id);
        $destKey = $this->throttleKey('dest', $session->id . ':' . sha1($to));
        $batchKey = $this->throttleKey('batch', $session->id);

        $hourCount = (int) Cache::get($hourKey, 0);
        $dayCount = (int) Cache::get($dayKey, 0);

        if ($hourCount >= $perHour) {
            $nextHour = $now->copy()->endOfHour()->addSecond();
            $delay = max(1, $nextHour->getTimestamp() - $now->getTimestamp());
            Log::info("Anti-ban: hourly limit ({$perHour}) reached for session {$session->id}, releasing for {$delay}s");
            throw new \RuntimeException("THROTTLE:{$delay}");
        }

        if ($dayCount >= $perDay) {
            $nextDay = $now->copy()->endOfDay()->addSecond();
            $delay = max(1, $nextDay->getTimestamp() - $now->getTimestamp());
            Log::info("Anti-ban: daily limit ({$perDay}) reached for session {$session->id}, releasing for {$delay}s");
            throw new \RuntimeException("THROTTLE:{$delay}");
        }

        // Same recipient cooldown, batch pause, and smart dynamic delay do not apply
        // to auto-replies so multiple conversations can be handled concurrently.
        if (!$this->isAutoReply) {
            // Same recipient cooldown to prevent spamming single number repeatedly
            $effectiveCooldown = max(5, $sameRecipientCooldown + random_int(-10, 10));
            $lastTo = Cache::get($destKey);
            if ($lastTo) {
                $elapsed = $now->getTimestamp() - $lastTo;
                if ($elapsed < $effectiveCooldown) {
                    $sleepFor = $effectiveCooldown - $elapsed;
                    Log::info("Anti-ban: cooling down session {$session->id} for {$to} ({$sleepFor}s)");
                    throw new \RuntimeException("THROTTLE:{$sleepFor}");
                }
            }

            // Batch pause: simulate human rest after sending N messages in a row
            $actualBatchPauseEvery = random_int(max(1, $batchPauseEvery - 5), $batchPauseEvery + 5);
            $batchCount = (int) Cache::get($batchKey, 0);
            if ($actualBatchPauseEvery > 0 && $batchCount >= $actualBatchPauseEvery) {
                $pause = max(10, $batchPauseDuration + random_int(-5, 20));
                Log::info("Anti-ban: batch pause reached ({$batchCount} msgs) for session {$session->id}, cooling down for {$pause}s");
                Cache::put($batchKey, 0, now()->addHours(2));
                throw new \RuntimeException("THROTTLE:{$pause}");
            }

        // Dynamic delay between messages from the same device (exactly 60s)
            $lastSend = Cache::get($lastKey);
            if ($lastSend) {
                $elapsed = $now->getTimestamp() - $lastSend;
                $requiredDelay = ($minDelay === $maxDelay) ? $minDelay : random_int($minDelay, $maxDelay);
                if ($elapsed < $requiredDelay) {
                    $sleepFor = $requiredDelay - $elapsed;
                    Log::info("Anti-ban: delaying session {$session->id} for {$sleepFor}s before sending to {$to}");
                    throw new \RuntimeException("THROTTLE:{$sleepFor}");
                }
            }
        }
    }

    /**
     * Record a successful send for anti-ban throttling counters.
     */
    private function recordSendThrottle(WhatsAppSession $session, string $to): void
    {
        $now = now();

        if (!$this->isSystem) {
            $hourKey = $this->throttleKey('hour', $session->id . ':' . $now->format('YmdH'));
            $dayKey = $this->throttleKey('day', $session->id . ':' . $now->format('Ymd'));
            $batchKey = $this->throttleKey('batch', $session->id);

            Cache::put($hourKey, ((int) Cache::get($hourKey, 0)) + 1, now()->addHours(2));
            Cache::put($dayKey, ((int) Cache::get($dayKey, 0)) + 1, now()->addHours(26));
            Cache::put($batchKey, ((int) Cache::get($batchKey, 0)) + 1, now()->addHours(2));
        }

        if (!$this->isAutoReply) {
            Cache::put($this->throttleKey('last-send', $session->id), $now->getTimestamp(), now()->addHours(2));
            Cache::put($this->throttleKey('dest', $session->id . ':' . sha1($to)), $now->getTimestamp(), now()->addHours(2));
        }
    }

    /**
     * Build a cache key for throttling data.
     */
    private function throttleKey(string $type, string $suffix): string
    {
        return "wa-throttle-{$type}:{$suffix}";
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        $message = WhatsAppMessage::find($this->messageId);

        if (!$message) {
            return;
        }

        $message->update([
            'status' => WhatsAppMessage::STATUS_FAILED,
            'error_message' => $exception->getMessage(),
            'failed_at' => now(),
        ]);

        if ($message->campaign_recipient_id) {
            $recipient = \App\Models\WhatsAppCampaignRecipient::find($message->campaign_recipient_id);
            if ($recipient) {
                $recipient->update([
                    'status' => \App\Models\WhatsAppCampaignRecipient::STATUS_FAILED,
                    'error_message' => $exception->getMessage(),
                    'failed_at' => now(),
                ]);
                $recipient->campaign?->recomputeStats();
            }
        }

        $user = User::find($message->user_id);

        WebhookDispatcher::dispatch($message->user_id, 'message.failed', [
            'message_id' => $message->id,
            'to' => $message->to,
            'from' => $message->from,
            'message_type' => $message->message_type,
            'message' => $message->message,
            'status' => $message->status,
            'error_message' => $exception->getMessage(),
            'failed_at' => $message->failed_at,
            'attempts' => $message->attempts,
        ]);

        Log::error("WhatsApp message {$message->id} failed after {$message->attempts} attempts: " . $exception->getMessage());

        try {
            app(AdminAlertService::class)->notifyMessageFailed($message->id, $message->to, $exception->getMessage());
        } catch (\Throwable $alertError) {
            Log::error('AdminAlert: failed to send failure alert', ['error' => $alertError->getMessage()]);
        }
    }

    /**
     * HTTP client authenticated with the WhatsApp service key.
     */
    private function request(float $timeout = 10)
    {
        return Http::timeout($timeout)->withHeaders([
            'Authorization' => 'Bearer ' . config('services.whatsapp.api_key'),
        ]);
    }

    /**
     * Send a text message via WhatsApp service.
     */
    private function sendTextMessage(string $serviceUrl, string $nodeUserId, string $to, string $messageText): array
    {
        $response = $this->request(100)->post("{$serviceUrl}/api/whatsapp/send-message", [
            'userId' => $nodeUserId,
            'to' => $to,
            'message' => $messageText,
        ]);

        return $this->parseServiceResponse($response, 'text');
    }

    /**
     * Send a media message via WhatsApp service.
     */
    private function sendMediaMessage(string $serviceUrl, string $nodeUserId, string $to, string $type, string $content): array
    {
        $serviceType = match ($type) {
            'voice' => 'voice',
            default => $type,
        };

        $caption = '';
        $mediaContent = $content;
        $fileName = null;

        if (str_starts_with(trim($content), '{')) {
            $decoded = json_decode($content, true);
            if (is_array($decoded) && isset($decoded['content'])) {
                $mediaContent = $decoded['content'];
                $caption = $decoded['caption'] ?? '';
                $fileName = $decoded['file_name'] ?? $decoded['filename'] ?? null;
            }
        }

        $params = [
            'userId' => $nodeUserId,
            'to' => $to,
            'type' => $serviceType,
            'content' => $mediaContent,
        ];
        if ($caption !== '') {
            $params['caption'] = $caption;
        }
        if ($fileName !== null) {
            $params['fileName'] = $fileName;
        }

        $response = $this->request(100)->post("{$serviceUrl}/api/whatsapp/send-media", $params);

        return $this->parseServiceResponse($response, $type);
    }

    /**
     * Send a document/file message via WhatsApp service.
     */
    private function sendDocumentMessage(string $serviceUrl, string $nodeUserId, string $to, string $payload): array
    {
        $data = json_decode($payload, true);
        if (!is_array($data) || empty($data['content'])) {
            throw new \Exception('Invalid document payload');
        }

        $fileName = $data['file_name'] ?? $data['filename'] ?? 'document';
        $caption = $data['caption'] ?? '';

        $params = [
            'userId' => $nodeUserId,
            'to' => $to,
            'type' => 'document',
            'content' => $data['content'],
            'fileName' => $fileName,
        ];
        if ($caption !== '') {
            $params['caption'] = $caption;
        }

        $response = $this->request(100)->post("{$serviceUrl}/api/whatsapp/send-media", $params);

        return $this->parseServiceResponse($response, 'document');
    }

    /**
     * Send a contact message via WhatsApp service.
     */
    private function sendContactMessage(string $serviceUrl, string $nodeUserId, string $to, string $content): array
    {
        // Content stored as "Display Name (Phone)"
        if (preg_match('/^(.*)\s\(([^)]+)\)$/', $content, $matches)) {
            $displayName = $matches[1];
            $phone = $matches[2];
        } else {
            $displayName = $content;
            $phone = $to;
        }

        $response = $this->request(30)->post("{$serviceUrl}/api/whatsapp/send-media", [
            'userId' => $nodeUserId,
            'to' => $to,
            'type' => 'contact',
            'content' => [
                'displayName' => $displayName,
                'phone' => $phone,
            ],
        ]);

        return $this->parseServiceResponse($response, 'contact');
    }

    /**
     * Send a location message via WhatsApp service.
     */
    private function sendLocationMessage(string $serviceUrl, string $nodeUserId, string $to, string $content): array
    {
        // Content stored as "Name (lat, long)"
        if (preg_match('/^(.*)\s\(([-\d.]+),\s*([-\d.]+)\)$/', $content, $matches)) {
            $name = $matches[1];
            $latitude = $matches[2];
            $longitude = $matches[3];
        } else {
            $name = '';
            $latitude = 0;
            $longitude = 0;
        }

        $response = $this->request(30)->post("{$serviceUrl}/api/whatsapp/send-media", [
            'userId' => $nodeUserId,
            'to' => $to,
            'type' => 'location',
            'content' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'name' => $name,
                'address' => '',
            ],
        ]);

        return $this->parseServiceResponse($response, 'location');
    }

    /**
     * Parse WhatsApp service response.
     */
    private function parseServiceResponse($response, string $type): array
    {
        if (!$response->successful()) {
            throw new \Exception("WhatsApp service returned HTTP {$response->status()}");
        }

        $data = $response->json();

        if (!($data['success'] ?? false)) {
            throw new \Exception($data['message'] ?? "Failed to send {$type} message");
        }

        return $data;
    }

    /**
     * Normalize phone number.
     */
    private function normalizePhone(string $phone): string
    {
        return PhoneNormalizer::normalize($phone);
    }

    private function setTyping(?WhatsAppSession $session, string $contactJid, bool $composing): void
    {
        if (!$session) {
            return;
        }

        try {
            $serviceUrl = rtrim(config('services.whatsapp.url', 'http://127.0.0.1:3002'), '/');
            $nodeUserId = $session->nodeUserId();
            $state = $composing ? 'composing' : 'paused';

            Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . config('services.whatsapp.api_key'),
                ])
                ->post("{$serviceUrl}/api/whatsapp/send-presence", [
                    'userId' => $nodeUserId,
                    'to' => $contactJid,
                    'state' => $state,
                ]);
        } catch (\Throwable $e) {
            Log::warning("SendWhatsApp presence failed for {$contactJid}: " . $e->getMessage());
        }
    }
}
