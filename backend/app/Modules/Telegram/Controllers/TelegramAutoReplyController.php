<?php

namespace App\Modules\Telegram\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TelegramAutoReply;
use App\Models\TelegramAutoReplyLog;
use App\Models\TelegramSession;
use App\Services\AIReplyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TelegramAutoReplyController extends Controller
{
    protected AIReplyService $aiReplyService;

    public function __construct(AIReplyService $aiReplyService)
    {
        $this->aiReplyService = $aiReplyService;
    }

    /**
     * Get auto reply settings and stats for a bot session
     */
    public function getSettings(Request $request)
    {
        $userId = Auth::id();
        $sessionId = $request->input('session_id');

        $session = null;
        if ($sessionId) {
            $session = TelegramSession::where('id', $sessionId)->where('user_id', $userId)->first();
        } else {
            $session = TelegramSession::where('user_id', $userId)->first();
        }

        if (!$session) {
            return response()->json([
                'success' => true,
                'data' => [
                    'settings' => null,
                    'stats' => [
                        'total_replies' => 0,
                        'ai_total_replies' => 0,
                    ],
                ],
            ]);
        }

        $autoReply = TelegramAutoReply::firstOrCreate(
            ['user_id' => $userId, 'telegram_session_id' => $session->id],
            [
                'is_enabled' => false,
                'mode' => 'ai',
                'ai_tone' => 'friendly',
                'ai_language' => 'ar',
                'ai_system_prompt' => 'أنت مساعد ذكي ومحترف لخدمة العملاء والرد على استفساراتهم في تيليجرام بأدب واختصار.',
                'welcome_message' => 'أهلاً بك! كيف يمكنني مساعدتك اليوم؟',
                'delay_seconds' => 1,
            ]
        );

        $totalReplies = TelegramAutoReplyLog::where('telegram_session_id', $session->id)->count();
        $aiReplies = TelegramAutoReplyLog::where('telegram_session_id', $session->id)->where('mode', 'ai')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'settings' => $autoReply,
                'session' => $session,
                'stats' => [
                    'total_replies' => $totalReplies,
                    'ai_total_replies' => $aiReplies,
                ],
            ],
        ]);
    }

    /**
     * Save auto reply settings
     */
    public function saveSettings(Request $request)
    {
        $userId = Auth::id();

        $data = $request->validate([
            'session_id' => 'required|integer|exists:telegram_sessions,id',
            'is_enabled' => 'required|boolean',
            'mode' => 'required|string|in:ai,rules',
            'welcome_message' => 'nullable|string|max:2000',
            'reply_once' => 'nullable|boolean',
            'delay_seconds' => 'nullable|integer|min:0|max:60',
            'rules' => 'nullable|array',
            'rules.*.keywords' => 'nullable|array',
            'rules.*.reply' => 'nullable|string',
            'rules.*.match_type' => 'nullable|string|in:contains,exact,starts_with',
            'ai_system_prompt' => 'nullable|string|max:5000',
            'ai_business_info' => 'nullable|string|max:5000',
            'ai_tone' => 'nullable|string|max:50',
            'ai_language' => 'nullable|string|max:10',
            'ai_model' => 'nullable|string|max:100',
            'ai_fallback_message' => 'nullable|string|max:1000',
        ]);

        $session = TelegramSession::where('id', $data['session_id'])
            ->where('user_id', $userId)
            ->firstOrFail();

        $autoReply = TelegramAutoReply::updateOrCreate(
            ['user_id' => $userId, 'telegram_session_id' => $session->id],
            [
                'is_enabled' => $data['is_enabled'],
                'mode' => $data['mode'],
                'welcome_message' => $data['welcome_message'] ?? null,
                'reply_once' => $data['reply_once'] ?? false,
                'delay_seconds' => $data['delay_seconds'] ?? 0,
                'rules' => $data['rules'] ?? [],
                'ai_system_prompt' => $data['ai_system_prompt'] ?? null,
                'ai_business_info' => $data['ai_business_info'] ?? null,
                'ai_tone' => $data['ai_tone'] ?? 'friendly',
                'ai_language' => $data['ai_language'] ?? 'ar',
                'ai_model' => $data['ai_model'] ?? null,
                'ai_fallback_message' => $data['ai_fallback_message'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ إعدادات الرد التلقائي بنجاح!',
            'data' => $autoReply,
        ]);
    }

    /**
     * Get auto reply activity logs
     */
    public function getLogs(Request $request)
    {
        $userId = Auth::id();
        $query = TelegramAutoReplyLog::where('user_id', $userId)
            ->with('session:id,bot_username,first_name,device_name');

        if ($request->filled('session_id')) {
            $query->where('telegram_session_id', $request->session_id);
        }

        if ($request->filled('mode')) {
            $query->where('mode', $request->mode);
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Test AI response generation
     */
    public function testAI(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'ai_system_prompt' => 'nullable|string|max:5000',
            'ai_business_info' => 'nullable|string|max:5000',
            'ai_tone' => 'nullable|string|max:50',
            'ai_language' => 'nullable|string|max:10',
            'ai_model' => 'nullable|string|max:100',
        ]);

        $sampleAutoReply = new \App\Models\WhatsAppAutoReply([
            'user_id' => Auth::id(),
            'ai_system_prompt' => $request->input('ai_system_prompt'),
            'ai_business_info' => $request->input('ai_business_info'),
            'ai_tone' => $request->input('ai_tone', 'friendly'),
            'ai_language' => $request->input('ai_language', 'ar'),
            'ai_model' => $request->input('ai_model'),
        ]);

        try {
            $result = $this->aiReplyService->test($sampleAutoReply, $request->input('message'));

            return response()->json([
                'success' => true,
                'data' => [
                    'reply' => $result['reply'] ?? '',
                    'model' => $result['model'] ?? '',
                    'tokens_used' => ($result['tokens_input'] ?? 0) + ($result['tokens_output'] ?? 0),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل اختبار الذكاء الاصطناعي: ' . $e->getMessage(),
            ], 500);
        }
    }
}
