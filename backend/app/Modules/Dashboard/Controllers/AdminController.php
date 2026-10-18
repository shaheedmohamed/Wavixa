<?php

namespace App\Modules\Dashboard\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AdminNotification;
use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\ApiKey;
use App\Models\OtpCode;
use App\Models\ApiLog;
use App\Models\AuditLog;
use App\Models\FraudAlert;
use App\Models\LoginHistory;
use App\Models\Plan;
use App\Models\RealTimeEvent;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\Webhook;
use App\Models\WebhookLog;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppSession;
use App\Modules\MetaTest\Models\MetaTestMessage;
use App\Models\TelegramSession;
use App\Models\TelegramMessage;
use App\Models\TelegramCampaign;
use App\Jobs\SendWhatsAppMessageJob;
use App\Services\PhoneNormalizer;
use App\Services\ActivityLogger;
use App\Services\RealTimeEventLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AdminController extends Controller
{
    /**
     * Admin dashboard overview statistics.
     */
    public function overview(Request $request)
    {
        try {
            $data = Cache::remember('admin:overview', 30, function () {
                $totalMessages = WhatsAppMessage::count();
                $sentMessages = WhatsAppMessage::where('status', 'sent')->count();
                $failedMessages = WhatsAppMessage::where('status', 'failed')->count();

                $diskTotal = disk_total_space(base_path());
                $diskFree = disk_free_space(base_path());
                $diskUsed = $diskTotal - $diskFree;

                $stats = [
                    'total_users' => User::count(),
                    'active_users' => User::whereHas('activeSubscription')->count(),
                    'users_online' => User::where('last_activity_at', '>=', now()->subMinutes(15))->count(),
                    'new_users_today' => User::whereDate('created_at', today())->count(),
                    'new_users_this_week' => User::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                    'new_users_this_month' => User::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
                    'total_messages' => $totalMessages,
                    'messages_today' => WhatsAppMessage::whereDate('created_at', today())->count(),
                    'messages_this_week' => WhatsAppMessage::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                    'messages_this_month' => WhatsAppMessage::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
                    'total_sessions' => WhatsAppSession::count(),
                    'active_sessions' => WhatsAppSession::where('status', 'connected')->count(),
                    'total_api_keys' => ApiKey::count(),
                    'total_subscriptions' => Subscription::count(),
                    'active_subscriptions' => Subscription::active()->count(),
                    'subscriptions_today' => Subscription::whereDate('created_at', today())->count(),
                    'subscriptions_this_month' => Subscription::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
                    'revenue_today' => Transaction::where('status', 'completed')->whereDate('created_at', today())->sum('amount'),
                    'revenue_this_week' => Transaction::where('status', 'completed')->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->sum('amount'),
                    'revenue_this_month' => Transaction::where('status', 'completed')->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('amount'),
                    'revenue_this_year' => Transaction::where('status', 'completed')->whereYear('created_at', now()->year)->sum('amount'),
                    'total_revenue' => Transaction::where('status', 'completed')->sum('amount'),
                    'message_success_rate' => $totalMessages > 0 ? round(($sentMessages / $totalMessages) * 100, 2) : 0,
                    'message_failure_rate' => $totalMessages > 0 ? round(($failedMessages / $totalMessages) * 100, 2) : 0,
                    'pending_jobs' => DB::table('jobs')->count(),
                    'failed_jobs' => DB::table('failed_jobs')->count(),
                    'total_webhooks' => Webhook::count(),
                    'active_webhooks' => Webhook::where('is_active', true)->count(),
                    'unresolved_fraud_alerts' => FraudAlert::where('is_resolved', false)->count(),
                    'server_usage' => [
                        'disk_total' => $diskTotal,
                        'disk_used' => $diskUsed,
                        'disk_free' => $diskFree,
                        'disk_usage_percent' => $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 2) : 0,
                        'memory_usage' => memory_get_usage(true),
                        'memory_peak' => memory_get_peak_usage(true),
                        'memory_limit' => ini_get('memory_limit'),
                        'cpu_load' => function_exists('sys_getloadavg') ? sys_getloadavg() : [0, 0, 0],
                    ],
                ];

                $recentActivity = ActivityLog::with('admin')
                    ->latest()
                    ->limit(10)
                    ->get();

                $recentEvents = RealTimeEvent::with('user')
                    ->latest()
                    ->limit(10)
                    ->get();

                $recentUsers = User::latest()
                    ->limit(5)
                    ->get(['id', 'name', 'email', 'created_at', 'last_activity_at']);

                $recentSubscriptions = Subscription::with(['user:id,name,email', 'plan:id,name,price'])
                    ->latest()
                    ->limit(5)
                    ->get();

                $recentMessages = WhatsAppMessage::with(['user:id,name,email'])
                    ->latest()
                    ->limit(5)
                    ->get();

                $dailyStats = $this->getDailyStats();
                $weeklyStats = $this->getWeeklyStats();

                return [
                    'stats' => $stats,
                    'recent_activity' => $recentActivity,
                    'recent_events' => $recentEvents,
                    'recent_users' => $recentUsers,
                    'recent_subscriptions' => $recentSubscriptions,
                    'recent_messages' => $recentMessages,
                    'daily_stats' => $dailyStats,
                    'weekly_stats' => $weeklyStats,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading overview: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get daily statistics for charts (last 7 days).
     */
    private function getDailyStats()
    {
        $days = [];
        $users = [];
        $messages = [];
        $revenue = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $days[] = $date->format('Y-m-d');
            
            $users[] = User::whereDate('created_at', $date)->count();
            $messages[] = WhatsAppMessage::whereDate('created_at', $date)->count();
            $revenue[] = (float) Transaction::where('status', 'completed')
                ->whereDate('created_at', $date)
                ->sum('amount');
        }

        return [
            'labels' => $days,
            'users' => $users,
            'messages' => $messages,
            'revenue' => $revenue,
        ];
    }

    /**
     * Get weekly statistics for charts (last 4 weeks).
     */
    private function getWeeklyStats()
    {
        $weeks = [];
        $users = [];
        $messages = [];
        $revenue = [];

        for ($i = 3; $i >= 0; $i--) {
            $startOfWeek = now()->subWeeks($i)->startOfWeek();
            $endOfWeek = now()->subWeeks($i)->endOfWeek();
            
            $weeks[] = $startOfWeek->format('M d') . ' - ' . $endOfWeek->format('M d');
            
            $users[] = User::whereBetween('created_at', [$startOfWeek, $endOfWeek])->count();
            $messages[] = WhatsAppMessage::whereBetween('created_at', [$startOfWeek, $endOfWeek])->count();
            $revenue[] = (float) Transaction::where('status', 'completed')
                ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
                ->sum('amount');
        }

        return [
            'labels' => $weeks,
            'users' => $users,
            'messages' => $messages,
            'revenue' => $revenue,
        ];
    }

    /**
     * List all users with search and pagination.
     */
    public function users(Request $request)
    {
        try {
            $query = User::withCount(['whatsappSessions', 'apiKeys', 'subscriptions', 'messages'])
                ->with(['activeSubscription.plan']);

            // Search
            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%")
                        ->orWhere('country', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            }

            // Filter by admin status
            if ($request->filled('is_admin')) {
                $query->where('is_admin', $request->input('is_admin'));
            }

            // Filter by status
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            // Filter by subscription status
            if ($request->filled('has_subscription')) {
                if ($request->input('has_subscription') == '1') {
                    $query->whereHas('activeSubscription');
                } else {
                    $query->whereDoesntHave('activeSubscription');
                }
            }

            // Sorting
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            
            $allowedSortFields = ['created_at', 'name', 'email', 'last_activity_at', 'messages_balance'];
            if (in_array($sortBy, $allowedSortFields)) {
                $query->orderBy($sortBy, $sortOrder);
            } else {
                $query->orderBy('created_at', 'desc');
            }

            $users = $query->paginate($request->input('per_page', 15));

            $users->getCollection()->each(function (User $user) {
                $this->enrichUserPhoneVerification($user);
            });

            return response()->json([
                'success' => true,
                'data' => $users,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading users: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a single user with details.
     */
    public function showUser(int $id)
    {
        try {
            $user = User::withCount(['whatsappSessions', 'apiKeys', 'subscriptions', 'messages'])
                ->with([
                    'activeSubscription.plan',
                    'subscriptions' => function ($query) {
                        $query->latest()->limit(5);
                    },
                    'subscriptions.plan',
                    'transactions' => function ($query) {
                        $query->latest()->limit(10);
                    },
                    'loginHistories' => function ($query) {
                        $query->latest()->limit(5);
                    },
                    'devices' => function ($query) {
                        $query->latest()->limit(5);
                    },
                    'whatsappSessions' => function ($query) {
                        $query->latest()->limit(5);
                    },
                    'apiKeys' => function ($query) {
                        $query->latest()->limit(5);
                    },
                ])
                ->findOrFail($id);

            // Use real data from active subscription and latest login history
            $latestLogin = $user->loginHistories->first();
            if ($latestLogin) {
                $user->last_login_at = $latestLogin->login_at ?? $user->last_login_at;
                $user->last_ip = $latestLogin->ip_address ?? $user->last_ip;
                $user->last_device = $latestLogin->device_name ?? $user->last_device;
                $user->last_browser = $latestLogin->browser ?? $user->last_browser;
                $user->last_os = $latestLogin->os ?? $user->last_os;
            }

            $user->messages_balance = $user->activeSubscription?->messages_remaining
                ?? $user->messages_balance
                ?? 0;

            // Additional stats
            $stats = [
                'total_messages_sent' => WhatsAppMessage::where('user_id', $id)->whereIn('status', ['sent', 'delivered', 'read'])->count(),
                'total_messages_failed' => WhatsAppMessage::where('user_id', $id)->where('status', 'failed')->count(),
                'total_spent' => Transaction::where('user_id', $id)->where('status', 'completed')->sum('amount'),
                'active_sessions_count' => WhatsAppSession::where('user_id', $id)->where('status', 'connected')->count(),
                'total_api_calls' => ApiLog::whereHas('apiKey', function ($q) use ($id) {
                    $q->where('user_id', $id);
                })->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'stats' => $stats,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }
    }

    /**
     * Update a user.
     */
    public function updateUser(Request $request, int $id)
    {
        try {
            $user = User::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|max:255|unique:users,email,' . $id,
                'phone_number' => 'sometimes|nullable|string|max:20',
                'is_admin' => 'sometimes|boolean',
            ]);

            $user->update($validated);

            ActivityLogger::log('user_updated', 'user', $user->id, $user->id, 'Updated user profile', $validated);

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => $user,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating user.',
            ], 500);
        }
    }

    /**
     * Delete a user.
     */
    public function deleteUser(int $id)
    {
        try {
            $user = User::findOrFail($id);

            if ($user->id === Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot delete your own account',
                ], 403);
            }

            ActivityLogger::log('user_deleted', 'user', $user->id, $user->id, 'Deleted user account');
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting user.',
            ], 500);
        }
    }

    /**
     * List all WhatsApp sessions.
     */
    public function sessions(Request $request)
    {
        try {
            $query = WhatsAppSession::with('user')->orderBy('created_at', 'desc');

            if ($status = $request->input('status')) {
                $query->where('status', $status);
            }

            if ($search = $request->input('search')) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })->orWhere('phone_number', 'like', "%{$search}%");
            }

            $sessions = $query->paginate($request->input('per_page', 15));
            $systemSessionIds = SystemSetting::get('system_whatsapp_session_ids', []);
            if (!is_array($systemSessionIds)) {
                $systemSessionIds = [];
            }

            return response()->json([
                'success' => true,
                'data' => $sessions,
                'system_session_id' => null, // Legacy, kept for compatibility
                'system_session_ids' => $systemSessionIds,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading sessions: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Set a session as the system-wide WhatsApp sender.
     */
    public function setSystemSession(int $id)
    {
        try {
            $session = WhatsAppSession::findOrFail($id);
            
            if ($session->status !== 'connected') {
                return response()->json([
                    'success' => false,
                    'message' => 'يمكن فقط اختيار الأجهزة المتصلة لتكون مرسلاً للنظام',
                ], 400);
            }

            $systemSessionIds = SystemSetting::get('system_whatsapp_session_ids', []);
            if (!is_array($systemSessionIds)) {
                $systemSessionIds = [];
            }

            $alreadySystem = in_array($session->id, $systemSessionIds, true);

            if ($alreadySystem) {
                $systemSessionIds = array_values(array_filter($systemSessionIds, fn ($value) => $value !== $session->id));
                $message = 'تم إزالة الجهاز من مرسلي النظام';
                $logAction = 'system_session_removed';
            } else {
                $systemSessionIds[] = $session->id;
                $message = 'تم إضافة الجهاز كمرسل للنظام';
                $logAction = 'system_session_added';
            }

            SystemSetting::set('system_whatsapp_session_ids', $systemSessionIds, 'json', 'whatsapp', 'System WhatsApp Session IDs', 'The WhatsApp devices used by the system to send OTPs and notifications.');

            ActivityLogger::log($logAction, 'session', $session->id, $session->user_id, $message);

            return response()->json([
                'success' => true,
                'message' => $message,
                'system_session_ids' => $systemSessionIds,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating system session.',
            ], 500);
        }
    }

    /**
     * Send a WhatsApp message from any connected device/session to any phone number.
     */
    public function sendMessageFromSession(Request $request, int $id)
    {
        try {
            $request->validate([
                'to' => 'required|string',
                'message' => 'required|string',
            ]);

            $session = WhatsAppSession::findOrFail($id);

            if ($session->status !== 'connected') {
                return response()->json([
                    'success' => false,
                    'message' => 'الجهاز المحدد غير متصل حالياً',
                ], 400);
            }

            $normalizedPhone = PhoneNormalizer::normalize($request->input('to'));
            if (empty($normalizedPhone)) {
                return response()->json([
                    'success' => false,
                    'message' => 'رقم الهاتف المستلم غير صحيح',
                ], 422);
            }

            $message = WhatsAppMessage::create([
                'user_id' => $session->user_id,
                'session_id' => $session->id,
                'from' => $session->phone_number,
                'to' => $normalizedPhone,
                'message' => $request->input('message'),
                'message_type' => $request->input('message_type', 'text'),
                'media_url' => $request->input('media_url'),
                'direction' => 'outgoing',
                'status' => WhatsAppMessage::STATUS_QUEUED,
                'queued_at' => now(),
                'timestamp' => now(),
            ]);

            // Dispatch job with isSystem = true so it bypasses limits and delivers immediately
            SendWhatsAppMessageJob::dispatch($message->id, true);

            ActivityLogger::log(
                'admin_session_message_sent',
                'session',
                $session->id,
                $session->user_id,
                "Admin sent WhatsApp message to {$normalizedPhone} from session #{$session->id} ({$session->phone_number})"
            );

            return response()->json([
                'success' => true,
                'message' => 'تم وضع الرسالة في طابور الإرسال بنجاح وسيتم إرسالها فوراً',
                'data' => [
                    'message_id' => $message->id,
                    'from' => $session->phone_number,
                    'to' => $normalizedPhone,
                    'status' => 'queued',
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Error sending message from admin session: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'فشل إرسال الرسالة.',
            ], 500);
        }
    }

    /**
     * Delete a WhatsApp session.
     */
    public function deleteSession(int $id)
    {
        try {
            $session = WhatsAppSession::findOrFail($id);

            // Ask the WhatsApp service to clean up the live socket/auth
            $nodeUserId = $session->session_token ?? $session->user_id;
            $serviceUrl = rtrim(config('services.whatsapp.url', env('WHATSAPP_SERVICE_URL', 'http://127.0.0.1:3002')), '/');
            try {
                Http::timeout(15)
                    ->withHeaders(['Authorization' => 'Bearer ' . config('services.whatsapp.api_key')])
                    ->post("{$serviceUrl}/api/whatsapp/delete-session/{$nodeUserId}");
            } catch (\Throwable $e) {
                // Service may be unreachable; still update the local record
                \Log::warning('Could not notify WhatsApp service of admin disconnect', ['session_id' => $id, 'error' => $e->getMessage()]);
            }

            // Keep the session row but mark it as disconnected so the phone
            // number and connection history remain visible in the admin panel.
            $session->update([
                'status' => 'disconnected',
                'connected_at' => null,
                'last_activity_at' => now(),
            ]);

            ActivityLogger::log('session_disconnected', 'session', $id, $session->user_id, 'Disconnected WhatsApp session (kept record)');

            return response()->json([
                'success' => true,
                'message' => 'Session disconnected and kept in history',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error disconnecting session.',
            ], 500);
        }
    }

    /**
     * List all API keys.
     */
    public function apiKeys(Request $request)
    {
        try {
            $query = ApiKey::with('user')
                ->with('whatsappSession')
                ->withCount('logs')
                ->withCount(['pairedSessions as paired_sessions_count' => function ($q) {
                    $q->where('status', 'connected');
                }])
                ->orderBy('created_at', 'desc');

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('prefix', 'like', "%{$search}%");
                })->orWhereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            if ($request->filled('is_active')) {
                $query->where('is_active', $request->input('is_active'));
            }

            $apiKeys = $query->paginate($request->input('per_page', 15));

            $usage = $this->metaUsageForKeys($apiKeys->getCollection()->pluck('id')->all());
            $apiKeys->getCollection()->transform(function ($key) use ($usage) {
                $key->meta_usage = $usage[$key->id] ?? $this->emptyMetaUsage();
                return $key;
            });

            return response()->json([
                'success' => true,
                'data' => $apiKeys,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading API keys: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Meta Cloud API usage and billable-cost estimate for a set of API keys.
     * Meta never returns the charged amount in webhooks — it reports the
     * pricing category + a billable flag, so cost is an estimate using the
     * configured rate card (config/meta.php → pricing_rates).
     */
    private function metaUsageForKeys(array $keyIds): array
    {
        if (empty($keyIds)) {
            return [];
        }

        $rows = MetaTestMessage::whereIn('api_key_id', $keyIds)
            ->where('direction', MetaTestMessage::DIRECTION_OUTGOING)
            ->selectRaw('api_key_id')
            ->selectRaw('count(*) as total')
            ->selectRaw('SUM(status IN ("sent","delivered","read")) as delivered')
            ->selectRaw('SUM(status = "read") as `read`')
            ->selectRaw('SUM(status = "failed") as failed')
            ->selectRaw('SUM(pricing_billable = 1) as billable')
            ->selectRaw('SUM(pricing_billable = 0) as free_msgs')
            ->selectRaw('pricing_category')
            ->selectRaw('count(*) as cat_count')
            ->groupBy('api_key_id', 'pricing_category')
            ->get();

        $rates = config('meta.pricing_rates', []);
        $usage = [];

        foreach ($rows as $row) {
            $u = &$usage[$row->api_key_id];
            $u ??= $this->emptyMetaUsage();

            $u['total'] += (int) $row->total;
            $u['delivered'] += (int) $row->delivered;
            $u['read'] += (int) $row->read;
            $u['failed'] += (int) $row->failed;
            $u['billable'] += (int) $row->billable;
            $u['free'] += (int) $row->free_msgs;

            $cat = $row->pricing_category ?: 'unpriced';
            $u['categories'][$cat] = ($u['categories'][$cat] ?? 0) + (int) $row->cat_count;

            // Only billable messages accrue cost at the category rate.
            $billableCat = min((int) $row->billable, (int) $row->cat_count);
            $u['estimated_usd'] += $billableCat * (float) ($rates[$cat] ?? 0);
        }

        foreach ($usage as &$u) {
            $u['estimated_usd'] = round($u['estimated_usd'], 4);
        }

        return $usage;
    }

    private function emptyMetaUsage(): array
    {
        return [
            'total' => 0,
            'delivered' => 0,
            'read' => 0,
            'failed' => 0,
            'billable' => 0,
            'free' => 0,
            'estimated_usd' => 0.0,
            'categories' => [],
        ];
    }

    /**
     * Detailed Meta usage for a single API key (recent messages + totals).
     */
    public function apiKeyMetaUsage(int $id)
    {
        $key = ApiKey::with('user:id,name,email')->findOrFail($id);

        $usage = $this->metaUsageForKeys([$id])[$id] ?? $this->emptyMetaUsage();

        $messages = MetaTestMessage::where('api_key_id', $id)
            ->where('direction', MetaTestMessage::DIRECTION_OUTGOING)
            ->latest()
            ->limit(50)
            ->get(['id', 'to_phone', 'message_type', 'status', 'pricing_category', 'pricing_billable', 'pricing_type', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => [
                'key' => ['id' => $key->id, 'name' => $key->name, 'prefix' => $key->prefix, 'user' => $key->user],
                'usage' => $usage,
                'rates' => config('meta.pricing_rates', []),
                'recent_messages' => $messages,
            ],
        ]);
    }

    /**
     * Delete an API key.
     */
    public function deleteApiKey(int $id)
    {
        try {
            $apiKey = ApiKey::findOrFail($id);
            $apiKey->delete();

            ActivityLogger::log('api_key_deleted', 'api_key', $id, $apiKey->user_id, 'Deleted API key: ' . $apiKey->name);

            return response()->json([
                'success' => true,
                'message' => 'API key deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting API key: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all messages with search and filters.
     */
    public function messages(Request $request)
    {
        try {
            $query = WhatsAppMessage::with(['user', 'apiKey', 'session'])
                ->select('whatsapp_messages.*');

            // Search
            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('to', 'like', "%{$search}%")
                        ->orWhere('from', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%")
                        ->orWhere('message_id', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            }

            // Filter by status
            if ($status = $request->input('status')) {
                $query->where('status', $status);
            }

            // Filter by message type
            if ($messageType = $request->input('message_type')) {
                $query->where('message_type', $messageType);
            }

            // Filter by direction
            if ($direction = $request->input('direction')) {
                $query->where('direction', $direction);
            }

            // Filter by user
            if ($userId = $request->input('user_id')) {
                $query->where('user_id', $userId);
            }

            // Filter by session
            if ($sessionId = $request->input('session_id')) {
                $query->where('session_id', $sessionId);
            }

            // Filter by API key
            if ($apiKeyId = $request->input('api_key_id')) {
                $query->where('api_key_id', $apiKeyId);
            }

            // Date range filter
            if ($from = $request->input('date_from')) {
                $query->whereDate('created_at', '>=', $from);
            }
            if ($to = $request->input('date_to')) {
                $query->whereDate('created_at', '<=', $to);
            }

            // Sorting
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            
            $allowedSortFields = ['created_at', 'status', 'to', 'from'];
            if (in_array($sortBy, $allowedSortFields)) {
                $query->orderBy($sortBy, $sortOrder);
            } else {
                $query->orderBy('created_at', 'desc');
            }

            $messages = $query->paginate($request->input('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $messages,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading messages: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a single message.
     */
    public function showMessage(int $id)
    {
        try {
            $message = WhatsAppMessage::with(['user', 'apiKey', 'session'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $message,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Message not found',
            ], 404);
        }
    }

    /**
     * Resend a failed message.
     */
    public function resendMessage(int $id)
    {
        try {
            $message = WhatsAppMessage::findOrFail($id);

            // Reset message status
            $message->update([
                'status' => 'pending',
                'attempts' => 0,
                'error_message' => null,
                'failed_at' => null,
            ]);

            ActivityLogger::log('message_resent', 'message', $id, $message->user_id, 'Resent WhatsApp message');

            return response()->json([
                'success' => true,
                'message' => 'Message queued for resending',
                'data' => $message,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error resending message: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a message.
     */
    public function deleteMessage(int $id)
    {
        try {
            $message = WhatsAppMessage::findOrFail($id);
            $message->delete();

            ActivityLogger::log('message_deleted', 'message', $id, $message->user_id, 'Deleted WhatsApp message');

            return response()->json([
                'success' => true,
                'message' => 'Message deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting message: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export messages to CSV.
     */
    public function exportMessagesCSV(Request $request)
    {
        try {
            $query = WhatsAppMessage::with(['user', 'apiKey', 'session']);

            // Apply same filters as messages() method
            $this->applyMessageFilters($query, $request);

            $messages = $query->limit(10000)->get();

            $filename = 'messages_' . date('Y-m-d_H-i-s') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ];

            $callback = function () use ($messages) {
                $file = fopen('php://output', 'w');
                
                // CSV Headers
                fputcsv($file, [
                    'ID',
                    'User',
                    'From',
                    'To',
                    'Message',
                    'Type',
                    'Status',
                    'Direction',
                    'Session',
                    'API Key',
                    'Created At',
                ]);

                // CSV Data
                foreach ($messages as $message) {
                    fputcsv($file, [
                        $message->id,
                        $message->user->name ?? 'N/A',
                        $message->from ?? '',
                        $message->to ?? '',
                        $message->message ?? '',
                        $message->message_type ?? 'text',
                        $message->status ?? '',
                        $message->direction ?? '',
                        $message->session->phone_number ?? 'N/A',
                        $message->apiKey->name ?? 'N/A',
                        $message->created_at->format('Y-m-d H:i:s'),
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error exporting messages: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export messages to Excel.
     */
    public function exportMessagesExcel(Request $request)
    {
        try {
            $query = WhatsAppMessage::with(['user', 'apiKey', 'session']);
            $this->applyMessageFilters($query, $request);
            $messages = $query->limit(10000)->get();

            $filename = 'messages_' . date('Y-m-d_H-i-s') . '.xlsx';
            
            // For now, we'll export as CSV with .xlsx extension
            // In production, you'd use a library like PhpSpreadsheet
            return $this->exportMessagesCSV($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error exporting messages: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export messages to PDF.
     */
    public function exportMessagesPDF(Request $request)
    {
        try {
            $query = WhatsAppMessage::with(['user', 'apiKey', 'session']);
            $this->applyMessageFilters($query, $request);
            $messages = $query->limit(1000)->get();

            // For now, return JSON with message data
            // In production, you'd use a library like DomPDF or TCPDF
            return response()->json([
                'success' => true,
                'message' => 'PDF export feature coming soon',
                'data' => [
                    'total' => $messages->count(),
                    'messages' => $messages,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error exporting messages: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Apply filters to message query.
     */
    private function applyMessageFilters($query, Request $request)
    {
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('to', 'like', "%{$search}%")
                    ->orWhere('from', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($messageType = $request->input('message_type')) {
            $query->where('message_type', $messageType);
        }

        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($from = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }

    /**
     * List all subscriptions.
     */
    public function subscriptions(Request $request)
    {
        try {
            $query = Subscription::with(['user', 'plan'])->orderBy('created_at', 'desc');

            if ($status = $request->input('status')) {
                $query->where('status', $status);
            }

            if ($search = $request->input('search')) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $subscriptions = $query->paginate($request->input('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $subscriptions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading subscriptions: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a subscription status.
     */
    public function updateSubscription(Request $request, int $id)
    {
        try {
            $subscription = Subscription::findOrFail($id);

            $validated = $request->validate([
                'status' => 'sometimes|string|in:active,cancelled,expired,pending,suspended',
                'messages_limit' => 'sometimes|integer|min:0',
                'messages_remaining' => 'sometimes|integer|min:0',
                'ends_at' => 'sometimes|nullable|date',
            ]);

            $subscription->update($validated);

            ActivityLogger::log('subscription_updated', 'subscription', $id, $subscription->user_id, 'Updated subscription', $validated);

            return response()->json([
                'success' => true,
                'message' => 'Subscription updated successfully',
                'data' => $subscription,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating subscription: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all plans including inactive ones.
     */
    public function plans(Request $request)
    {
        try {
            $plans = Plan::orderBy('sort_order', 'asc')->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $plans,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading plans: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all webhooks.
     */
    public function webhooks(Request $request)
    {
        try {
            $query = Webhook::with('user')->withCount('logs')->orderBy('created_at', 'desc');

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('url', 'like', "%{$search}%");
                })->orWhereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            if ($request->filled('is_active')) {
                $query->where('is_active', $request->input('is_active'));
            }

            $webhooks = $query->paginate($request->input('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $webhooks,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading webhooks: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a webhook.
     */
    public function deleteWebhook(int $id)
    {
        try {
            $webhook = Webhook::findOrFail($id);
            $webhook->delete();

            ActivityLogger::log('webhook_deleted', 'webhook', $id, $webhook->user_id, 'Deleted webhook: ' . $webhook->name);

            return response()->json([
                'success' => true,
                'message' => 'Webhook deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting webhook: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List webhook logs for a webhook.
     */
    public function webhookLogs(int $id)
    {
        try {
            $webhook = Webhook::findOrFail($id);
            $logs = WebhookLog::where('webhook_id', $id)->orderBy('created_at', 'desc')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $logs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading webhook logs: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get queue status and jobs.
     */
    public function queueStatus(Request $request)
    {
        try {
            $type = $request->input('type', 'pending');
            $perPage = $request->input('per_page', 20);

            if ($type === 'failed') {
                $jobs = DB::table('failed_jobs')
                    ->orderBy('failed_at', 'desc')
                    ->paginate($perPage);
            } else {
                $jobs = DB::table('jobs')
                    ->orderBy('created_at', 'asc')
                    ->paginate($perPage);
            }

            return response()->json([
                'success' => true,
                'data' => $jobs,
                'counts' => [
                    'pending' => DB::table('jobs')->count(),
                    'failed' => DB::table('failed_jobs')->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading queue status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retry a failed job.
     */
    public function retryFailedJob(int $id)
    {
        try {
            $job = DB::table('failed_jobs')->where('id', $id)->first();
            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed job not found',
                ], 404);
            }

            \Illuminate\Support\Facades\Artisan::call('queue:retry', ['id' => $id]);

            ActivityLogger::log('failed_job_retried', 'failed_job', $id, null, 'Retried failed job');

            return response()->json([
                'success' => true,
                'message' => 'Failed job retried',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrying failed job: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a failed job.
     */
    public function deleteFailedJob(int $id)
    {
        try {
            $job = DB::table('failed_jobs')->where('id', $id)->first();
            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed job not found',
                ], 404);
            }

            \Illuminate\Support\Facades\Artisan::call('queue:forget', ['id' => $id]);

            ActivityLogger::log('failed_job_deleted', 'failed_job', $id, null, 'Deleted failed job');

            return response()->json([
                'success' => true,
                'message' => 'Failed job deleted',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting failed job: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all connected devices.
     */
    public function devices(Request $request)
    {
        try {
            $query = UserDevice::with('user')->orderBy('last_active_at', 'desc');

            if ($search = $request->input('search')) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            if ($request->filled('is_current')) {
                $query->where('is_current', $request->input('is_current'));
            }

            $devices = $query->paginate($request->input('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $devices,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading devices: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a device.
     */
    public function deleteDevice(int $id)
    {
        try {
            $device = UserDevice::findOrFail($id);
            $device->delete();

            ActivityLogger::log('device_deleted', 'device', $id, $device->user_id, 'Deleted user device');

            return response()->json([
                'success' => true,
                'message' => 'Device deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting device: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get system statistics.
     */
    public function systemStats(Request $request)
    {
        try {
            $diskTotal = disk_total_space(base_path());
            $diskFree = disk_free_space(base_path());
            $diskUsed = $diskTotal - $diskFree;

            $stats = [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'environment' => env('APP_ENV', 'production'),
                'debug' => env('APP_DEBUG', false),
                'queue_driver' => env('QUEUE_CONNECTION', 'sync'),
                'database' => config('database.default'),
                'disk' => [
                    'total' => $diskTotal,
                    'free' => $diskFree,
                    'used' => $diskUsed,
                    'usage_percent' => $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 2) : 0,
                ],
                'memory' => [
                    'limit' => ini_get('memory_limit'),
                    'usage' => memory_get_usage(true),
                    'peak' => memory_get_peak_usage(true),
                ],
                'timezone' => config('app.timezone'),
                'now' => now()->toIso8601String(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading system stats: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get analytics data over time.
     */
    public function analytics(Request $request)
    {
        try {
            $days = min((int) $request->input('days', 30), 90);
            $startDate = now()->subDays($days)->startOfDay();

            $messagesPerDay = WhatsAppMessage::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
                ->where('created_at', '>=', $startDate)
                ->groupBy('date')
                ->orderBy('date', 'asc')
                ->get();

            $usersPerDay = User::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
                ->where('created_at', '>=', $startDate)
                ->groupBy('date')
                ->orderBy('date', 'asc')
                ->get();

            $revenuePerDay = Transaction::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(amount) as total')
            )
                ->where('status', 'completed')
                ->where('created_at', '>=', $startDate)
                ->groupBy('date')
                ->orderBy('date', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'messages_per_day' => $messagesPerDay,
                    'users_per_day' => $usersPerDay,
                    'revenue_per_day' => $revenuePerDay,
                    'days' => $days,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading analytics: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List activity logs.
     */
    public function activityLogs(Request $request)
    {
        try {
            $query = ActivityLog::with(['user', 'admin'])->latest();

            if ($action = $request->input('action')) {
                $query->where('action', $action);
            }

            if ($entityType = $request->input('entity_type')) {
                $query->where('entity_type', $entityType);
            }

            if ($userId = $request->input('user_id')) {
                $query->where(function ($q) use ($userId) {
                    $q->where('user_id', $userId)->orWhere('admin_id', $userId);
                });
            }

            $logs = $query->paginate($request->input('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $logs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading activity logs: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List audit logs.
     */
    public function auditLogs(Request $request)
    {
        try {
            $query = AuditLog::with('user')->latest();

            if ($action = $request->input('action')) {
                $query->where('action', $action);
            }

            if ($entityType = $request->input('entity_type')) {
                $query->where('entity_type', $entityType);
            }

            if ($userId = $request->input('user_id')) {
                $query->where('user_id', $userId);
            }

            $logs = $query->paginate($request->input('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $logs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading audit logs: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List admin notifications.
     */
    public function notifications(Request $request)
    {
        try {
            $query = AdminNotification::with(['user', 'admin'])->orderBy('created_at', 'desc');

            if ($type = $request->input('type')) {
                $query->where('type', $type);
            }

            if ($request->filled('is_read')) {
                $query->where('is_read', $request->input('is_read'));
            }

            $notifications = $query->paginate($request->input('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $notifications,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading notifications: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send a notification to users.
     */
    public function sendNotification(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'message' => 'required|string',
                'type' => 'required|string|in:info,warning,success,error',
                'user_ids' => 'nullable|array',
                'user_ids.*' => 'integer|exists:users,id',
                'send_to_all' => 'required|boolean',
            ]);

            $sendToAll = $validated['send_to_all'];
            $userIds = $validated['user_ids'] ?? [];

            $createdCount = 0;
            $adminId = Auth::id();

            if ($sendToAll) {
                User::select('id')->orderBy('id')->chunk(100, function ($users) use ($validated, $adminId, &$createdCount) {
                    $records = [];
                    foreach ($users as $user) {
                        $records[] = [
                            'user_id' => $user->id,
                            'admin_id' => $adminId,
                            'title' => $validated['title'],
                            'message' => $validated['message'],
                            'type' => $validated['type'],
                            'is_read' => false,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    AdminNotification::insert($records);
                    $createdCount += count($records);
                });
            } else {
                $records = [];
                foreach (array_unique($userIds) as $userId) {
                    $records[] = [
                        'user_id' => $userId,
                        'admin_id' => $adminId,
                        'title' => $validated['title'],
                        'message' => $validated['message'],
                        'type' => $validated['type'],
                        'is_read' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (!empty($records)) {
                    AdminNotification::insert($records);
                    $createdCount = count($records);
                }
            }

            ActivityLogger::log(
                'notification_sent',
                'notification',
                null,
                null,
                "Sent notification to " . ($sendToAll ? 'all users' : $createdCount . ' users'),
                ['title' => $validated['title'], 'type' => $validated['type'], 'send_to_all' => $sendToAll]
            );

            return response()->json([
                'success' => true,
                'message' => "Notification sent to {$createdCount} users",
                'data' => ['count' => $createdCount],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error sending notification: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a notification.
     */
    public function deleteNotification(int $id)
    {
        try {
            $notification = AdminNotification::findOrFail($id);
            $notification->delete();

            ActivityLogger::log('notification_deleted', 'notification', $id, null, 'Deleted notification');

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting notification: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Suspend a user.
     */
    public function suspendUser(Request $request, int $id)
    {
        try {
            $user = User::findOrFail($id);
            $user->update(['status' => 'suspended']);
            ActivityLogger::log('user_suspended', 'user', $id, $user->id, 'User suspended by admin');

            return response()->json(['success' => true, 'message' => 'User suspended']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error suspending user: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Ban a user.
     */
    public function banUser(int $id)
    {
        try {
            $user = User::findOrFail($id);
            $user->update(['status' => 'banned', 'banned_at' => now()]);
            $user->tokens()->delete();
            ActivityLogger::log('user_banned', 'user', $id, $user->id, 'User banned by admin');

            return response()->json(['success' => true, 'message' => 'User banned']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error banning user: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reactivate a suspended/banned user.
     */
    public function reactivateUser(int $id)
    {
        try {
            $user = User::findOrFail($id);
            $user->update(['status' => 'active', 'banned_at' => null]);
            ActivityLogger::log('user_reactivated', 'user', $id, $user->id, 'User reactivated by admin');

            return response()->json(['success' => true, 'message' => 'User reactivated']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error reactivating user: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Login as another user (generate a temporary token for admin).
     */
    public function loginAsUser(int $id)
    {
        try {
            $user = User::findOrFail($id);
            $token = $user->createToken('admin_login_as')->plainTextToken;
            ActivityLogger::log('login_as_user', 'user', $id, $user->id, 'Admin logged in as user');

            return response()->json(['success' => true, 'data' => ['token' => $token, 'user' => $user]]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error generating token.'], 500);
        }
    }

    /**
     * Adjust user's message balance.
     */
    public function adjustUserBalance(Request $request, int $id)
    {
        try {
            $validated = $request->validate([
                'amount' => 'required|integer',
                'reason' => 'nullable|string|max:500',
            ]);

            $user = User::findOrFail($id);
            $user->increment('messages_balance', $validated['amount']);

            ActivityLogger::log('user_balance_adjusted', 'user', $id, $user->id, 'Adjusted message balance by ' . $validated['amount'], $validated);

            return response()->json(['success' => true, 'message' => 'Balance adjusted', 'data' => $user]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error adjusting balance.'], 500);
        }
    }

    /**
     * Change user's subscription plan.
     */
    public function changeUserPlan(Request $request, int $id)
    {
        try {
            $validated = $request->validate([
                'plan_id' => 'required|integer|exists:plans,id',
                'status' => 'nullable|string|in:active,pending,suspended,cancelled',
                'messages_limit' => 'nullable|integer|min:0',
                'ends_at' => 'nullable|date',
            ]);

            $user = User::findOrFail($id);
            $plan = Plan::findOrFail($validated['plan_id']);

            $subscription = $user->subscriptions()->latest()->first() ?? new Subscription();
            $subscription->fill([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => $validated['status'] ?? 'active',
                'messages_limit' => $validated['messages_limit'] ?? $plan->messages_limit,
                'messages_remaining' => $validated['messages_limit'] ?? $plan->messages_limit,
                'messages_used' => 0,
                'starts_at' => now(),
                'ends_at' => $validated['ends_at'] ?? now()->addMonth(),
            ]);
            $subscription->save();

            $user->update(['subscription_plan' => $plan->name, 'subscription_status' => $subscription->status]);

            ActivityLogger::log('user_plan_changed', 'subscription', $subscription->id, $user->id, 'Plan changed to ' . $plan->name, $validated);

            return response()->json(['success' => true, 'message' => 'Plan changed', 'data' => $subscription]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error changing plan.'], 500);
        }
    }

    /**
     * Get user's login history.
     */
    public function userLoginHistory(Request $request, int $id)
    {
        try {
            $logs = LoginHistory::where('user_id', $id)
                ->latest()
                ->paginate($request->input('per_page', 20));

            return response()->json(['success' => true, 'data' => $logs]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error loading login history: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get user's devices.
     */
    public function userDevices(Request $request, int $id)
    {
        try {
            $devices = UserDevice::where('user_id', $id)
                ->orderBy('last_active_at', 'desc')
                ->paginate($request->input('per_page', 20));

            return response()->json(['success' => true, 'data' => $devices]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error loading devices: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get user's API keys.
     */
    public function userApiKeys(Request $request, int $id)
    {
        try {
            $keys = ApiKey::where('user_id', $id)
                ->withCount('logs')
                ->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 20));

            return response()->json(['success' => true, 'data' => $keys]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error loading API keys: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get user's messages.
     */
    public function userMessages(Request $request, int $id)
    {
        try {
            $messages = WhatsAppMessage::where('user_id', $id)
                ->latest()
                ->paginate($request->input('per_page', 20));

            return response()->json(['success' => true, 'data' => $messages]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error loading messages: ' . $e->getMessage()], 500);
        }
    }

    /**
     * API monitoring logs.
     */
    public function apiMonitoring(Request $request)
    {
        try {
            $query = ApiLog::with(['user', 'apiKey'])->orderBy('created_at', 'desc');

            if ($search = $request->input('search')) {
                $query->where('endpoint', 'like', "%{$search}%");
            }
            if ($status = $request->input('status')) {
                $statusCode = $status === 'success' ? 200 : ($status === 'error' ? 500 : (int) $status);
                $query->where('status_code', $statusCode);
            }
            if ($userId = $request->input('user_id')) {
                $query->where('user_id', $userId);
            }

            $logs = $query->paginate($request->input('per_page', 20));
            return response()->json(['success' => true, 'data' => $logs]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error loading API logs: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Fraud alerts.
     */
    public function fraudAlerts(Request $request)
    {
        try {
            $query = FraudAlert::with('user')->orderBy('created_at', 'desc');

            if ($severity = $request->input('severity')) {
                $query->where('severity', $severity);
            }
            if ($request->filled('is_resolved')) {
                $query->where('is_resolved', $request->input('is_resolved'));
            }
            if ($userId = $request->input('user_id')) {
                $query->where('user_id', $userId);
            }
            if ($type = $request->input('type')) {
                $query->where('type', 'like', '%' . $type . '%');
            }
            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('ip_address', 'like', '%' . $search . '%')
                      ->orWhere('owner_name', 'like', '%' . $search . '%')
                      ->orWhere('owner_email', 'like', '%' . $search . '%')
                      ->orWhere('owner_phone', 'like', '%' . $search . '%')
                      ->orWhere('device_fingerprint', 'like', '%' . $search . '%')
                      ->orWhere('type', 'like', '%' . $search . '%');
                });
            }

            $alerts = $query->paginate($request->input('per_page', 20));
            return response()->json(['success' => true, 'data' => $alerts]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error loading fraud alerts'], 500);
        }
    }

    /**
     * Resolve a fraud alert.
     */
    public function resolveFraudAlert(int $id)
    {
        try {
            $alert = FraudAlert::findOrFail($id);
            $alert->update(['is_resolved' => true, 'resolved_at' => now(), 'resolved_by' => Auth::id()]);
            ActivityLogger::log('fraud_alert_resolved', 'fraud_alert', $id, $alert->user_id, 'Fraud alert resolved');
            return response()->json(['success' => true, 'message' => 'Fraud alert resolved']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error resolving alert'], 500);
        }
    }

    /**
     * Real-time events feed.
     */
    public function realTimeEvents(Request $request)
    {
        try {
            $query = RealTimeEvent::with('user')->latest();

            if ($type = $request->input('type')) {
                $query->where('type', $type);
            }
            if ($since = $request->input('since')) {
                $query->where('created_at', '>', $since);
            }

            $events = $query->limit($request->input('limit', 50))->get();
            return response()->json(['success' => true, 'data' => $events]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error loading events: ' . $e->getMessage()], 500);
        }
    }

    /**
     * System settings.
     */
    public function systemSettings(Request $request)
    {
        try {
            $group = $request->input('group');
            $query = SystemSetting::query();
            if ($group) {
                $query->where('group', $group);
            }
            return response()->json(['success' => true, 'data' => $query->get()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error loading settings: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update or create a system setting.
     */
    public function updateSystemSetting(Request $request)
    {
        try {
            $validated = $request->validate([
                'key' => 'required|string|max:255',
                'value' => 'required',
                'type' => 'required|string|in:string,integer,boolean,json',
                'group' => 'required|string|max:50',
                'label' => 'nullable|string|max:255',
                'description' => 'nullable|string',
            ]);

            SystemSetting::set($validated['key'], $validated['value'], $validated['type'], $validated['group'], $validated['label'] ?? null, $validated['description'] ?? null);
            ActivityLogger::log('setting_updated', 'system_setting', null, null, 'Updated setting: ' . $validated['key'], $validated);

            return response()->json(['success' => true, 'message' => 'Setting updated']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error updating setting: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Transactions list with refund support.
     */
    public function transactions(Request $request)
    {
        try {
            $query = Transaction::with(['user', 'plan'])->orderBy('created_at', 'desc');

            if ($status = $request->input('status')) {
                $query->where('status', $status);
            }
            if ($search = $request->input('search')) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
                })->orWhere('gateway_transaction_id', 'like', "%{$search}%");
            }

            $transactions = $query->paginate($request->input('per_page', 20));
            return response()->json(['success' => true, 'data' => $transactions]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error loading transactions: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Refund a transaction.
     */
    public function refundTransaction(int $id)
    {
        try {
            $transaction = Transaction::findOrFail($id);
            if ($transaction->status !== 'completed') {
                return response()->json(['success' => false, 'message' => 'Only completed transactions can be refunded'], 400);
            }

            $transaction->update(['status' => 'refunded', 'refunded_at' => now()]);
            ActivityLogger::log('transaction_refunded', 'transaction', $id, $transaction->user_id, 'Transaction refunded: ' . $transaction->gateway_transaction_id);

            return response()->json(['success' => true, 'message' => 'Transaction refunded']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error refunding transaction: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get login history for all users (Security Center).
     */
    public function loginHistory(Request $request)
    {
        try {
            $query = LoginHistory::with('user')->orderBy('login_at', 'desc');

            if ($success = $request->input('success')) {
                $query->where('success', $success);
            }
            if ($userId = $request->input('user_id')) {
                $query->where('user_id', $userId);
            }

            $logs = $query->paginate($request->input('per_page', 20));
            return response()->json(['success' => true, 'data' => $logs]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error loading login history: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Failed login attempts.
     */
    public function failedLogins(Request $request)
    {
        try {
            $query = LoginHistory::with('user')->where('success', false)->orderBy('login_at', 'desc');
            if ($userId = $request->input('user_id')) {
                $query->where('user_id', $userId);
            }
            $logs = $query->paginate($request->input('per_page', 20));
            return response()->json(['success' => true, 'data' => $logs]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error loading failed logins: ' . $e->getMessage()], 500);
        }
    }

    /**
     * List all Telegram bot sessions across users.
     */
    public function telegramSessions(Request $request)
    {
        try {
            $query = TelegramSession::with('user:id,name,email,phone_number')
                ->withCount(['messages as messages_count']);

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('bot_username', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('device_name', 'like', "%{$search}%")
                        ->orWhere('bot_id', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            }

            if ($status = $request->input('status')) {
                $query->where('status', $status);
            }

            $sessions = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $sessions,
                'counts' => [
                    'total' => TelegramSession::count(),
                    'connected' => TelegramSession::where('status', 'connected')->count(),
                    'disconnected' => TelegramSession::where('status', 'disconnected')->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading Telegram sessions: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a Telegram bot session.
     */
    public function deleteTelegramSession(int $id)
    {
        try {
            $session = TelegramSession::findOrFail($id);
            if ($session->bot_token) {
                app(\App\Services\TelegramService::class)->deleteWebhook($session->bot_token);
            }
            $session->delete();

            ActivityLogger::log('telegram_session_deleted', 'telegram_session', $id, $session->user_id, 'Deleted Telegram bot session: @' . $session->bot_username);

            return response()->json(['success' => true, 'message' => 'Telegram session deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error deleting Telegram session: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Force disconnect a Telegram bot session.
     */
    public function disconnectTelegramSession(int $id)
    {
        try {
            $session = TelegramSession::findOrFail($id);
            if ($session->bot_token) {
                app(\App\Services\TelegramService::class)->deleteWebhook($session->bot_token);
            }
            $session->update([
                'status' => 'disconnected',
                'webhook_set' => false,
            ]);

            ActivityLogger::log('telegram_session_disconnected', 'telegram_session', $id, $session->user_id, 'Admin disconnected Telegram bot: @' . $session->bot_username);

            return response()->json(['success' => true, 'message' => 'Telegram bot disconnected']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error disconnecting Telegram bot: ' . $e->getMessage()], 500);
        }
    }

    /**
     * List all Telegram messages platform-wide.
     */
    public function telegramMessages(Request $request)
    {
        try {
            $query = TelegramMessage::with([
                'user:id,name,email',
                'session:id,bot_username,first_name,device_name',
            ]);

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('chat_id', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            }

            if ($status = $request->input('status')) {
                $query->where('status', $status);
            }

            if ($direction = $request->input('direction')) {
                $query->where('direction', $direction);
            }

            if ($type = $request->input('type')) {
                $query->where('type', $type);
            }

            if ($userId = $request->input('user_id')) {
                $query->where('user_id', $userId);
            }

            $messages = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $messages,
                'counts' => [
                    'total' => TelegramMessage::count(),
                    'sent' => TelegramMessage::where('status', 'sent')->count(),
                    'failed' => TelegramMessage::where('status', 'failed')->count(),
                    'incoming' => TelegramMessage::where('direction', 'incoming')->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading Telegram messages: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a Telegram message.
     */
    public function deleteTelegramMessage(int $id)
    {
        try {
            $msg = TelegramMessage::findOrFail($id);
            $msg->delete();

            return response()->json(['success' => true, 'message' => 'Message deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error deleting message: ' . $e->getMessage()], 500);
        }
    }

    /**
     * List all Telegram campaigns.
     */
    public function telegramCampaigns(Request $request)
    {
        try {
            $query = TelegramCampaign::with([
                'user:id,name,email',
                'session:id,bot_username,first_name,device_name',
            ]);

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            }

            if ($status = $request->input('status')) {
                $query->where('status', $status);
            }

            $campaigns = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $campaigns,
                'counts' => [
                    'total' => TelegramCampaign::count(),
                    'running' => TelegramCampaign::where('status', 'running')->count(),
                    'completed' => TelegramCampaign::where('status', 'completed')->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading Telegram campaigns: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a Telegram campaign.
     */
    public function deleteTelegramCampaign(int $id)
    {
        try {
            $campaign = TelegramCampaign::findOrFail($id);
            $campaign->delete();

            ActivityLogger::log('telegram_campaign_deleted', 'telegram_campaign', $id, $campaign->user_id, 'Deleted Telegram campaign: ' . $campaign->name);

            return response()->json(['success' => true, 'message' => 'Campaign deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error deleting campaign: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Add phone-verification status and the last failure reason to a user model
     * so the admin users list can display them in a dedicated column.
     */
    private function enrichUserPhoneVerification(User $user): void
    {
        if ($user->email_verified_at || OtpCode::where('user_id', $user->id)->where('purpose', 'registration')->whereNotNull('verified_at')->exists()) {
            $user->setAttribute('phone_verified', true);
            $user->setAttribute('phone_verification_error', null);
            return;
        }

        $user->setAttribute('phone_verified', false);

        $latestOtp = OtpCode::where('user_id', $user->id)
            ->where('purpose', 'registration')
            ->latest('created_at')
            ->first();

        if (!$latestOtp) {
            $user->setAttribute('phone_verification_error', 'لم يتم طلب تحقق الهاتف');
            return;
        }

        $failedMessage = WhatsAppMessage::where('to', $latestOtp->phone_number)
            ->where('message', 'like', '%كود التحقق%')
            ->where('direction', 'outgoing')
            ->where('status', 'failed')
            ->latest('created_at')
            ->first();

        if ($failedMessage && $failedMessage->error_message) {
            $user->setAttribute('phone_verification_error', $failedMessage->error_message);
            return;
        }

        if ($latestOtp->expires_at && $latestOtp->expires_at->isFuture()) {
            $user->setAttribute('phone_verification_error', 'تم إرسال كود التحقق لكن المستخدم لم يدخله بعد');
        } else {
            $user->setAttribute('phone_verification_error', 'انتهت صلاحية آخر كود تحقق ولم يتم التحقق');
        }
    }
}

