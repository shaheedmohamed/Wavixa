<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\MessengerConversation;
use App\Models\MessengerMessage;
use App\Models\MessengerPage;
use App\Services\MessengerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PublicMessengerController extends Controller
{
    protected MessengerService $messenger;

    public function __construct(MessengerService $messenger)
    {
        $this->messenger = $messenger;
    }

    /**
     * Send a Messenger message via API key.
     */
    public function sendMessage(Request $request)
    {
        $data = $request->validate([
            'psid' => 'required|string',
            'message' => 'required|string|max:2000',
            'page_id' => 'required|string',
            'message_type' => 'nullable|string|in:text,image,audio,video,file',
            'media_url' => 'nullable|string|url',
        ]);

        $apiKey = $request->attributes->get('api_key');
        if (!$apiKey instanceof ApiKey) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $page = MessengerPage::where('page_id', $data['page_id'])
            ->where('user_id', $apiKey->user_id)
            ->where('status', 'connected')
            ->first();

        if (!$page) {
            return response()->json(['success' => false, 'message' => 'Messenger page not connected.'], 400);
        }

        $conversation = MessengerConversation::firstOrCreate(
            ['page_id' => $page->id, 'psid' => $data['psid']],
            [
                'user_id' => $apiKey->user_id,
                'contact_id' => null, // Will be resolved on first incoming message if needed.
                'last_message_at' => now(),
                'messaging_window_until' => now()->addHours(24),
            ]
        );

        try {
            $messageType = $data['message_type'] ?? 'text';
            if ($messageType === 'text') {
                $result = $this->messenger->sendMessage($page, $data['psid'], $data['message']);
            } else {
                $result = $this->messenger->sendAttachment($page, $data['psid'], $messageType, $data['media_url'] ?? '');
            }

            $message = MessengerMessage::create([
                'user_id' => $apiKey->user_id,
                'page_id' => $page->id,
                'conversation_id' => $conversation->id,
                'psid' => $data['psid'],
                'direction' => 'outgoing',
                'message_type' => $messageType,
                'message' => $data['message'],
                'media_url' => $data['media_url'] ?? null,
                'status' => 'sent',
                'external_message_id' => $result['message_id'] ?? null,
                'sent_at' => now(),
                'cost' => 1,
            ]);

            $conversation->update(['last_message_at' => now()]);

            $apiKey->increment('requests_count');

            return response()->json([
                'success' => true,
                'message' => 'Message sent.',
                'data' => ['message_id' => $message->id, 'external_message_id' => $message->external_message_id],
            ]);
        } catch (\Throwable $e) {
            Log::error('Public Messenger send failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
