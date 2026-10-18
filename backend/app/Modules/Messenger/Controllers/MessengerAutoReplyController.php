<?php

namespace App\Modules\Messenger\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MessengerAutoReply;
use App\Models\MessengerAutoReplyLog;
use App\Models\MessengerPage;
use App\Models\WhatsAppAutoReply;
use App\Services\AIReplyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessengerAutoReplyController extends Controller
{
    protected AIReplyService $aiReply;

    public function __construct(AIReplyService $aiReply)
    {
        $this->aiReply = $aiReply;
    }

    public function getSettings(Request $request)
    {
        $userId = Auth::id();
        $pageId = $request->input('page_id');

        $page = MessengerPage::where('id', $pageId)
            ->where('user_id', $userId)
            ->first();

        if (!$page) {
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

        $autoReply = MessengerAutoReply::firstOrCreate(
            ['user_id' => $userId, 'page_id' => $page->id],
            [
                'is_enabled' => false,
                'mode' => 'ai',
                'ai_tone' => 'friendly',
                'ai_language' => 'ar',
                'ai_system_prompt' => 'أنت مساعد ذكي ومحترف لخدمة العملاء على Messenger. رد بأدب واختصار وباللغة التي يتحدث بها العميل.',
                'welcome_message' => 'أهلاً بك! كيف يمكنني مساعدتك اليوم؟',
                'delay_seconds' => 1,
            ]
        );

        $totalReplies = MessengerAutoReplyLog::where('page_id', $page->id)->count();
        $aiReplies = MessengerAutoReplyLog::where('page_id', $page->id)->where('mode', 'ai')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'settings' => $autoReply,
                'page' => $page->only(['id', 'page_id', 'page_name', 'status']),
                'stats' => [
                    'total_replies' => $totalReplies,
                    'ai_total_replies' => $aiReplies,
                ],
            ],
        ]);
    }

    public function saveSettings(Request $request)
    {
        $userId = Auth::id();

        $data = $request->validate([
            'page_id' => 'required|integer|exists:messenger_pages,id',
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

        $page = MessengerPage::where('id', $data['page_id'])
            ->where('user_id', $userId)
            ->firstOrFail();

        $autoReply = MessengerAutoReply::updateOrCreate(
            ['user_id' => $userId, 'page_id' => $page->id],
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
            'message' => 'تم حفظ إعدادات الرد التلقائي بنجاح.',
            'data' => $autoReply,
        ]);
    }

    public function getLogs(Request $request)
    {
        $userId = Auth::id();
        $query = MessengerAutoReplyLog::where('user_id', $userId)
            ->with('page:id,page_id,page_name');

        if ($request->filled('page_id')) {
            $query->where('page_id', $request->page_id);
        }

        if ($request->filled('mode')) {
            $query->where('mode', $request->mode);
        }

        $logs = $query->orderByDesc('created_at')->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

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

        $sample = new WhatsAppAutoReply([
            'user_id' => Auth::id(),
            'session_id' => null,
            'ai_system_prompt' => $request->input('ai_system_prompt'),
            'ai_business_info' => $request->input('ai_business_info'),
            'ai_tone' => $request->input('ai_tone', 'friendly'),
            'ai_language' => $request->input('ai_language', 'ar'),
            'ai_model' => $request->input('ai_model'),
        ]);

        try {
            $result = $this->aiReply->test($sample, $request->input('message'));

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
                'message' => 'فشل اختبار AI: ' . $e->getMessage(),
            ], 500);
        }
    }
}
