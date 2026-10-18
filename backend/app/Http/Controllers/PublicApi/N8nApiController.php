<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\ApiKey;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppSession;
use App\Services\PhoneNormalizer;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class N8nApiController extends Controller
{
    private SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    private const PERMISSIONS = [
        'send_messages' => 'send_messages',
        'read_message_status' => 'read_message_status',
        'read_sessions' => 'read_sessions',
        'read_balance' => 'read_balance',
        'use_webhooks' => 'use_webhooks',
    ];

    /**
     * Check if API key has a permission
     */
    private function hasPermission(Request $request, string $permission): bool
    {
        $apiKey = $request->attributes->get('api_key');
        if (!$apiKey) {
            return false;
        }

        $permissions = $apiKey->permissions ?? [];
        if (empty($permissions)) {
            // Default: full access when no permissions set (backward compatible)
            return true;
        }

        return in_array($permission, $permissions);
    }

    private function normalizePhone(string $phone): string
    {
        return PhoneNormalizer::normalize($phone);
    }

    private function resolveSession(Request $request)
    {
        $user = $request->attributes->get('api_key_user');
        $apiKey = $request->attributes->get('api_key');

        $session = null;
        if ($request->filled('session_id')) {
            $session = WhatsAppSession::where('id', $request->input('session_id'))
                ->where('user_id', $user->id)
                ->where('status', 'connected')
                ->first();
        } elseif ($apiKey?->whatsapp_session_id) {
            $session = WhatsAppSession::where('id', $apiKey->whatsapp_session_id)
                ->where('user_id', $user->id)
                ->where('status', 'connected')
                ->first();
        }

        if (!$session) {
            $session = WhatsAppSession::where('user_id', $user->id)
                ->where('status', 'connected')
                ->first();
        }

        return $session;
    }

    private function queueMessage(Request $request, string $to, string $type, string $content): WhatsAppMessage
    {
        $user = $request->attributes->get('api_key_user');
        $apiKey = $request->attributes->get('api_key');
        $session = $this->resolveSession($request);

        $message = WhatsAppMessage::create([
            'user_id' => $user->id,
            'api_key_id' => $apiKey?->id,
            'session_id' => $session?->id,
            'from' => $session?->phone_number,
            'to' => $this->normalizePhone($to),
            'message' => $content,
            'message_type' => $type,
            'direction' => 'outgoing',
            'status' => WhatsAppMessage::STATUS_QUEUED,
            'queued_at' => now(),
            'timestamp' => now(),
        ]);

        SendWhatsAppMessageJob::dispatch($message->id);

        return $message;
    }

    /**
     * Send a text message
     */
    public function sendMessage(Request $request)
    {
        if (!$this->hasPermission($request, self::PERMISSIONS['send_messages'])) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $request->validate([
            'to' => 'required|string|max:255',
            'message' => 'required|string',
            'session_id' => 'nullable|integer',
        ]);

        $user = $request->attributes->get('api_key_user');

        if (!$this->subscriptionService->hasRemainingMessages($user)) {
            return response()->json(['success' => false, 'message' => 'Insufficient message credits'], 403);
        }

        $session = $this->resolveSession($request);
        if (!$session) {
            return response()->json(['success' => false, 'message' => 'Session Not Connected'], 400);
        }

        $message = $this->queueMessage($request, $request->input('to'), 'text', $request->input('message'));

        return response()->json([
            'success' => true,
            'message' => 'Message queued',
            'data' => [
                'message_id' => $message->id,
                'status' => $message->status,
                'queued_at' => $message->queued_at,
            ],
        ]);
    }

    /**
     * Send an OTP
     */
    public function sendOtp(Request $request)
    {
        if (!$this->hasPermission($request, self::PERMISSIONS['send_messages'])) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $request->validate([
            'to' => 'required|string|max:255',
            'code' => 'required|string|max:255',
            'session_id' => 'nullable|integer',
        ]);

        $user = $request->attributes->get('api_key_user');

        if (!$this->subscriptionService->hasRemainingMessages($user)) {
            return response()->json(['success' => false, 'message' => 'Insufficient message credits'], 403);
        }

        $session = $this->resolveSession($request);
        if (!$session) {
            return response()->json(['success' => false, 'message' => 'Session Not Connected'], 400);
        }

        $text = "كود التحقق الخاص بك في Wavixa هو: {$request->input('code')}\nلا تشارك هذا الكود مع أحد.";
        $message = $this->queueMessage($request, $request->input('to'), 'text', $text);

        return response()->json([
            'success' => true,
            'message' => 'OTP queued',
            'data' => [
                'message_id' => $message->id,
                'status' => $message->status,
            ],
        ]);
    }

    /**
     * Send an image
     */
    public function sendImage(Request $request)
    {
        if (!$this->hasPermission($request, self::PERMISSIONS['send_messages'])) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $request->validate([
            'to' => 'required|string|max:255',
            'content' => 'required|string',
            'caption' => 'nullable|string',
            'session_id' => 'nullable|integer',
        ]);

        $user = $request->attributes->get('api_key_user');

        if (!$this->subscriptionService->hasRemainingMessages($user)) {
            return response()->json(['success' => false, 'message' => 'Insufficient message credits'], 403);
        }

        $session = $this->resolveSession($request);
        if (!$session) {
            return response()->json(['success' => false, 'message' => 'Session Not Connected'], 400);
        }

        $message = $this->queueMessage($request, $request->input('to'), 'image', $request->input('caption', ''));
        $message->update(['message' => $request->input('content')]);

        return response()->json([
            'success' => true,
            'message' => 'Image queued',
            'data' => [
                'message_id' => $message->id,
                'status' => $message->status,
            ],
        ]);
    }

    /**
     * Send a file/document
     */
    public function sendFile(Request $request)
    {
        if (!$this->hasPermission($request, self::PERMISSIONS['send_messages'])) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $request->validate([
            'to' => 'required|string|max:255',
            'content' => 'required|string',
            'file_name' => 'nullable|string|max:255',
            'caption' => 'nullable|string',
            'session_id' => 'nullable|integer',
        ]);

        $user = $request->attributes->get('api_key_user');

        if (!$this->subscriptionService->hasRemainingMessages($user)) {
            return response()->json(['success' => false, 'message' => 'Insufficient message credits'], 403);
        }

        $session = $this->resolveSession($request);
        if (!$session) {
            return response()->json(['success' => false, 'message' => 'Session Not Connected'], 400);
        }

        $payload = [
            'content' => $request->input('content'),
            'file_name' => $request->input('file_name', 'document'),
            'caption' => $request->input('caption', ''),
        ];

        $message = $this->queueMessage($request, $request->input('to'), 'document', json_encode($payload));

        return response()->json([
            'success' => true,
            'message' => 'File queued',
            'data' => [
                'message_id' => $message->id,
                'status' => $message->status,
            ],
        ]);
    }

    /**
     * Get message status
     */
    public function messageStatus(Request $request, $id)
    {
        if (!$this->hasPermission($request, self::PERMISSIONS['read_message_status'])) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $user = $request->attributes->get('api_key_user');
        $message = WhatsAppMessage::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$message) {
            return response()->json(['success' => false, 'message' => 'Message not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $message->id,
                'to' => $message->to,
                'message_type' => $message->message_type,
                'status' => $message->status,
                'attempts' => $message->attempts,
                'error_message' => $message->error_message,
                'sent_at' => $message->sent_at,
                'delivered_at' => $message->delivered_at,
                'read_at' => $message->read_at,
                'failed_at' => $message->failed_at,
            ],
        ]);
    }

    /**
     * Get WhatsApp session status
     */
    public function sessionStatus(Request $request)
    {
        if (!$this->hasPermission($request, self::PERMISSIONS['read_sessions'])) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $user = $request->attributes->get('api_key_user');

        $session = WhatsAppSession::where('user_id', $user->id)
            ->where('status', 'connected')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $session ? 'connected' : 'disconnected',
                'session' => $session ? [
                    'id' => $session->id,
                    'phone_number' => $session->phone_number,
                    'device_name' => $session->device_name,
                    'connected_at' => $session->connected_at,
                    'last_activity_at' => $session->last_activity_at,
                ] : null,
            ],
        ]);
    }

    /**
     * Get message balance
     */
    public function balance(Request $request)
    {
        if (!$this->hasPermission($request, self::PERMISSIONS['read_balance'])) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $user = $request->attributes->get('api_key_user');

        return response()->json([
            'success' => true,
            'data' => [
                'messages_balance' => (int) $user->messages_balance,
                'has_active_subscription' => $this->subscriptionService->hasRemainingMessages($user),
            ],
        ]);
    }

    /**
     * Get account data
     */
    public function account(Request $request)
    {
        $user = $request->attributes->get('api_key_user');
        $apiKey = $request->attributes->get('api_key');

        $permissions = $apiKey->permissions ?? [];
        if (empty($permissions)) {
            $permissions = array_values(self::PERMISSIONS);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'messages_balance' => (int) $user->messages_balance,
                'permissions' => $permissions,
                'subscription_plan' => $user->subscription_plan,
                'subscription_status' => $user->subscription_status,
            ],
        ]);
    }

    /**
     * List available sessions
     */
    public function sessions(Request $request)
    {
        if (!$this->hasPermission($request, self::PERMISSIONS['read_sessions'])) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $user = $request->attributes->get('api_key_user');

        $sessions = WhatsAppSession::where('user_id', $user->id)
            ->orderBy('last_activity_at', 'desc')
            ->get(['id', 'phone_number', 'device_name', 'status', 'connected_at', 'last_activity_at']);

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }
}
