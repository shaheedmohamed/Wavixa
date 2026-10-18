<?php

namespace App\Jobs;

use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Ask the paired phone (via the engine's peer-data on-demand mechanism) for
 * the older messages of one conversation. Anchored at the oldest real
 * WhatsApp message id we already store — conversations without one cannot be
 * backfilled this way.
 */
class SyncConversationHistoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 30;

    public function __construct(private int $conversationId)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $conversation = WhatsAppConversation::with('session')->find($this->conversationId);
        $session = $conversation?->session;
        if (!$conversation || !$session || $session->status !== 'connected') {
            return;
        }

        $anchor = WhatsAppMessage::where('conversation_id', $conversation->id)
            ->whereNotNull('message_id')
            ->where('message_id', 'not like', 'out\_%')
            ->orderBy('sent_at')
            ->orderBy('timestamp')
            ->first(['message_id', 'direction', 'sent_at', 'timestamp']);

        if (!$anchor?->message_id) {
            return;
        }

        $ts = $anchor->sent_at ?? $anchor->timestamp;
        $engineUrl = rtrim(config('services.whatsapp.url', 'http://127.0.0.1:3005'), '/');
        $token = $session->session_token ?: (string) $session->id;

        try {
            Http::timeout(20)
                ->withHeaders(['Authorization' => 'Bearer ' . config('services.whatsapp.api_key')])
                ->post("{$engineUrl}/internal/history/chat", [
                    'sessionId' => $token,
                    'chatJid' => $conversation->contact_jid,
                    'oldestMsgId' => $anchor->message_id,
                    'oldestMsgFromMe' => $anchor->direction === 'outgoing',
                    'oldestMsgTimestampMs' => $ts->getTimestamp() * 1000,
                    'count' => 100,
                ]);
        } catch (\Throwable $e) {
            Log::info("History sync request for conversation {$conversation->id} failed: {$e->getMessage()}");
        }
    }
}
