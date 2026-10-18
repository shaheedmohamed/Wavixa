<?php

namespace App\Modules\WhatsApp\Controllers;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppAutoReply;
use App\Models\WhatsAppAutoReplyLog;
use App\Models\WhatsAppSession;
use App\Services\AIReplyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AutoReplyController extends Controller
{
    private string $whatsappServiceUrl;

    public function __construct()
    {
        $this->whatsappServiceUrl = config('services.whatsapp.url', 'http://127.0.0.1:3005') . '/api/whatsapp';
    }

    /**
     * Get auto reply settings and user's WhatsApp sessions.
     */
    public function getSettings(Request $request)
    {
        try {
            $userId = Auth::id();

            $autoReply = WhatsAppAutoReply::with('session:id,phone_number,device_name,status')
                ->where('user_id', $userId)
                ->first();

            $sessions = WhatsAppSession::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            // Sync with live WhatsApp service if local list is empty or has no connected sessions
            if ($sessions->isEmpty() || $sessions->where('status', 'connected')->isEmpty()) {
                try {
                    $response = Http::timeout(5)
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
                    // Ignore service error; return current sessions
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'auto_reply' => $autoReply ? $this->serializeAutoReply($autoReply) : null,
                    'sessions' => $sessions,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب إعدادات الرد التلقائي: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save auto reply settings.
     */
    public function saveSettings(Request $request)
    {
        try {
            $userId = Auth::id();

            $validated = $request->validate([
                'is_enabled' => 'required|boolean',
                'session_id' => 'nullable|integer',
                'mode' => 'required|in:static,ai',
                'reply_message' => 'nullable|string|max:4000',
                'reply_once' => 'nullable|boolean',
                'delay_seconds' => 'nullable|integer|in:0,5,10,30',
                'ai_system_prompt' => 'nullable|string|max:8000',
                'ai_business_info' => 'nullable|string|max:8000',
                'ai_tone' => 'nullable|string|in:friendly,professional,brief,formal',
                'ai_language' => 'nullable|string|in:arabic,egyptian,english,auto',
                'ai_context_limit' => 'nullable|integer|in:5,10,15,20',
                'ai_fallback_enabled' => 'nullable|boolean',
                'ai_fallback_message' => 'nullable|string|max:4000',
                'ai_max_daily_replies' => 'nullable|integer|min:0|max:10000',
                'ai_business_hours_enabled' => 'nullable|boolean',
                'ai_business_hours_from' => 'nullable|date_format:H:i',
                'ai_business_hours_to' => 'nullable|date_format:H:i',
                'ai_model' => 'nullable|string|max:255',
                'ai_handoff_minutes' => 'nullable|integer|min:0|max:1440',
            ]);

            $isEnabled = (bool) $validated['is_enabled'];
            $sessionId = $validated['session_id'] ?? null;
            $mode = $validated['mode'] ?? 'static';
            $replyMessage = trim($validated['reply_message'] ?? '');

            if ($isEnabled) {
                if (!$sessionId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'يرجى اختيار جهاز WhatsApp لتفعيل الرد التلقائي.',
                    ], 422);
                }

                $session = WhatsAppSession::where('id', $sessionId)
                    ->where('user_id', $userId)
                    ->first();

                if (!$session) {
                    return response()->json([
                        'success' => false,
                        'message' => 'جهاز WhatsApp المحدد غير موجود أو غير مرتبط بحسابك.',
                    ], 403);
                }

                if ($session->status !== 'connected') {
                    return response()->json([
                        'success' => false,
                        'message' => 'لا يمكن تفعيل الرد التلقائي على جهاز غير متصل. يرجى ربط الجهاز أولاّ.',
                    ], 422);
                }

                if ($mode === 'static' && empty($replyMessage)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'يرجى كتابة رسالة الرد التلقائي قبل التفعيل.',
                    ], 422);
                }

                if ($mode === 'ai' && empty(trim($validated['ai_system_prompt'] ?? ''))) {
                    return response()->json([
                        'success' => false,
                        'message' => 'يرجى كتابة تعليمات الذكاء الاصطناعي قبل تفعيل وضع AI.',
                    ], 422);
                }
            } else if ($sessionId) {
                // If disabled but session is passed, verify ownership
                $session = WhatsAppSession::where('id', $sessionId)
                    ->where('user_id', $userId)
                    ->first();

                if (!$session) {
                    $sessionId = null;
                }
            }

            $values = [
                'session_id' => $sessionId,
                'is_enabled' => $isEnabled,
                'mode' => $mode,
                'reply_message' => $replyMessage,
                'reply_once' => (bool) ($validated['reply_once'] ?? false),
                'delay_seconds' => (int) ($validated['delay_seconds'] ?? 0),
                'ai_system_prompt' => $validated['ai_system_prompt'] ?? null,
                'ai_business_info' => $validated['ai_business_info'] ?? null,
                'ai_tone' => $validated['ai_tone'] ?? null,
                'ai_language' => $validated['ai_language'] ?? null,
                'ai_context_limit' => (int) ($validated['ai_context_limit'] ?? 10),
                'ai_fallback_enabled' => (bool) ($validated['ai_fallback_enabled'] ?? false),
                'ai_fallback_message' => $validated['ai_fallback_message'] ?? null,
                'ai_max_daily_replies' => (int) ($validated['ai_max_daily_replies'] ?? 0),
                'ai_business_hours_enabled' => (bool) ($validated['ai_business_hours_enabled'] ?? false),
                'ai_business_hours_from' => $validated['ai_business_hours_from'] ?? null,
                'ai_business_hours_to' => $validated['ai_business_hours_to'] ?? null,
                'ai_model' => $validated['ai_model'] ?? null,
                'ai_handoff_minutes' => (int) ($validated['ai_handoff_minutes'] ?? 0),
            ];

            $autoReply = WhatsAppAutoReply::updateOrCreate(
                ['user_id' => $userId],
                $values
            );

            $autoReply->load('session:id,phone_number,device_name,status');

            return response()->json([
                'success' => true,
                'message' => 'تم حفظ إعدادات الرد التلقائي بنجاح.',
                'data' => [
                    'auto_reply' => $this->serializeAutoReply($autoReply),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first(),
                'errors' => $e->validator->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حفظ الإعدادات: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test AI reply for a sample message.
     */
    public function testAI(Request $request, AIReplyService $ai)
    {
        try {
            $userId = Auth::id();

            $validated = $request->validate([
                'test_message' => 'required|string|max:2000',
                'session_id' => 'required|integer',
            ]);

            $autoReply = WhatsAppAutoReply::where('user_id', $userId)
                ->where('session_id', $validated['session_id'])
                ->first();

            if (!$autoReply) {
                $autoReply = WhatsAppAutoReply::where('user_id', $userId)->first();
            }

            if (!$autoReply || $autoReply->mode !== 'ai') {
                return response()->json([
                    'success' => false,
                    'message' => 'وضع AI غير مفعّل أو لم يتم إعداده.',
                ], 422);
            }

            $result = $ai->test($autoReply, $validated['test_message'], []);

            return response()->json([
                'success' => true,
                'data' => [
                    'reply' => $result['reply'],
                    'model' => $result['model'],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('AI test failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'فشل اختبار AI: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get auto reply activity logs.
     */
    public function getLogs(Request $request)
    {
        try {
            $userId = Auth::id();

            $logs = WhatsAppAutoReplyLog::with(['session:id,phone_number,device_name', 'outgoingMessage:id,status,sent_at,delivered_at,failed_at,error_message'])
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->paginate(25);

            return response()->json([
                'success' => true,
                'data' => $logs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب سجلات الرد التلقائي: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function serializeAutoReply(WhatsAppAutoReply $autoReply): array
    {
        return [
            'id' => $autoReply->id,
            'is_enabled' => (bool) $autoReply->is_enabled,
            'mode' => $autoReply->mode,
            'session_id' => $autoReply->session_id,
            'reply_message' => $autoReply->reply_message ?? '',
            'reply_once' => (bool) $autoReply->reply_once,
            'delay_seconds' => (int) $autoReply->delay_seconds,
            'total_replies_sent' => (int) $autoReply->total_replies_sent,
            'last_reply_at' => $autoReply->last_reply_at,
            'session' => $autoReply->session,
            'ai_system_prompt' => $autoReply->ai_system_prompt ?? '',
            'ai_business_info' => $autoReply->ai_business_info ?? '',
            'ai_tone' => $autoReply->ai_tone ?? 'friendly',
            'ai_language' => $autoReply->ai_language ?? 'auto',
            'ai_context_limit' => (int) $autoReply->ai_context_limit,
            'ai_fallback_enabled' => (bool) $autoReply->ai_fallback_enabled,
            'ai_fallback_message' => $autoReply->ai_fallback_message ?? '',
            'ai_max_daily_replies' => (int) $autoReply->ai_max_daily_replies,
            'ai_total_replies_sent' => (int) $autoReply->ai_total_replies_sent,
            'ai_daily_replies_sent' => (int) $autoReply->ai_daily_replies_sent,
            'ai_last_reply_at' => $autoReply->ai_last_reply_at,
            'ai_business_hours_enabled' => (bool) $autoReply->ai_business_hours_enabled,
            'ai_business_hours_from' => $autoReply->ai_business_hours_from ? substr($autoReply->ai_business_hours_from, 0, 5) : null,
            'ai_business_hours_to' => $autoReply->ai_business_hours_to ? substr($autoReply->ai_business_hours_to, 0, 5) : null,
            'ai_model' => $autoReply->ai_model ?: (config('services.gemini.api_key') ? config('services.gemini.default_model') : config('services.openrouter.default_model')),
            'ai_handoff_minutes' => (int) $autoReply->ai_handoff_minutes,
        ];
    }
}
