<?php

namespace App\Jobs;

use App\Models\WhatsAppAutoReply;
use App\Models\WhatsAppAutoReplyLog;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppSession;
use App\Services\AIReplyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateAIReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;
    public int $logId;

    public function __construct(int $logId)
    {
        $this->logId = $logId;
    }

    public function handle(AIReplyService $ai): void
    {
        $log = WhatsAppAutoReplyLog::with(['conversation', 'session', 'incomingMessage'])->find($this->logId);

        if (!$log) {
            Log::warning("GenerateAIReplyJob: log {$this->logId} not found");
            return;
        }

        $autoReply = WhatsAppAutoReply::where('session_id', $log->session_id)->first();

        if (!$autoReply || !$autoReply->is_enabled || $autoReply->mode !== 'ai') {
            $log->update([
                'status' => 'blocked',
                'status_reason' => 'AI auto reply is disabled or mode changed',
            ]);
            return;
        }

        $conversation = $log->conversation;
        $incoming = $log->incomingMessage;

        if (!$conversation || !$incoming) {
            $log->update([
                'status' => 'failed',
                'status_reason' => 'Conversation or incoming message missing',
            ]);
            return;
        }

        $log->update(['status' => 'processing']);

        // Daily limit and business hours happen here before the API call
        $autoReply->refreshDailyCounter();

        if ($autoReply->ai_max_daily_replies > 0 && $autoReply->ai_daily_replies_sent >= $autoReply->ai_max_daily_replies) {
            $this->sendFallback($autoReply, $log, $conversation, 'Daily AI reply limit reached');
            return;
        }

        if ($autoReply->ai_business_hours_enabled && !$this->withinBusinessHours($autoReply)) {
            $this->sendFallback($autoReply, $log, $conversation, 'Outside configured business hours');
            return;
        }

        // Human handoff
        if ($conversation->ai_disabled_until && $conversation->ai_disabled_until->isFuture()) {
            $log->update([
                'status' => 'blocked',
                'status_reason' => 'Human handoff active for this conversation',
            ]);
            return;
        }

        // Reply once per conversation
        if ($autoReply->reply_once && Cache::has("ai_reply_once:{$conversation->id}")) {
            $log->update([
                'status' => 'reply_once_skipped',
                'status_reason' => 'AI already replied once to this conversation',
            ]);
            return;
        }

        // Build context from recent conversation messages
        $context = WhatsAppMessage::where('conversation_id', $conversation->id)
            ->whereNotNull('message')
            ->orderBy('created_at', 'desc')
            ->limit($autoReply->ai_context_limit ?: 10)
            ->get(['message', 'direction', 'created_at'])
            ->sortBy('created_at')
            ->values()
            ->toArray();

        $this->setTyping($log->session, $conversation->contact_jid, true);
        try {
            $result = $ai->generate($autoReply, $conversation, $incoming, $context);
            $replyText = $result['reply'];

            $this->queueWhatsAppMessage($autoReply, $log, $conversation, $replyText, $result);

            $autoReply->refreshDailyCounter();
            $autoReply->increment('ai_total_replies_sent');
            $autoReply->increment('ai_daily_replies_sent');
            $autoReply->update(['ai_last_reply_at' => now()]);

            if ($autoReply->reply_once) {
                Cache::put("ai_reply_once:{$conversation->id}", true, now()->addDays(30));
            }

            $log->update([
                'status' => 'queued',
                'status_reason' => 'AI reply generated and queued for WhatsApp',
                'reply_message' => $replyText,
                'ai_tokens_input' => $result['tokens_input'] ?? null,
                'ai_tokens_output' => $result['tokens_output'] ?? null,
                'ai_model' => $result['model'],
            ]);

            Log::info("AI reply generated for conversation {$conversation->id}: " . mb_substr($replyText, 0, 80));
        } catch (Throwable $e) {
            Log::error("AI reply generation failed for conversation {$conversation->id}: " . $e->getMessage());
            $this->setTyping($log->session, $conversation->contact_jid, false);
            $this->sendFallback($autoReply, $log, $conversation, $e->getMessage());
        }
    }

    public function failed(Throwable $exception): void
    {
        $log = WhatsAppAutoReplyLog::find($this->logId);
        if ($log) {
            $log->update([
                'status' => 'failed',
                'status_reason' => 'AI job failed: ' . $exception->getMessage(),
            ]);
        }
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
            Log::warning("AI reply presence failed for {$contactJid}: " . $e->getMessage());
        }
    }

    private function queueWhatsAppMessage(WhatsAppAutoReply $autoReply, WhatsAppAutoReplyLog $log, WhatsAppConversation $conversation, string $replyText, array $result): void
    {
        $session = $log->session;
        if (!$session || $session->status !== 'connected') {
            throw new \RuntimeException('WhatsApp session is not connected');
        }

        $target = $conversation->contact_jid;

        $outgoing = WhatsAppMessage::create([
            'user_id' => $autoReply->user_id,
            'session_id' => $session->id,
            'conversation_id' => $conversation->id,
            'from' => $session->phone_number,
            'to' => $target,
            'message' => $replyText,
            'message_type' => 'text',
            'direction' => 'outgoing',
            'status' => WhatsAppMessage::STATUS_QUEUED,
            'queued_at' => now(),
            'timestamp' => now(),
        ]);

        $log->update(['outgoing_message_id' => $outgoing->id]);

        SendWhatsAppMessageJob::dispatch($outgoing->id, false, true);
    }

    private function sendFallback(WhatsAppAutoReply $autoReply, WhatsAppAutoReplyLog $log, WhatsAppConversation $conversation, string $reason): void
    {
        $fallback = trim($autoReply->ai_fallback_message ?? '');

        if (!$autoReply->ai_fallback_enabled || $fallback === '') {
            $log->update([
                'status' => 'blocked',
                'status_reason' => 'AI blocked: ' . $reason,
            ]);
            return;
        }

        $this->queueWhatsAppMessage($autoReply, $log, $conversation, $fallback, []);

        $log->update([
            'status' => 'queued',
            'status_reason' => 'AI fallback sent: ' . $reason,
            'reply_message' => $fallback,
        ]);
    }

    private function withinBusinessHours(WhatsAppAutoReply $autoReply): bool
    {
        $from = $autoReply->ai_business_hours_from;
        $to = $autoReply->ai_business_hours_to;

        if (!$from || !$to) {
            return true;
        }

        $now = now();
        $current = $now->format('H:i');

        // Time-only comparison. If from < to: within same day. If to < from: overnight window.
        if ($from <= $to) {
            return $current >= $from && $current <= $to;
        }

        return $current >= $from || $current <= $to;
    }
}
