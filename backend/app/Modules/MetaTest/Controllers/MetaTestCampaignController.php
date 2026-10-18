<?php

namespace App\Modules\MetaTest\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMetaTestCampaignJob;
use App\Modules\MetaTest\Models\MetaTestCampaign;
use App\Modules\MetaTest\Models\MetaTestCampaignRecipient;
use App\Modules\MetaTest\Models\MetaTestPhoneNumber;
use App\Modules\MetaTest\Models\MetaTestTemplate;
use App\Services\ContactFileParser;
use App\Services\PhoneNormalizer;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MetaTestCampaignController extends Controller
{
    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    public function index(): JsonResponse
    {
        $campaigns = MetaTestCampaign::where('user_id', Auth::id())
            ->with(['phoneNumber:id,phone_number,display_name', 'template:id,name,status,category'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $campaigns]);
    }

    public function stats(): JsonResponse
    {
        $totals = MetaTestCampaign::where('user_id', Auth::id())
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "running" THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed,
                COALESCE(SUM(total_recipients), 0) as total_messages,
                COALESCE(SUM(sent_count), 0) as sent,
                COALESCE(SUM(delivered_count), 0) as delivered,
                COALESCE(SUM(read_count), 0) as `read`,
                COALESCE(SUM(failed_count), 0) as failed
            ')
            ->first();

        $subscription = $this->subscriptionService->getUsageStats(Auth::user());

        return response()->json([
            'success' => true,
            'data' => [
                'total' => (int) $totals->total,
                'active' => (int) $totals->active,
                'completed' => (int) $totals->completed,
                'total_messages' => (int) $totals->total_messages,
                'sent' => (int) $totals->sent,
                'delivered' => (int) $totals->delivered,
                'read' => (int) $totals->read,
                'failed' => (int) $totals->failed,
                'credits_remaining' => (int) ($subscription['messages_remaining'] ?? 0),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone_number_id' => 'required|integer|exists:meta_test_phone_numbers,id',
            'template_id' => 'nullable|integer|exists:meta_test_templates,id',
            'message_type' => 'required|string|in:marketing,utility,authentication,text,auto',
            'text' => 'nullable|string|max:4096',
            'delay_seconds' => 'sometimes|integer|min:1|max:120',
            'recipients' => 'sometimes|array|max:50000',
            'recipients.*.name' => 'nullable|string|max:255',
            'recipients.*.phone' => 'required_with:recipients|string',
            'recipients.*.variables' => 'sometimes|array',
            'opt_in_confirmed' => 'sometimes|boolean',
        ]);

        $phone = MetaTestPhoneNumber::where('id', $data['phone_number_id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if ($data['message_type'] === 'text' && empty($data['text'])) {
            return response()->json(['success' => false, 'message' => 'Text campaigns require a message body.'], 422);
        }

        if ($data['message_type'] !== 'text' && empty($data['template_id'])) {
            return response()->json(['success' => false, 'message' => 'An approved template is required.'], 422);
        }

        $campaign = MetaTestCampaign::create([
            'user_id' => Auth::id(),
            'connection_id' => $phone->waba->connection_id,
            'waba_id' => $phone->waba_id,
            'phone_number_id' => $phone->id,
            'template_id' => $data['template_id'] ?? null,
            'name' => $data['name'],
            'message_type' => $data['message_type'],
            'text' => $data['text'] ?? null,
            'status' => MetaTestCampaign::STATUS_DRAFT,
            'delay_seconds' => $data['delay_seconds'] ?? 2,
            'opt_in_confirmed' => $data['opt_in_confirmed'] ?? false,
        ]);

        if (!empty($data['recipients'])) {
            $this->saveRecipients($request, $campaign->id);
        }

        return response()->json(['success' => true, 'data' => $campaign->fresh()], 201);
    }

    public function show(int $id): JsonResponse
    {
        $campaign = MetaTestCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->with(['phoneNumber', 'template'])
            ->firstOrFail();

        return response()->json(['success' => true, 'data' => $campaign]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $campaign = MetaTestCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (!in_array($campaign->status, [MetaTestCampaign::STATUS_DRAFT, MetaTestCampaign::STATUS_PAUSED], true)) {
            return response()->json(['success' => false, 'message' => 'Cannot edit campaign in this state.'], 400);
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'template_id' => 'sometimes|nullable|integer|exists:meta_test_templates,id',
            'text' => 'sometimes|nullable|string|max:4096',
            'delay_seconds' => 'sometimes|integer|min:1|max:120',
            'opt_in_confirmed' => 'sometimes|boolean',
        ]);

        $campaign->update($data);

        return response()->json(['success' => true, 'data' => $campaign]);
    }

    public function destroy(int $id): JsonResponse
    {
        $campaign = MetaTestCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (!in_array($campaign->status, [MetaTestCampaign::STATUS_DRAFT, MetaTestCampaign::STATUS_FAILED, MetaTestCampaign::STATUS_COMPLETED], true)) {
            return response()->json(['success' => false, 'message' => 'Only draft, failed, or completed campaigns can be deleted.'], 400);
        }

        $campaign->delete();

        return response()->json(['success' => true, 'message' => 'Campaign deleted.']);
    }

    public function saveRecipients(Request $request, int $id): JsonResponse
    {
        $campaign = MetaTestCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $data = $request->validate([
            'recipients' => 'required|array|max:50000',
            'recipients.*.name' => 'nullable|string|max:255',
            'recipients.*.phone' => 'required|string',
            'recipients.*.variables' => 'sometimes|array',
            'recipients.*.text' => 'nullable|string|max:4096',
        ]);

        $recipients = $data['recipients'];

        DB::transaction(function () use ($campaign, $recipients) {
            $campaign->recipients()->delete();

            $seen = [];
            $inserts = [];
            $invalid = 0;
            $now = now();

            foreach ($recipients as $r) {
                $phone = PhoneNormalizer::normalize($r['phone'] ?? '');
                if (empty($phone) || strlen($phone) < 7 || isset($seen[$phone])) {
                    $invalid++;
                    continue;
                }
                $seen[$phone] = true;
                $inserts[] = [
                    'campaign_id' => $campaign->id,
                    'name' => $r['name'] ?? null,
                    'phone' => $phone,
                    'is_valid' => true,
                    'status' => MetaTestCampaignRecipient::STATUS_PENDING,
                    'variables' => isset($r['variables']) ? json_encode($r['variables']) : null,
                    'custom_text' => $r['text'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($inserts, 500) as $chunk) {
                MetaTestCampaignRecipient::insert($chunk);
            }

            $campaign->update([
                'total_recipients' => count($inserts),
                'valid_recipients' => count($inserts),
                'invalid_recipients' => $invalid,
                'pending_count' => count($inserts),
            ]);
        });

        return response()->json(['success' => true, 'data' => $campaign->fresh()]);
    }

    public function validateRecipients(Request $request): JsonResponse
    {
        $data = $request->validate([
            'recipients' => 'sometimes|array|max:50000',
            'recipients.*.name' => 'nullable|string',
            'recipients.*.phone' => 'required|string',
            'file' => 'sometimes|file|extensions:csv,xlsx,xls,txt,vcf|max:20480',
        ]);

        $rows = $request->hasFile('file')
            ? ContactFileParser::parse($request->file('file'))
            : ($data['recipients'] ?? []);

        if ($request->hasFile('file') && empty($rows)) {
            return response()->json([
                'success' => false,
                'message' => 'No readable contacts found in this file. Use a CSV/TXT with a phone column, or a VCF exported from your phone contacts.',
            ], 422);
        }

        $seen = [];
        $valid = [];
        $invalid = [];
        $duplicates = [];

        foreach ($rows as $row) {
            $name = trim($row['name'] ?? '');
            $phone = PhoneNormalizer::normalize($row['phone'] ?? '');

            if (empty($phone) || strlen($phone) < 7) {
                $invalid[] = ['name' => $name, 'phone' => $row['phone'] ?? '', 'reason' => 'invalid_phone'];
                continue;
            }

            if (isset($seen[$phone])) {
                $duplicates[] = ['name' => $name, 'phone' => $phone];
                continue;
            }

            $seen[$phone] = true;
            $valid[] = ['name' => $name, 'phone' => $phone];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total' => count($rows),
                'valid' => $valid,
                'invalid' => $invalid,
                'duplicates' => $duplicates,
                'valid_count' => count($valid),
                'invalid_count' => count($invalid),
                'duplicate_count' => count($duplicates),
            ],
        ]);
    }

    public function launch(int $id): JsonResponse
    {
        $user = Auth::user();
        $campaign = MetaTestCampaign::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if (!in_array($campaign->status, [MetaTestCampaign::STATUS_DRAFT, MetaTestCampaign::STATUS_PAUSED], true)) {
            return response()->json(['success' => false, 'message' => 'Campaign cannot be launched.'], 400);
        }

        if (!$campaign->opt_in_confirmed) {
            return response()->json(['success' => false, 'message' => 'Recipient opt-in must be confirmed.'], 400);
        }

        $phone = MetaTestPhoneNumber::where('id', $campaign->phone_number_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$phone || !$phone->is_active) {
            return response()->json(['success' => false, 'message' => 'Selected WhatsApp number is not active.'], 400);
        }

        $pending = $campaign->recipients()->where('status', MetaTestCampaignRecipient::STATUS_PENDING)->count();
        if ($pending === 0) {
            return response()->json(['success' => false, 'message' => 'No pending recipients.'], 400);
        }

        $available = $this->subscriptionService->getRemainingMessages($user);
        if ($available < $pending) {
            return response()->json([
                'success' => false,
                'message' => "Insufficient message credits. Needed: {$pending}, available: {$available}.",
            ], 403);
        }

        $campaign->update([
            'status' => MetaTestCampaign::STATUS_RUNNING,
            'started_at' => $campaign->started_at ?? now(),
            'paused_at' => null,
        ]);

        ProcessMetaTestCampaignJob::dispatch($campaign->id);

        return response()->json(['success' => true, 'message' => 'Campaign launched.', 'data' => $campaign->fresh()]);
    }

    public function pause(int $id): JsonResponse
    {
        $campaign = MetaTestCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if ($campaign->status !== MetaTestCampaign::STATUS_RUNNING) {
            return response()->json(['success' => false, 'message' => 'Campaign is not running.'], 400);
        }

        $campaign->update([
            'status' => MetaTestCampaign::STATUS_PAUSED,
            'paused_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Campaign paused.']);
    }

    public function recipients(int $id): JsonResponse
    {
        $campaign = MetaTestCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $campaign->recipients()->orderBy('id')->paginate(50),
        ]);
    }
}
