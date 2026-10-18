<?php

namespace App\Modules\Telegram\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TelegramMessage;
use App\Models\TelegramSession;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TelegramController extends Controller
{
    private string $serviceUrl;
    private SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->serviceUrl = rtrim(config('services.telegram.url', env('TELEGRAM_SERVICE_URL', 'http://127.0.0.1:3003')), '/') . '/api/telegram';
        $this->subscriptionService = $subscriptionService;
    }

    private function service()
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.telegram.api_key', 'wavixa-telegram-service-secret-key-2026'),
            'Accept' => 'application/json',
        ]);
    }

    /**
     * Get Telegram connection status and QR code
     */
    public function getStatus()
    {
        try {
            $userId = Auth::id();
            $session = TelegramSession::where('user_id', $userId)->first();

            $response = $this->service()->timeout(8)->get("{$this->serviceUrl}/status/{$userId}");

            if ($response->successful()) {
                $data = $response->json('data') ?? [];
                $status = $data['status'] ?? 'disconnected';

                if ($status === 'connected' && (!empty($data['phoneNumber']) || !empty($data['name']))) {
                    $session = TelegramSession::updateOrCreate(
                        ['user_id' => $userId],
                        [
                            'phone_number' => $data['phoneNumber'] ?? $session?->phone_number,
                            'first_name' => $data['name'] ?? $session?->first_name,
                            'bot_username' => $data['username'] ?? $session?->bot_username,
                            'bot_id' => $data['telegramId'] ?? $session?->bot_id,
                            'session_token' => $session?->session_token ?? Str::random(40),
                            'status' => 'connected',
                            'connected_at' => $session?->connected_at ?? now(),
                            'last_activity_at' => now(),
                        ]
                    );
                } elseif ($status === 'disconnected' && $session && $session->status === 'connected') {
                    $session->update(['status' => 'disconnected']);
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'status' => $status,
                        'phoneNumber' => $data['phoneNumber'] ?? $session?->phone_number,
                        'name' => $data['name'] ?? $session?->first_name,
                        'username' => $data['username'] ?? $session?->bot_username,
                        'qr' => $data['qr'] ?? null,
                        'connectedAt' => $session?->connected_at,
                        'lastActivity' => $session?->last_activity_at,
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => $session?->status ?? 'disconnected',
                    'phoneNumber' => $session?->phone_number,
                    'name' => $session?->first_name,
                    'username' => $session?->bot_username,
                    'qr' => null,
                    'connectedAt' => $session?->connected_at,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching Telegram status: ' . 'An error occurred',
            ], 500);
        }
    }

    /**
     * Start Telegram QR Code connection
     */
    public function connect(Request $request)
    {
        try {
            $userId = Auth::id();
            $force = $request->query('force') === 'true' ? '?force=true' : '';

            $response = $this->service()->timeout(15)->post("{$this->serviceUrl}/connect/{$userId}{$force}");

            if ($response->successful()) {
                $data = $response->json('data') ?? [];

                return response()->json([
                    'success' => true,
                    'data' => [
                        'status' => $data['status'] ?? 'pairing',
                        'qr' => $data['qr'] ?? null,
                        'phoneNumber' => $data['phoneNumber'] ?? null,
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize Telegram QR connection: ' . $response->body(),
            ], 500);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Telegram connection error: ' . 'An error occurred',
            ], 500);
        }
    }

    /**
     * Disconnect Telegram account
     */
    public function disconnect()
    {
        try {
            $userId = Auth::id();
            $this->service()->timeout(10)->post("{$this->serviceUrl}/disconnect/{$userId}");

            TelegramSession::where('user_id', $userId)->update([
                'status' => 'disconnected',
                'connected_at' => null,
                'last_activity_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم فصل حساب تيليجرام بنجاح.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error disconnecting Telegram: ' . 'An error occurred',
            ], 500);
        }
    }

    /**
     * Delete Telegram session
     */
    public function deleteSession($id = null)
    {
        try {
            $userId = Auth::id();
            $this->service()->timeout(10)->post("{$this->serviceUrl}/disconnect/{$userId}");

            $query = TelegramSession::where('user_id', $userId);
            if ($id) {
                $query->where('id', $id);
            }
            $query->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف جلسة Telegram بنجاح.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting session: ' . 'An error occurred',
            ], 500);
        }
    }

    /**
     * List user Telegram sessions
     */
    public function getSessions()
    {
        $userId = Auth::id();

        $sessions = TelegramSession::where('user_id', $userId)
            ->withCount(['messages as total_sent' => function ($q) {
                $q->where('direction', 'outgoing')->where('status', 'sent');
            }])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    /**
     * Send message from user's Telegram account
     */
    public function sendMessage(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'to' => 'required|string',
            'message' => 'nullable|string',
            'type' => 'nullable|string|in:text,photo,document',
            'media_url' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
        ]);

        $session = TelegramSession::where('user_id', $user->id)
            ->where('status', 'connected')
            ->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'حساب تيليجرام غير متصل. يرجى مسح رمز الـ QR لربط رقمك أولاً.',
            ], 422);
        }

        if (!$this->subscriptionService->hasRemainingMessages($user)) {
            return response()->json([
                'success' => false,
                'message' => 'رصيد رسائلك غير كافٍ. يرجى شحن الرصيد للمتابعة.',
            ], 402);
        }

        $type = $data['type'] ?? 'text';
        $to = trim($data['to']);
        $text = $data['message'] ?? '';
        $mediaUrl = $data['media_url'] ?? null;

        if ($type === 'text' && empty($text)) {
            return response()->json(['success' => false, 'message' => 'نص الرسالة مطلوب.'], 422);
        }

        // Send via telegram-service microservice
        $sendPayload = [
            'userId' => (string) $user->id,
            'to' => $to,
            'message' => $text,
            'type' => $type,
            'mediaUrl' => $mediaUrl,
        ];

        try {
            $serviceRes = $this->service()->timeout(30)->post("{$this->serviceUrl}/send-message", $sendPayload);

            if ($serviceRes->successful()) {
                $resData = $serviceRes->json('data') ?? [];
                $msgId = $resData['messageId'] ?? null;

                $msgRecord = TelegramMessage::create([
                    'user_id' => $user->id,
                    'telegram_session_id' => $session->id,
                    'chat_id' => $to,
                    'telegram_message_id' => $msgId,
                    'direction' => 'outgoing',
                    'type' => $type,
                    'message' => $text,
                    'media_url' => $mediaUrl,
                    'status' => 'sent',
                    'sent_at' => now(),
                    'cost' => 1,
                ]);

                $session->increment('messages_sent_count');
                $session->update(['last_activity_at' => now()]);
                $this->subscriptionService->consumeMessage($user);

                return response()->json([
                    'success' => true,
                    'message' => 'تم إرسال رسالة تيليجرام بنجاح من حسابك!',
                    'data' => $msgRecord,
                ]);
            }

            $errMsg = $serviceRes->json('message') ?? 'فشل إرسال الرسالة عبر تيليجرام';

            TelegramMessage::create([
                'user_id' => $user->id,
                'telegram_session_id' => $session->id,
                'chat_id' => $to,
                'direction' => 'outgoing',
                'type' => $type,
                'message' => $text,
                'media_url' => $mediaUrl,
                'status' => 'failed',
                'error_message' => $errMsg,
                'cost' => 1,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل الإرسال: ' . $errMsg,
            ], 400);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في خادم الإرسال: ' . 'An error occurred',
            ], 500);
        }
    }

    /**
     * Upload media file for Telegram.
     *
     * Files are validated by real MIME type and stored with an extension derived
     * from that MIME type, never from the client-supplied filename.
     */
    public function uploadMedia(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:20480|mimes:jpg,jpeg,png,gif,webp,mp4,mov,mp3,ogg,wav,webm,m4a,pdf,txt,zip,rar,7z,doc,docx,xls,xlsx',
        ]);

        $file = $request->file('file');
        $path = $file->store('telegram_media', 'public');
        $url = Storage::url($path);

        return response()->json([
            'success' => true,
            'url' => $url,
            'filename' => $file->getClientOriginalName(),
        ]);
    }

    /**
     * Dashboard Stats
     */
    public function getDashboardStats()
    {
        $userId = Auth::id();

        $totalSessions = TelegramSession::where('user_id', $userId)->count();
        $activeSessions = TelegramSession::where('user_id', $userId)->where('status', 'connected')->count();
        $totalSent = TelegramMessage::where('user_id', $userId)->where('direction', 'outgoing')->where('status', 'sent')->count();
        $todaySent = TelegramMessage::where('user_id', $userId)->where('direction', 'outgoing')->where('status', 'sent')->whereDate('sent_at', now()->toDateString())->count();
        $totalReceived = TelegramMessage::where('user_id', $userId)->where('direction', 'incoming')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_devices' => $totalSessions,
                'active_devices' => $activeSessions,
                'messages_sent' => $totalSent,
                'today_sent' => $todaySent,
                'messages_received' => $totalReceived,
            ],
        ]);
    }
}
