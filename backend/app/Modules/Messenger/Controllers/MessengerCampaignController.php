<?php

namespace App\Modules\Messenger\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMessengerCampaignJob;
use App\Models\MessengerCampaign;
use App\Models\MessengerCampaignRecipient;
use App\Models\MessengerContact;
use App\Models\MessengerConversation;
use App\Models\MessengerPage;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MessengerCampaignController extends Controller
{
    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    public function index()
    {
        $campaigns = MessengerCampaign::where('user_id', Auth::id())
            ->with('page:id,page_id,page_name')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $campaigns]);
    }

    public function stats()
    {
        $userId = Auth::id();

        $totals = MessengerCampaign::where('user_id', $userId)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "running" THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed,
                COALESCE(SUM(total_recipients), 0) as total_messages,
                COALESCE(SUM(success_count), 0) as successful,
                COALESCE(SUM(failed_count), 0) as failed,
                COALESCE(SUM(excluded_count), 0) as excluded,
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
                'excluded' => (int) $totals->excluded,
                'remaining' => (int) $totals->remaining,
                'credits_remaining' => (int) ($usage['messages_remaining'] ?? 0),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
            'page_id' => 'required|integer|exists:messenger_pages,id',
            'delay_min' => 'nullable|integer|min:1|max:300',
            'delay_max' => 'nullable|integer|min:1|max:300',
            'scheduled_at' => 'nullable|date',
        ]);

        $page = MessengerPage::where('id', $data['page_id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $campaign = MessengerCampaign::create([
            'user_id' => Auth::id(),
            'page_id' => $page->id,
            'name' => $data['name'],
            'message' => $data['message'],
            'delay_min' => $data['delay_min'] ?? 2,
            'delay_max' => $data['delay_max'] ?? 5,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'status' => MessengerCampaign::STATUS_DRAFT,
        ]);

        return response()->json(['success' => true, 'data' => $campaign], 201);
    }

    public function show($id)
    {
        $campaign = MessengerCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->with(['page:id,page_id,page_name', 'recipients' => function ($q) {
                $q->orderBy('id')->limit(50);
            }])
            ->firstOrFail();

        return response()->json(['success' => true, 'data' => $campaign]);
    }

    public function update(Request $request, $id)
    {
        $campaign = MessengerCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (!in_array($campaign->status, [MessengerCampaign::STATUS_DRAFT, MessengerCampaign::STATUS_PAUSED])) {
            return response()->json(['success' => false, 'message' => 'لا يمكن تعديل حملة جارية أو مكتملة.'], 422);
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'message' => 'sometimes|string|max:2000',
            'page_id' => 'sometimes|integer|exists:messenger_pages,id',
            'delay_min' => 'sometimes|integer|min:1|max:300',
            'delay_max' => 'sometimes|integer|min:1|max:300',
            'scheduled_at' => 'nullable|date',
        ]);

        if (isset($data['page_id'])) {
            $page = MessengerPage::where('id', $data['page_id'])
                ->where('user_id', Auth::id())
                ->firstOrFail();
            $campaign->page_id = $page->id;
        }

        $campaign->update($data);

        return response()->json(['success' => true, 'data' => $campaign]);
    }

    public function destroy($id)
    {
        $campaign = MessengerCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (!in_array($campaign->status, [MessengerCampaign::STATUS_DRAFT, MessengerCampaign::STATUS_FAILED, MessengerCampaign::STATUS_CANCELLED])) {
            return response()->json(['success' => false, 'message' => 'لا يمكن حذف حملة جارية أو مكتملة.'], 422);
        }

        $campaign->delete();

        return response()->json(['success' => true, 'message' => 'تم حذف الحملة.']);
    }

    public function validateRecipients(Request $request, $id)
    {
        $pageId = null;

        if ($id && $id !== '0') {
            $campaign = MessengerCampaign::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();
            $pageId = $campaign->page_id;
        }

        if ($request->boolean('send_to_all')) {
            if (!$pageId) {
                $data = $request->validate(['page_id' => 'required|integer|exists:messenger_pages,id']);
                $page = MessengerPage::where('id', $data['page_id'])
                    ->where('user_id', Auth::id())
                    ->firstOrFail();
                $pageId = $page->id;
            }

            $count = MessengerContact::where('page_id', $pageId)->count();
            $sample = MessengerContact::where('page_id', $pageId)
                ->limit(5)
                ->get(['psid', 'name'])
                ->toArray();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $count,
                    'valid_count' => $count,
                    'invalid_count' => 0,
                    'sample_valid' => $sample,
                    'send_to_all' => true,
                ],
            ]);
        }

        $data = $request->validate([
            'recipients' => 'required|array|min:1',
            'recipients.*.psid' => 'required|string',
            'recipients.*.name' => 'nullable|string',
        ]);

        $raw = $data['recipients'];
        $valid = [];
        $invalid = [];
        $seen = [];

        foreach ($raw as $r) {
            $psid = trim((string) $r['psid']);
            if (empty($psid)) {
                $invalid[] = $r;
                continue;
            }
            if (isset($seen[$psid])) {
                continue;
            }
            $seen[$psid] = true;
            $valid[] = ['psid' => $psid, 'name' => $r['name'] ?? null];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total' => count($raw),
                'valid_count' => count($valid),
                'invalid_count' => count($invalid),
                'sample_valid' => array_slice($valid, 0, 5),
            ],
        ]);
    }

    public function saveRecipients(Request $request, $id)
    {
        $campaign = MessengerCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $sendToAll = $request->boolean('send_to_all');

        if ($sendToAll) {
            $contacts = MessengerContact::where('page_id', $campaign->page_id)
                ->get(['psid', 'name']);

            if ($contacts->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يوجد متابعون مسجّلون لهذه الصفحة.',
                ], 422);
            }

            $recipientsData = $contacts->map(fn ($c) => [
                'psid' => $c->psid,
                'name' => $c->name,
            ])->toArray();
        } else {
            $data = $request->validate([
                'recipients' => 'required|array|min:1',
                'recipients.*.psid' => 'required|string',
                'recipients.*.name' => 'nullable|string',
            ]);

            $recipientsData = $data['recipients'];
        }

        DB::transaction(function () use ($campaign, $recipientsData) {
            $campaign->recipients()->delete();

            $now = now();
            $insertData = [];
            $seen = [];

            foreach ($recipientsData as $r) {
                $psid = trim((string) $r['psid']);
                if (empty($psid) || isset($seen[$psid])) continue;
                $seen[$psid] = true;

                $insertData[] = [
                    'campaign_id' => $campaign->id,
                    'psid' => $psid,
                    'name' => $r['name'] ?? null,
                    'status' => MessengerCampaignRecipient::STATUS_PENDING,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($insertData, 500) as $chunk) {
                MessengerCampaignRecipient::insert($chunk);
            }

            $count = count($insertData);
            $campaign->update([
                'total_recipients' => $count,
                'valid_recipients' => $count,
                'invalid_recipients' => count($recipientsData) - $count,
                'pending_count' => $count,
                'success_count' => 0,
                'failed_count' => 0,
                'excluded_count' => 0,
            ]);
        });

        return response()->json([
            'success' => true,
            'data' => $campaign->fresh(),
        ]);
    }

    public function recipients(Request $request, $id)
    {
        $campaign = MessengerCampaign::where('id', $id)
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
        $campaign = MessengerCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (!in_array($campaign->status, [MessengerCampaign::STATUS_DRAFT, MessengerCampaign::STATUS_PAUSED])) {
            return response()->json(['success' => false, 'message' => 'لا يمكن إطلاق الحملة في حالتها الحالية.'], 422);
        }

        $page = $campaign->page;
        if (!$page || $page->status !== 'connected') {
            return response()->json(['success' => false, 'message' => 'الصفحة غير متصلة بـ Messenger.'], 400);
        }

        $recipients = $campaign->recipients()->where('status', MessengerCampaignRecipient::STATUS_PENDING)->get();
        if ($recipients->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'لا يوجد مستلمين صالحين.'], 422);
        }

        $user = Auth::user();
        $available = $this->subscriptionService->getRemainingMessages($user);
        if ($available < $recipients->count()) {
            return response()->json([
                'success' => false,
                'message' => 'رصيد الرسائل غير كافٍ.',
                'data' => ['credits' => $available, 'required' => $recipients->count()],
            ], 403);
        }

        $campaign->update([
            'status' => MessengerCampaign::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        ProcessMessengerCampaignJob::dispatch($campaign->id);

        return response()->json([
            'success' => true,
            'message' => 'تم إطلاق الحملة وجاري الإرسال.',
            'data' => $campaign,
        ]);
    }

    public function pause($id)
    {
        $campaign = MessengerCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if ($campaign->status !== MessengerCampaign::STATUS_RUNNING) {
            return response()->json(['success' => false, 'message' => 'الحملة ليست جارية.'], 400);
        }

        $campaign->update(['status' => MessengerCampaign::STATUS_PAUSED]);

        return response()->json(['success' => true, 'message' => 'تم إيقاف الحملة مؤقتاً.']);
    }

    public function resume($id)
    {
        return $this->launch($id);
    }

    public function resendFailed($id)
    {
        $campaign = MessengerCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $failedCount = $campaign->recipients()->where('status', MessengerCampaignRecipient::STATUS_FAILED)->count();
        if ($failedCount === 0) {
            return response()->json(['success' => false, 'message' => 'لا توجد رسائل فاشلة.'], 422);
        }

        $campaign->recipients()->where('status', MessengerCampaignRecipient::STATUS_FAILED)->update([
            'status' => MessengerCampaignRecipient::STATUS_PENDING,
            'error_message' => null,
            'sent_at' => null,
        ]);

        $campaign->update([
            'status' => MessengerCampaign::STATUS_RUNNING,
            'pending_count' => $campaign->pending_count + $failedCount,
            'failed_count' => 0,
        ]);

        ProcessMessengerCampaignJob::dispatch($campaign->id);

        return response()->json(['success' => true, 'message' => 'جاري إعادة إرسال الرسائل الفاشلة.']);
    }
}
