<?php

namespace App\Modules\WhatsApp\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MetaWhatsAppCampaign;
use App\Models\MetaWhatsAppCampaignRecipient;
use App\Models\MetaWhatsAppPhoneNumber;
use App\Models\MetaWhatsAppTemplate;
use App\Services\MetaWhatsAppService;
use App\Services\PhoneNormalizer;
use App\Services\SubscriptionService;
use App\Services\ContactFileParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class MetaWhatsAppCampaignController extends Controller
{
    protected MetaWhatsAppService $service;
    protected SubscriptionService $subscriptionService;

    public function __construct(MetaWhatsAppService $service, SubscriptionService $subscriptionService)
    {
        $this->service = $service;
        $this->subscriptionService = $subscriptionService;
    }

    public function index()
    {
        $campaigns = MetaWhatsAppCampaign::where('user_id', Auth::id())
            ->with(['phoneNumber:id,meta_phone_number_id,phone_number', 'template:id,name,status'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $campaigns]);
    }

    public function stats()
    {
        $totals = MetaWhatsAppCampaign::where('user_id', Auth::id())
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "running" THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed,
                COALESCE(SUM(total_recipients), 0) as total_messages,
                COALESCE(SUM(sent_count), 0) as sent,
                COALESCE(SUM(delivered_count), 0) as delivered,
                COALESCE(SUM(read_count), 0) as read,
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

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone_number_id' => 'required|integer|exists:meta_whatsapp_phone_numbers,id',
            'template_id' => 'nullable|integer|exists:meta_whatsapp_templates,id',
            'message_type' => 'required|string|in:marketing,utility,authentication',
            'recipients' => 'sometimes|array',
            'recipients.*.name' => 'nullable|string',
            'recipients.*.phone' => 'required_with:recipients|string',
            'opt_in_confirmed' => 'sometimes|boolean',
        ]);

        $phone = MetaWhatsAppPhoneNumber::where('id', $data['phone_number_id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $templateId = $data['template_id'] ?? null;
        if ($data['message_type'] === 'marketing' && !$templateId) {
            return response()->json(['success' => false, 'message' => 'Marketing campaigns require an approved template.'], 400);
        }

        $campaign = MetaWhatsAppCampaign::create([
            'user_id' => Auth::id(),
            'connection_id' => $phone->waba->connection_id,
            'waba_id' => $phone->waba_id,
            'phone_number_id' => $phone->id,
            'template_id' => $templateId,
            'name' => $data['name'],
            'message_type' => $data['message_type'],
            'status' => MetaWhatsAppCampaign::STATUS_DRAFT,
            'opt_in_confirmed' => $data['opt_in_confirmed'] ?? false,
        ]);

        if (!empty($data['recipients'])) {
            $this->saveRecipients($request, $campaign->id);
        }

        return response()->json(['success' => true, 'data' => $campaign], 201);
    }

    public function show($id)
    {
        $campaign = MetaWhatsAppCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->with(['phoneNumber', 'template', 'recipients' => function ($q) {
                $q->orderBy('id')->limit(50);
            }])
            ->firstOrFail();

        return response()->json(['success' => true, 'data' => $campaign]);
    }

    public function update(Request $request, $id)
    {
        $campaign = MetaWhatsAppCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (!in_array($campaign->status, [MetaWhatsAppCampaign::STATUS_DRAFT, MetaWhatsAppCampaign::STATUS_PAUSED])) {
            return response()->json(['success' => false, 'message' => 'Cannot edit campaign in this state.'], 400);
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone_number_id' => 'sometimes|integer|exists:meta_whatsapp_phone_numbers,id',
            'template_id' => 'sometimes|integer|exists:meta_whatsapp_templates,id',
            'message_type' => 'sometimes|string|in:marketing,utility,authentication',
        ]);

        $campaign->update($data);

        return response()->json(['success' => true, 'data' => $campaign]);
    }

    public function destroy($id)
    {
        $campaign = MetaWhatsAppCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (!in_array($campaign->status, [MetaWhatsAppCampaign::STATUS_DRAFT, MetaWhatsAppCampaign::STATUS_FAILED])) {
            return response()->json(['success' => false, 'message' => 'Only drafts or failed campaigns can be deleted.'], 400);
        }

        $campaign->delete();

        return response()->json(['success' => true, 'message' => 'Campaign deleted.']);
    }

    public function saveRecipients(Request $request, $id)
    {
        $campaign = MetaWhatsAppCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $data = $request->validate([
            'recipients' => 'required|array',
            'recipients.*.name' => 'nullable|string',
            'recipients.*.phone' => 'required|string',
            'recipients.*.variables' => 'sometimes|array',
        ]);

        $recipients = $data['recipients'];

        DB::transaction(function () use ($campaign, $recipients) {
            $campaign->recipients()->delete();

            $inserts = [];
            $now = now();
            foreach ($recipients as $r) {
                $phone = $this->normalizePhone($r['phone'] ?? '');
                if (empty($phone) || strlen($phone) < 7) {
                    continue;
                }
                $inserts[] = [
                    'campaign_id' => $campaign->id,
                    'name' => $r['name'] ?? null,
                    'phone' => $phone,
                    'is_valid' => true,
                    'status' => MetaWhatsAppCampaignRecipient::STATUS_PENDING,
                    'variables' => $r['variables'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($inserts)) {
                foreach (array_chunk($inserts, 500) as $chunk) {
                    MetaWhatsAppCampaignRecipient::insert($chunk);
                }
            }

            $total = count($inserts);
            $campaign->update([
                'total_recipients' => $total,
                'valid_recipients' => $total,
                'invalid_recipients' => count($recipients) - $total,
                'pending_count' => $total,
            ]);
        });

        return response()->json(['success' => true, 'data' => $campaign]);
    }

    public function validateRecipients(Request $request)
    {
        $data = $request->validate([
            'recipients' => 'sometimes|array',
            'recipients.*.name' => 'nullable|string',
            'recipients.*.phone' => 'required|string',
            'file' => 'sometimes|file|extensions:csv,xlsx,xls,txt,vcf',
        ]);

        $rows = [];

        if ($request->hasFile('file')) {
            $rows = $this->parseFile($request->file('file'));
        } elseif (!empty($data['recipients'])) {
            $rows = $data['recipients'];
        }

        $seen = [];
        $valid = [];
        $invalid = [];
        $duplicates = [];

        foreach ($rows as $row) {
            $name = trim($row['name'] ?? '');
            $phone = $this->normalizePhone($row['phone'] ?? '');

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

    public function launch(Request $request, $id)
    {
        $user = Auth::user();
        $campaign = MetaWhatsAppCampaign::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if (!in_array($campaign->status, [MetaWhatsAppCampaign::STATUS_DRAFT, MetaWhatsAppCampaign::STATUS_PAUSED, MetaWhatsAppCampaign::STATUS_PENDING])) {
            return response()->json(['success' => false, 'message' => 'Campaign cannot be launched.'], 400);
        }

        if (!$campaign->opt_in_confirmed) {
            return response()->json(['success' => false, 'message' => 'Opt-in must be confirmed.'], 400);
        }

        $phone = MetaWhatsAppPhoneNumber::where('id', $campaign->phone_number_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$phone || !$phone->is_active) {
            return response()->json(['success' => false, 'message' => 'Selected WhatsApp number is not active.'], 400);
        }

        $connection = $phone->waba->connection;
        if (!$connection) {
            return response()->json(['success' => false, 'message' => 'Connection not found.'], 400);
        }

        $recipients = $campaign->recipients()->where('is_valid', true)->get();
        if ($recipients->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No recipients.'], 400);
        }

        $available = $this->subscriptionService->getRemainingMessages($user);
        if ($available < $recipients->count()) {
            return response()->json(['success' => false, 'message' => 'Insufficient message credits.'], 403);
        }

        $template = $campaign->template;

        $campaign->update([
            'status' => MetaWhatsAppCampaign::STATUS_RUNNING,
            'started_at' => now(),
            'paused_at' => null,
        ]);

        $useTemplate = $campaign->message_type === 'marketing' || ($template && $template->status === MetaWhatsAppTemplate::STATUS_APPROVED);

        foreach ($recipients as $recipient) {
            try {
                if ($useTemplate && $template) {
                    $components = $this->buildTemplateComponents($template, $recipient);
                    $result = $this->service->sendTemplateMessage(
                        $phone->meta_phone_number_id,
                        $recipient->phone,
                        $template->name,
                        $template->language,
                        $connection->access_token,
                        $components
                    );
                } else {
                    $text = $this->renderMessage($request->input('text', ''), $recipient->name);
                    $result = $this->service->sendTextMessage(
                        $phone->meta_phone_number_id,
                        $recipient->phone,
                        $text,
                        $connection->access_token
                    );
                }

                $recipient->update([
                    'status' => MetaWhatsAppCampaignRecipient::STATUS_SENT,
                    'meta_message_id' => $result['messages'][0]['id'] ?? null,
                    'sent_at' => now(),
                    'attempts' => 1,
                ]);
            } catch (Exception $e) {
                $recipient->update([
                    'status' => MetaWhatsAppCampaignRecipient::STATUS_FAILED,
                    'failed_at' => now(),
                    'attempts' => $recipient->attempts + 1,
                    'error_message' => $e->getMessage(),
                ]);
            }
        }

        $campaign->recomputeStats();

        return response()->json(['success' => true, 'message' => 'Campaign launched.', 'data' => $campaign]);
    }

    public function pause($id)
    {
        $campaign = MetaWhatsAppCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if ($campaign->status !== MetaWhatsAppCampaign::STATUS_RUNNING) {
            return response()->json(['success' => false, 'message' => 'Campaign is not running.'], 400);
        }

        $campaign->update([
            'status' => MetaWhatsAppCampaign::STATUS_PAUSED,
            'paused_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Campaign paused.']);
    }

    public function recipients($id)
    {
        $campaign = MetaWhatsAppCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $recipients = $campaign->recipients()
            ->orderBy('id')
            ->paginate(50);

        return response()->json(['success' => true, 'data' => $recipients]);
    }

    protected function buildTemplateComponents(MetaWhatsAppTemplate $template, MetaWhatsAppCampaignRecipient $recipient): array
    {
        $components = [];
        $templateComponents = $template->components ?? [];

        foreach ($templateComponents as $component) {
            if ($component['type'] === 'BODY') {
                $components[] = [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $recipient->name ?? 'عميل'],
                    ],
                ];
            }
        }

        return $components;
    }

    protected function normalizePhone(string $phone): string
    {
        return PhoneNormalizer::normalize($phone);
    }

    protected function renderMessage(?string $template, ?string $name): string
    {
        if (empty($name)) {
            $name = 'عزيزي العميل';
        }
        $firstName = trim(explode(' ', trim($name))[0]);
        return str_replace('{{name}}', $firstName, $template ?? '');
    }

    protected function parseFile($file): array
    {
        return ContactFileParser::parse($file);
    }

    protected function parseCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return $rows;
        }

        $headers = array_map('strtolower', array_map('trim', fgetcsv($handle) ?? []));
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 1) {
                continue;
            }
            $phoneIndex = array_search('phone', $headers) !== false ? array_search('phone', $headers) : 0;
            $nameIndex = array_search('name', $headers);
            $rows[] = [
                'name' => $nameIndex !== false ? trim($row[$nameIndex]) : null,
                'phone' => trim($row[$phoneIndex] ?? ''),
            ];
        }
        fclose($handle);
        return $rows;
    }

    protected function parseXlsx(string $path): array
    {
        // Placeholder: install a spreadsheet library for full XLSX support
        return [];
    }

    protected function parseVcf(string $path): array
    {
        return [];
    }
}
