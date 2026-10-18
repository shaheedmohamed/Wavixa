<?php

namespace App\Modules\WhatsApp\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\User;
use App\Models\WhatsAppSession;
use App\Models\WhatsAppMessage;
use App\Services\SubscriptionService;
use App\Services\WebhookDispatcher;
use App\Services\MetaPixelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WhatsAppController extends Controller
{
    private $whatsappServiceUrl;
    private SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->whatsappServiceUrl = config('services.whatsapp.url', 'http://127.0.0.1:3005') . '/api/whatsapp';
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * HTTP client authenticated with the WhatsApp service key.
     */
    private function service()
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.whatsapp.api_key'),
        ]);
    }

    /**
     * Get the user's latest connected WhatsApp session.
     */
    private function currentSession(): ?WhatsAppSession
    {
        return WhatsAppSession::where('user_id', Auth::id())
            ->where('status', 'connected')
            ->latest('created_at')
            ->first();
    }

    /**
     * Get WhatsApp connection status
     */
    public function getStatus()
    {
        try {
            $userId = Auth::id();

            // The live client may be keyed by user_id (dashboard/legacy) or by
            // session_token (remote pairing). Probe both and prefer the one
            // that is actually alive (connected/pending with a QR or pairing code).
            $session = WhatsAppSession::where('user_id', $userId)
                ->latest('created_at')
                ->first();

            $candidates = array_values(array_unique(array_filter([
                (string) $userId,
                $session?->session_token,
            ])));

            $fallbackData = [
                'status' => 'disconnected',
                'mode' => null,
                'phoneNumber' => $session?->phone_number ?? null,
                'name' => null,
                'qr' => null,
                'pairingCode' => null,
                'connectedAt' => $session?->connected_at,
                'lastActivity' => $session?->last_activity_at,
            ];

            $lastResponse = null;

            foreach ($candidates as $nodeUserId) {
                $response = $this->service()->get("{$this->whatsappServiceUrl}/status/{$nodeUserId}");

                if ($response->successful()) {
                    $data = $response->json();
                    $lastResponse = $data;
                    $status = $data['status'] ?? 'disconnected';

                    // Return the first candidate that is not disconnected.
                    if ($status !== 'disconnected') {
                        return response()->json([
                            'success' => true,
                            'data' => [
                                'status' => $status,
                                'mode' => $data['mode'] ?? null,
                                'phoneNumber' => $data['phoneNumber'] ?? $session?->phone_number ?? null,
                                'name' => $data['name'] ?? null,
                                'qr' => $data['qr'] ?? null,
                                'pairingCode' => $data['pairingCode'] ?? null,
                                'connectedAt' => $session?->connected_at,
                                'lastActivity' => $session?->last_activity_at,
                            ],
                        ]);
                    }
                }
            }

            // If all candidates reported disconnected, surface the last response
            // so the frontend can see the actual service state.
            if ($lastResponse) {
                $fallbackData['status'] = $lastResponse['status'] ?? 'disconnected';
                $fallbackData['mode'] = $lastResponse['mode'] ?? null;
                $fallbackData['qr'] = $lastResponse['qr'] ?? null;
                $fallbackData['pairingCode'] = $lastResponse['pairingCode'] ?? null;
            }

            return response()->json([
                'success' => true,
                'data' => $fallbackData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Initialize WhatsApp connection
     */
    public function connect()
    {
        try {
            $userId = Auth::id();

            $user = Auth::user();
            $session = $this->currentSession();
            $nodeUserId = $session?->nodeUserId() ?? $userId;

            // Check real WhatsApp service status first
            $statusResponse = $this->service()->get("{$this->whatsappServiceUrl}/status/{$nodeUserId}");
            if ($statusResponse->successful()) {
                $statusData = $statusResponse->json();
                if (($statusData['status'] ?? 'disconnected') === 'connected') {
                    // Update database to match real status
                    WhatsAppSession::updateOrCreate(
                        ['user_id' => $userId],
                        [
                            'status' => 'connected',
                            'phone_number' => $statusData['phoneNumber'] ?? null,
                            'connected_at' => now(),
                        ]
                    );

                    MetaPixelService::track(
                        'ConnectWhatsApp',
                        $user,
                        [
                            'content_name' => 'WhatsApp Device Connection',
                            'content_type' => 'device',
                            'phone_number' => $statusData['phoneNumber'] ?? null,
                        ],
                        "whatsapp-connect-{$userId}-" . time(),
                        'https://wavixa.net/devices'
                    );

                    return response()->json([
                        'success' => true,
                        'message' => 'Already connected',
                        'data' => [
                            'phoneNumber' => $statusData['phoneNumber'] ?? null,
                        ],
                    ]);
                }
            }

            // Call WhatsApp service
            $phoneNumber = request()->input('phone_number');
            $payload = [];
            if ($phoneNumber) {
                $payload['phone_number'] = $phoneNumber;
            }

            $response = $this->service()->post("{$this->whatsappServiceUrl}/connect/{$userId}", $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                // Create or update session record
                WhatsAppSession::updateOrCreate(
                    ['user_id' => $userId],
                    [
                        'status' => 'pending',
                        'phone_number' => $phoneNumber,
                        'connected_at' => null,
                    ]
                );

                MetaPixelService::track(
                    'ConnectWhatsApp',
                    $user,
                    [
                        'content_name' => 'WhatsApp Device Connection',
                        'content_type' => 'device',
                    ],
                    "whatsapp-connect-{$userId}-" . time(),
                    'https://wavixa.net/devices'
                );

                return response()->json([
                    'success' => true,
                    'message' => $phoneNumber ? 'Connection initialized. Enter the pairing code on your phone.' : 'Connection initialized. Scan QR code.',
                    'data' => $responseData['data'] ?? $responseData,
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize connection',
            ], 500);
        } catch (\Exception $e) {
            \Log::error('WhatsApp connect error: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error initializing connection: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Disconnect WhatsApp
     */
    public function disconnect()
    {
        try {
            $userId = Auth::id();
            $session = $this->currentSession();
            $nodeUserId = $session?->nodeUserId() ?? $userId;

            // Call WhatsApp service
            $response = $this->service()->post("{$this->whatsappServiceUrl}/disconnect/{$nodeUserId}");
            
            if ($response->successful()) {
                // Update session record
                WhatsAppSession::where('user_id', $userId)->update([
                    'status' => 'disconnected',
                    'connected_at' => null,
                ]);

                WebhookDispatcher::dispatch($userId, 'session.expired', [
                    'reason' => 'manual_disconnect',
                    'expired_at' => now()->toIso8601String(),
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Disconnected successfully',
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to disconnect',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error disconnecting',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send WhatsApp message
     */
    public function sendMessage(Request $request)
    {
        try {
            $request->validate([
                'to' => 'required|string',
                'message' => 'required|string',
            ]);
            
            $user = Auth::user();
            $userId = $user->id;

            // Check if connected in DB
            $session = $this->currentSession();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'WhatsApp not connected',
                ], 400);
            }

            $nodeUserId = $session->nodeUserId();

            // Double-check real WhatsApp service status
            $statusResponse = $this->service()->get("{$this->whatsappServiceUrl}/status/{$nodeUserId}");
            if (!$statusResponse->successful() || ($statusResponse->json()['status'] ?? 'disconnected') !== 'connected') {
                return response()->json([
                    'success' => false,
                    'message' => 'WhatsApp connection is not active. Please reconnect.',
                ], 400);
            }

            // Only queue if the user has an active subscription with remaining messages.
            // The credit is actually consumed inside SendWhatsAppMessageJob once processing
            // starts, and is refunded by the job's failed() hook if the message does not
            // reach a SENT status. This guarantees a SENT message is always deducted once.
            if (!$this->subscriptionService->hasRemainingMessages($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No remaining messages in your subscription. Please upgrade your plan.',
                ], 403);
            }

            // Queue the message for async sending. Direct synchronous calls to
            // the WhatsApp service can exceed the HTTP timeout (up to 60s for
            // typing + WhatsApp response), so we dispatch the same job used by
            // campaigns and return immediately with a queued message.
            $message = WhatsAppMessage::create([
                'user_id' => $userId,
                'session_id' => $session->id,
                'from' => $session->phone_number,
                'to' => $request->to,
                'message' => $request->message,
                'message_type' => 'text',
                'direction' => 'outgoing',
                'status' => WhatsAppMessage::STATUS_QUEUED,
                'queued_at' => now(),
                'timestamp' => now(),
            ]);

            SendWhatsAppMessageJob::dispatch($message->id);

            return response()->json([
                'success' => true,
                'message' => 'تم وضع الرسالة في قائمة الإرسال بنجاح!',
                'data' => ['message_id' => $message->id, 'status' => 'queued'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error sending message',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete WhatsApp session
     */
    public function deleteSession()
    {
        try {
            $userId = Auth::id();
            $session = $this->currentSession();
            $nodeUserId = $session?->nodeUserId() ?? $userId;

            // Call WhatsApp service
            $response = $this->service()->post("{$this->whatsappServiceUrl}/delete-session/{$nodeUserId}");
            
            if ($response->successful()) {
                // Keep the session record but mark it as disconnected so the
                // phone number and history remain visible in the admin panel.
                WhatsAppSession::where('user_id', $userId)->update([
                    'status' => 'disconnected',
                    'connected_at' => null,
                    'last_activity_at' => now(),
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Session disconnected successfully',
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete session',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting session',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get QR code
     */
    public function getQR()
    {
        try {
            $userId = Auth::id();
            $session = $this->currentSession();
            $nodeUserId = $session?->nodeUserId() ?? $userId;

            $response = $this->service()->get("{$this->whatsappServiceUrl}/qr/{$nodeUserId}");
            
            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json(),
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'No QR code available',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting QR code',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Webhook: Handle WhatsApp connected
     */
    public function handleConnected(Request $request)
    {
        try {
            $data = $request->validate([
                'userId' => 'required',
                'phoneNumber' => 'required|string',
                'name' => 'nullable|string',
            ]);

            $session = $this->resolveWebhookSession($data['userId']);

            if (!$session) {
                \Log::warning('WhatsApp connected for unknown session', ['userId' => $data['userId']]);
                return response()->json(['success' => false, 'message' => 'Unknown session'], 404);
            }

            $session->update([
                'status' => 'connected',
                'phone_number' => $data['phoneNumber'],
                'device_name' => $data['name'] ?? $session->device_name,
                'connected_at' => now(),
                'last_activity_at' => now(),
            ]);

            WebhookDispatcher::dispatch($session->user_id, 'session.connected', [
                'phone_number' => $data['phoneNumber'],
                'session_id' => $session->id,
                'connected_at' => now()->toIso8601String(),
            ]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('Error handling WhatsApp connected:', ['error' => $e->getMessage()]);
            return response()->json(['success' => false], 500);
        }
    }

    /**
     * Webhook: Handle WhatsApp disconnected
     */
    public function handleDisconnected(Request $request)
    {
        try {
            $data = $request->validate([
                'userId' => 'required',
                'reason' => 'nullable|string',
            ]);

            $session = $this->resolveWebhookSession($data['userId']);

            if (!$session) {
                \Log::warning('WhatsApp disconnected for unknown session', ['userId' => $data['userId']]);
                return response()->json(['success' => false, 'message' => 'Unknown session'], 404);
            }

            $session->update([
                'status' => 'disconnected',
                'connected_at' => null,
                'last_activity_at' => now(),
            ]);

            WebhookDispatcher::dispatch($session->user_id, 'session.disconnected', [
                'reason' => $data['reason'] ?? null,
                'session_id' => $session->id,
                'disconnected_at' => now()->toIso8601String(),
            ]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('Error handling WhatsApp disconnected:', ['error' => $e->getMessage()]);
            return response()->json(['success' => false], 500);
        }
    }

    /**
     * Webhook: Handle message received
     */
    public function handleMessageReceived(Request $request)
    {
        try {
            $data = $request->validate([
                'userId' => 'required',
                'from' => 'required|string',
                'message' => 'required|array',
                'timestamp' => 'required',
            ]);

            $session = $this->resolveWebhookSession($data['userId']);

            if (!$session) {
                \Log::warning('WhatsApp message received for unknown session', ['userId' => $data['userId']]);
                return response()->json(['success' => false, 'message' => 'Unknown session'], 404);
            }

            WhatsAppMessage::create([
                'user_id' => $session->user_id,
                'session_id' => $session->id,
                'from' => $data['from'],
                'message' => json_encode($data['message']),
                'direction' => 'incoming',
                'timestamp' => $data['timestamp'],
            ]);

            $session->update(['last_activity_at' => now()]);

            WebhookDispatcher::dispatch($session->user_id, 'message.received', [
                'from' => $data['from'],
                'message' => $data['message'],
                'timestamp' => $data['timestamp'],
                'direction' => 'incoming',
            ]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('Error handling message received:', ['error' => $e->getMessage()]);
            return response()->json(['success' => false], 500);
        }
    }

    /**
     * Webhook: Handle message sent
     */
    public function handleMessageSent(Request $request)
    {
        try {
            $data = $request->validate([
                'userId' => 'required',
                'to' => 'required|string',
                'message' => 'nullable|string',
                'messageType' => 'nullable|string',
                'messageId' => 'required|string',
                'timestamp' => 'required',
            ]);

            $session = $this->resolveWebhookSession($data['userId']);

            if (!$session) {
                \Log::warning('WhatsApp message sent for unknown session', ['userId' => $data['userId']]);
                return response()->json(['success' => false, 'message' => 'Unknown session'], 404);
            }

            WhatsAppMessage::updateOrCreate(
                [
                    'user_id' => $session->user_id,
                    'message_id' => $data['messageId'],
                ],
                [
                    'session_id' => $session->id,
                    'to' => $data['to'],
                    'message' => $data['message'] ?? '',
                    'message_type' => $data['messageType'] ?? null,
                    'direction' => 'outgoing',
                    'status' => 'sent',
                    'sent_at' => now(),
                    'timestamp' => $data['timestamp'],
                ]
            );

            $session->update(['last_activity_at' => now()]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('Error handling message sent:', ['error' => $e->getMessage()]);
            return response()->json(['success' => false], 500);
        }
    }

    /**
     * Resolve a WhatsApp session from a webhook userId (token or legacy integer).
     */
    private function resolveWebhookSession(string|int $userId): ?WhatsAppSession
    {
        if (is_numeric($userId)) {
            $session = WhatsAppSession::where('user_id', $userId)
                ->latest('created_at')
                ->first();

            if ($session) {
                return $session;
            }
        }

        return WhatsAppSession::forToken($userId)->first();
    }

    /**
     * Get dashboard statistics
     */
    public function getDashboardStats()
    {
        try {
            $userId = Auth::id();
            $cacheKey = "dashboard_stats:{$userId}";

            $data = Cache::remember($cacheKey, 30, function () use ($userId) {
                $session = WhatsAppSession::where('user_id', $userId)->first();

                // Main stats
                $totalMessages = WhatsAppMessage::where('user_id', $userId)->count();
                $delivered = WhatsAppMessage::where('user_id', $userId)->where('status', 'delivered')->count();
                $read = WhatsAppMessage::where('user_id', $userId)->where('status', 'read')->count();
                $sent = WhatsAppMessage::where('user_id', $userId)->where('status', 'sent')->count();
                $failed = WhatsAppMessage::where('user_id', $userId)->where('status', 'failed')->count();

                // New contacts proxy: unique phone numbers the user interacted with
                $newContacts = WhatsAppMessage::where('user_id', $userId)
                    ->whereDate('created_at', today())
                    ->distinct('to')
                    ->count('to');

                // Performance chart (last 7 days)
                $performanceLabels = [];
                $performanceSent = [];
                $performanceDelivered = [];
                $performanceRead = [];
                $performanceReplied = [];

                for ($i = 6; $i >= 0; $i--) {
                    $date = now()->subDays($i)->startOfDay();
                    $endOfDay = $date->copy()->endOfDay();
                    $performanceLabels[] = $date->format('M d');

                    $performanceSent[] = WhatsAppMessage::where('user_id', $userId)
                        ->where('status', 'sent')
                        ->whereBetween('created_at', [$date, $endOfDay])
                        ->count();

                    $performanceDelivered[] = WhatsAppMessage::where('user_id', $userId)
                        ->where('status', 'delivered')
                        ->whereBetween('created_at', [$date, $endOfDay])
                        ->count();

                    $performanceRead[] = WhatsAppMessage::where('user_id', $userId)
                        ->where('status', 'read')
                        ->whereBetween('created_at', [$date, $endOfDay])
                        ->count();

                    $performanceReplied[] = 0;
                }

                // WhatsApp numbers (sessions)
                $numbers = WhatsAppSession::where('user_id', $userId)
                    ->select(['id', 'phone_number', 'status', 'device_name'])
                    ->get()
                    ->map(function ($item) use ($userId) {
                        $sent = WhatsAppMessage::where('user_id', $userId)
                            ->where('session_id', $item->id)
                            ->where('status', 'sent')
                            ->count();
                        $delivered = WhatsAppMessage::where('user_id', $userId)
                            ->where('session_id', $item->id)
                            ->where('status', 'delivered')
                            ->count();
                        $read = WhatsAppMessage::where('user_id', $userId)
                            ->where('session_id', $item->id)
                            ->where('status', 'read')
                            ->count();

                        return [
                            'id' => $item->id,
                            'number' => $item->phone_number,
                            'status' => $item->status,
                            'label' => $item->device_name,
                            'sent' => $sent,
                            'delivered' => $delivered,
                            'read' => $read,
                        ];
                    });

                // Messages by type
                $messagesByType = WhatsAppMessage::where('user_id', $userId)
                    ->selectRaw('COALESCE(message_type, "text") as type, COUNT(*) as count')
                    ->groupBy('type')
                    ->pluck('count', 'type')
                    ->toArray();

                $typeLabels = ['text', 'image', 'video', 'document', 'audio', 'template', 'other'];
                $messagesByTypeFinal = [];
                foreach ($typeLabels as $label) {
                    if ($label === 'other') {
                        $otherCount = 0;
                        foreach ($messagesByType as $type => $count) {
                            if (!in_array($type, ['text', 'image', 'video', 'document', 'audio', 'template'])) {
                                $otherCount += $count;
                            }
                        }
                        $messagesByTypeFinal[] = ['label' => 'Other', 'value' => $otherCount];
                    } else {
                        $messagesByTypeFinal[] = ['label' => ucfirst($label), 'value' => (int) ($messagesByType[$label] ?? 0)];
                    }
                }

                // Recent activity (messages + activity logs)
                $recentMessages = WhatsAppMessage::where('user_id', $userId)
                    ->select(['id', 'to', 'from', 'message', 'status', 'created_at'])
                    ->latest()
                    ->limit(5)
                    ->get()
                    ->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'type' => 'message',
                            'title' => $item->status === 'failed' ? 'فشل إرسال رسالة' : 'تم إرسال رسالة',
                            'description' => $item->to ?: 'غير معروف',
                            'created_at' => $item->created_at,
                        ];
                    });

                $recentActivity = $recentMessages;

                // Heatmap data (last 6 weeks, grouped by day of week and hour)
                $heatmap = [];
                for ($day = 0; $day < 7; $day++) {
                    for ($hour = 0; $hour < 24; $hour += 4) {
                        $count = WhatsAppMessage::where('user_id', $userId)
                            ->whereRaw('DAYOFWEEK(created_at) = ?', [$day + 1])
                            ->whereRaw('HOUR(created_at) BETWEEN ? AND ?', [$hour, $hour + 3])
                            ->count();
                        $heatmap[] = ['day' => $day, 'hour' => $hour, 'value' => $count];
                    }
                }

                // Message counts
                $messageStats = WhatsAppMessage::where('user_id', $userId)
                    ->selectRaw("
                        SUM(CASE WHEN DATE(created_at) = ? THEN 1 ELSE 0 END) as today_count,
                        SUM(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN 1 ELSE 0 END) as month_count
                    ", [today()->toDateString(), now()->month, now()->year])
                    ->first();

                // Queue status
                $queueAggregates = WhatsAppMessage::where('user_id', $userId)
                    ->selectRaw('status, COUNT(*) as count')
                    ->whereIn('status', ['queued', 'processing', 'sent', 'failed', 'pending', 'retrying', 'delivered', 'read'])
                    ->groupBy('status')
                    ->pluck('count', 'status');

                $queueStatus = [
                    'queued' => (int) ($queueAggregates['queued'] ?? 0),
                    'processing' => (int) ($queueAggregates['processing'] ?? 0),
                    'sent' => (int) ($queueAggregates['sent'] ?? 0),
                    'delivered' => (int) ($queueAggregates['delivered'] ?? 0),
                    'read' => (int) ($queueAggregates['read'] ?? 0),
                    'failed' => (int) ($queueAggregates['failed'] ?? 0),
                    'pending' => (int) ($queueAggregates['pending'] ?? 0),
                    'retrying' => (int) ($queueAggregates['retrying'] ?? 0),
                ];

                $pendingJobs = \Illuminate\Support\Facades\DB::table('jobs')->count();
                $failedJobs = \Illuminate\Support\Facades\DB::table('failed_jobs')->count();

                return [
                    'whatsappStatus' => $session?->status ?? 'disconnected',
                    'phoneNumber' => $session?->phone_number,
                    'connectedAt' => $session?->connected_at,
                    'lastActivity' => $session?->last_activity_at,
                    'totalMessages' => $totalMessages,
                    'delivered' => $delivered,
                    'read' => $read,
                    'replied' => 0,
                    'newContacts' => $newContacts,
                    'todayMessages' => (int) $messageStats->today_count,
                    'monthMessages' => (int) $messageStats->month_count,
                    'performance' => [
                        'labels' => $performanceLabels,
                        'sent' => $performanceSent,
                        'delivered' => $performanceDelivered,
                        'read' => $performanceRead,
                        'replied' => $performanceReplied,
                    ],
                    'numbers' => $numbers,
                    'messagesByType' => $messagesByTypeFinal,
                    'topCampaigns' => [],
                    'recentActivity' => $recentActivity,
                    'heatmap' => $heatmap,
                    'recentMessages' => $recentMessages,
                    'queueStatus' => $queueStatus,
                    'pendingJobs' => $pendingJobs,
                    'failedJobs' => $failedJobs,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting dashboard stats',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List the authenticated user's WhatsApp sessions.
     */
    public function listSessions()
    {
        $userId = Auth::id();
        $sessions = WhatsAppSession::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        // Sync with the live WhatsApp service if the local list is empty or has
        // no connected sessions. This can happen when the service has a live
        // session restored from disk but the database row has not been created.
        if ($sessions->isEmpty() || $sessions->where('status', 'connected')->isEmpty()) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . config('services.whatsapp.api_key'),
                    ])
                    ->get("{$this->whatsappServiceUrl}/status/{$userId}");

                if ($response->successful()) {
                    $data = $response->json();
                    if (($data['status'] ?? 'disconnected') === 'connected' && !empty($data['phoneNumber'])) {
                        $session = WhatsAppSession::updateOrCreate(
                            ['user_id' => $userId, 'phone_number' => $data['phoneNumber']],
                            [
                                'status' => 'connected',
                                'device_name' => $data['name'] ?? 'WhatsApp',
                                'connected_at' => now(),
                                'last_activity_at' => now(),
                            ]
                        );
                        $sessions = collect([$session]);
                    }
                }
            } catch (\Throwable $e) {
                // Live service unavailable; fall back to the local list.
            }
        }

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }
}
