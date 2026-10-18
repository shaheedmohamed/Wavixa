<?php

namespace App\Modules\Telegram\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessTelegramCampaignJob;
use App\Models\TelegramCampaign;
use App\Models\TelegramCampaignRecipient;
use App\Models\TelegramSession;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TelegramCampaignController extends Controller
{
    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    public function index()
    {
        $campaigns = TelegramCampaign::where('user_id', Auth::id())
            ->with('session:id,bot_username,first_name,device_name')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $campaigns]);
    }

    public function stats()
    {
        $userId = Auth::id();

        $totals = TelegramCampaign::where('user_id', $userId)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "running" THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed,
                COALESCE(SUM(total_recipients), 0) as total_messages,
                COALESCE(SUM(success_count), 0) as successful,
                COALESCE(SUM(failed_count), 0) as failed,
                COALESCE(SUM(pending_count), 0) as remaining
            ')
            ->first();

        $usage = $this->subscriptionService->getUsageStats(Auth::user());

        return response()->json([
            'success' => true,
            'data' => [
                'total' => (int) $totals->total,
                'active' => (int) $totals->active,
                'completed' => (int) $totals->completed,
                'total_messages' => (int) $totals->total_messages,
                'successful' => (int) $totals->successful,
                'failed' => (int) $totals->failed,
                'remaining' => (int) $totals->remaining,
                'credits_remaining' => (int) ($usage['messages_remaining'] ?? 0),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'message' => 'required|string|max:4096',
            'session_id' => 'nullable|integer|exists:telegram_sessions,id',
            'type' => 'nullable|string|in:text,photo,document',
            'media_url' => 'nullable|string',
            'button_text' => 'nullable|string|max:64',
            'button_url' => 'nullable|url|max:255',
            'parse_mode' => 'nullable|string|in:HTML,Markdown,MarkdownV2',
            'delay_seconds' => 'nullable|integer|min:1|max:60',
            'scheduled_at' => 'nullable|date',
        ]);

        $campaign = TelegramCampaign::create([
            'user_id' => Auth::id(),
            'name' => $data['name'],
            'message' => $data['message'],
            'telegram_session_id' => $data['session_id'] ?? null,
            'type' => $data['type'] ?? 'text',
            'media_url' => $data['media_url'] ?? null,
            'button_text' => $data['button_text'] ?? null,
            'button_url' => $data['button_url'] ?? null,
            'parse_mode' => $data['parse_mode'] ?? 'HTML',
            'delay_seconds' => $data['delay_seconds'] ?? 2,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'status' => TelegramCampaign::STATUS_DRAFT,
        ]);

        return response()->json(['success' => true, 'data' => $campaign], 201);
    }

    public function show($id)
    {
        $campaign = TelegramCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->with(['session:id,bot_username,first_name,device_name', 'recipients' => function ($q) {
                $q->orderBy('id')->limit(50);
            }])
            ->firstOrFail();

        return response()->json(['success' => true, 'data' => $campaign]);
    }

    public function update(Request $request, $id)
    {
        $campaign = TelegramCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (in_array($campaign->status, [TelegramCampaign::STATUS_RUNNING, TelegramCampaign::STATUS_COMPLETED])) {
            return response()->json(['success' => false, 'message' => 'لا يمكن تعديل حملة جارية أو مكتملة.'], 422);
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'message' => 'sometimes|string|max:4096',
            'session_id' => 'nullable|integer|exists:telegram_sessions,id',
            'type' => 'nullable|string|in:text,photo,document',
            'media_url' => 'nullable|string',
            'button_text' => 'nullable|string|max:64',
            'button_url' => 'nullable|url|max:255',
            'parse_mode' => 'nullable|string|in:HTML,Markdown,MarkdownV2',
            'delay_seconds' => 'nullable|integer|min:1|max:60',
            'scheduled_at' => 'nullable|date',
        ]);

        if (isset($data['session_id'])) {
            $data['telegram_session_id'] = $data['session_id'];
            unset($data['session_id']);
        }

        $campaign->update($data);

        return response()->json(['success' => true, 'data' => $campaign]);
    }

    public function destroy($id)
    {
        $campaign = TelegramCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $campaign->delete();

        return response()->json(['success' => true, 'message' => 'تم حذف الحملة بنجاح.']);
    }

    public function validateRecipients(Request $request, $id)
    {
        $request->validate([
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'required|string',
        ]);

        $rawRecipients = $request->input('recipients', []);
        $valid = [];
        $invalid = [];

        foreach ($rawRecipients as $r) {
            $chatId = trim((string)$r);
            if (empty($chatId)) continue;

            // Telegram chat ID can be numbers (e.g. 123456789, -100123456789) or @username
            if (preg_match('/^(@[a-zA-Z0-9_]{3,32}|-?[0-9]{5,20})$/', $chatId)) {
                $valid[] = $chatId;
            } else {
                $invalid[] = $chatId;
            }
        }

        $unique = array_values(array_unique($valid));

        return response()->json([
            'success' => true,
            'data' => [
                'total' => count($rawRecipients),
                'valid_count' => count($unique),
                'invalid_count' => count($invalid),
                'duplicates' => count($valid) - count($unique),
                'sample_valid' => array_slice($unique, 0, 5),
                'sample_invalid' => array_slice($invalid, 0, 5),
            ],
        ]);
    }

    public function saveRecipients(Request $request, $id)
    {
        $campaign = TelegramCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $request->validate([
            'recipients' => 'required|array|min:1',
            'recipients.*.chat_id' => 'required|string',
            'recipients.*.name' => 'nullable|string',
        ]);

        $recipientsData = $request->input('recipients', []);

        DB::transaction(function () use ($campaign, $recipientsData) {
            TelegramCampaignRecipient::where('campaign_id', $campaign->id)->delete();

            $now = now();
            $insertData = [];
            $seen = [];

            foreach ($recipientsData as $r) {
                $chatId = trim((string)($r['chat_id'] ?? ''));
                if (empty($chatId) || isset($seen[$chatId])) continue;
                $seen[$chatId] = true;

                $insertData[] = [
                    'campaign_id' => $campaign->id,
                    'chat_id' => $chatId,
                    'name' => $r['name'] ?? null,
                    'status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($insertData, 500) as $chunk) {
                TelegramCampaignRecipient::insert($chunk);
            }

            $count = count($insertData);
            $campaign->update([
                'total_recipients' => $count,
                'pending_count' => $count,
                'success_count' => 0,
                'failed_count' => 0,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ جهات الاتصال بنجاح.',
            'total' => $campaign->fresh()->total_recipients,
        ]);
    }

    public function recipients(Request $request, $id)
    {
        $campaign = TelegramCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $query = $campaign->recipients();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->input('per_page', 50)),
        ]);
    }

    public function launch($id)
    {
        $campaign = TelegramCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $user = Auth::user();

        if (!$this->subscriptionService->hasRemainingMessages($user)) {
            return response()->json(['success' => false, 'message' => 'رصيدك لا يكفي لإطلاق الحملة.'], 402);
        }

        if (!$campaign->telegram_session_id) {
            $session = TelegramSession::where('user_id', $user->id)->where('status', 'connected')->latest()->first();
            if (!$session) {
                return response()->json(['success' => false, 'message' => 'يرجى ربط بوت تيليجرام أولاً لإطلاق الحملة.'], 422);
            }
            $campaign->telegram_session_id = $session->id;
        }

        if ($campaign->total_recipients === 0) {
            return response()->json(['success' => false, 'message' => 'يجب إضافة مستلمين إلى الحملة أولاً.'], 422);
        }

        $campaign->update([
            'status' => TelegramCampaign::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        // Dispatch background job to process campaign
        ProcessTelegramCampaignJob::dispatch($campaign->id);

        return response()->json([
            'success' => true,
            'message' => 'تم إطلاق حملة التيليجرام بنجاح وهي قيد الإرسال الآن!',
            'data' => $campaign,
        ]);
    }

    public function pause($id)
    {
        $campaign = TelegramCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $campaign->update(['status' => TelegramCampaign::STATUS_PAUSED]);

        return response()->json(['success' => true, 'message' => 'تم إيقاف الحملة مؤقتاً.']);
    }

    public function resume($id)
    {
        $campaign = TelegramCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $campaign->update(['status' => TelegramCampaign::STATUS_RUNNING]);
        ProcessTelegramCampaignJob::dispatch($campaign->id);

        return response()->json(['success' => true, 'message' => 'تم استئناف الحملة بنجاح.']);
    }

    public function resendFailed($id)
    {
        $campaign = TelegramCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $failedCount = $campaign->recipients()->where('status', 'failed')->count();
        if ($failedCount === 0) {
            return response()->json(['success' => false, 'message' => 'لا توجد رسائل فاشلة لإعادة إرسالها.'], 422);
        }

        $campaign->recipients()->where('status', 'failed')->update([
            'status' => 'pending',
            'error_message' => null,
            'sent_at' => null,
        ]);

        $campaign->update([
            'status' => TelegramCampaign::STATUS_RUNNING,
            'pending_count' => $campaign->pending_count + $failedCount,
            'failed_count' => 0,
        ]);

        ProcessTelegramCampaignJob::dispatch($campaign->id);

        return response()->json(['success' => true, 'message' => 'تمت إعادة تعيين الرسائل الفاشلة وجاري الإرسال!']);
    }
}
