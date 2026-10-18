<?php

namespace App\Modules\WhatsApp\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppSession; 
use App\Services\PhoneNormalizer;
use App\Services\SubscriptionService;
use App\Services\ContactFileParser;
use App\Services\UserReputationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CampaignController extends Controller
{
    private $whatsappServiceUrl;
    private SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->whatsappServiceUrl = config('services.whatsapp.url', 'http://127.0.0.1:3005') . '/api/whatsapp';
        $this->subscriptionService = $subscriptionService;
    }

    private function service()
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.whatsapp.api_key'),
        ]);
    }

    public function index()
    {
        $campaigns = WhatsAppCampaign::where('user_id', Auth::id())
            ->with('session:id,phone_number,device_name')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $campaigns]);
    }

    public function stats()
    {
        $userId = Auth::id();

        $totals = WhatsAppCampaign::where('user_id', $userId)
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

        $subscription = $this->subscriptionService->getUsageStats(Auth::user());

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
                'credits_remaining' => (int) $subscription['messages_remaining'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        if ($user && !$user->is_admin && !UserReputationService::canRunCampaigns($user)) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إنشاء حملات جديدة بسبب سلوك عالي المخاطر. يرجى مراجعة الدعم الفني.',
            ], 403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'message' => 'required_without:messages|nullable|string|max:4096',
            'messages' => 'sometimes|array|min:1|max:5',
            'messages.*' => 'nullable|string|max:4096',
            'session_id' => 'nullable|integer|exists:whatsapp_sessions,id',
            'delay_min' => 'nullable|integer|min:1|max:300',
            'delay_max' => 'nullable|integer|min:1|max:300',
            'concurrent' => 'nullable|integer|min:1|max:10',
            'recipients' => 'sometimes|array',
            'recipients.*.name' => 'nullable|string',
            'recipients.*.phone' => 'required_with:recipients|string',
            'status' => 'sometimes|string|in:draft,running',
        ]);

        $variations = $this->normalizeVariations($data);

        if (empty($variations)) {
            return response()->json([
                'success' => false,
                'message' => 'Message content is required.',
            ], 422);
        }

        $campaign = WhatsAppCampaign::create([
            'user_id' => Auth::id(),
            'name' => $data['name'],
            'message' => $variations[0],
            'message_variations' => $variations,
            'session_id' => $data['session_id'] ?? null,
            'delay_min' => $data['delay_min'] ?? 10,
            'delay_max' => $data['delay_max'] ?? 25,
            'concurrent' => $data['concurrent'] ?? 1,
            'status' => WhatsAppCampaign::STATUS_DRAFT,
        ]);

        if (!empty($data['recipients'])) {
            $this->saveRecipients($request, $campaign->id);
        }

        if (($data['status'] ?? 'draft') === 'running') {
            return $this->launch($request, $campaign->id);
        }

        UserReputationService::recalculateIfStale($user, 0);

        $campaign->refresh();
        return response()->json(['success' => true, 'data' => $campaign], 201);
    }

    public function show($id)
    {
        $campaign = WhatsAppCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->with(['session:id,phone_number,device_name', 'recipients' => function ($q) {
                $q->orderBy('id')->limit(50);
            }])
            ->firstOrFail();

        return response()->json(['success' => true, 'data' => $campaign]);
    }

    public function update(Request $request, $id)
    {
        $campaign = WhatsAppCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (!in_array($campaign->status, [WhatsAppCampaign::STATUS_DRAFT, WhatsAppCampaign::STATUS_PAUSED])) {
            return response()->json(['success' => false, 'message' => 'Cannot edit campaign in this state.'], 400);
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'message' => 'sometimes|nullable|string|max:4096',
            'messages' => 'sometimes|array|min:1|max:5',
            'messages.*' => 'nullable|string|max:4096',
            'session_id' => 'sometimes|integer|exists:whatsapp_sessions,id',
            'delay_min' => 'sometimes|integer|min:1|max:300',
            'delay_max' => 'sometimes|integer|min:1|max:300',
            'concurrent' => 'sometimes|integer|min:1|max:10',
        ]);

        if (array_key_exists('messages', $data)) {
            $variations = $this->normalizeVariations($data);
            if (empty($variations)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message content is required.',
                ], 422);
            }
            $data['message'] = $variations[0];
            $data['message_variations'] = $variations;
        }
        unset($data['messages']);

        $campaign->update($data);

        return response()->json(['success' => true, 'data' => $campaign]);
    }

    public function destroy($id)
    {
        $campaign = WhatsAppCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (!in_array($campaign->status, [WhatsAppCampaign::STATUS_DRAFT, WhatsAppCampaign::STATUS_FAILED])) {
            return response()->json(['success' => false, 'message' => 'Only drafts or failed campaigns can be deleted.'], 400);
        }

        $campaign->delete();

        return response()->json(['success' => true, 'message' => 'Campaign deleted.']);
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

    public function saveRecipients(Request $request, $id)
    {
        $campaign = WhatsAppCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $data = $request->validate([
            'recipients' => 'required|array',
            'recipients.*.name' => 'nullable|string',
            'recipients.*.phone' => 'required|string',
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
                    'status' => WhatsAppCampaignRecipient::STATUS_PENDING,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($inserts)) {
                foreach (array_chunk($inserts, 500) as $chunk) {
                    WhatsAppCampaignRecipient::insert($chunk);
                }
            }

            $total = count($inserts);
            $campaign->update([
                'total_recipients' => $total,
                'valid_recipients' => $total,
                'invalid_recipients' => count($recipients) - $total,
                'duplicate_recipients' => 0,
                'pending_count' => $total,
            ]);
        });

        $campaign->recomputeStats();

        return response()->json(['success' => true, 'data' => $campaign]);
    }

    public function launch(Request $request, $id)
    {
        $user = Auth::user();
        $campaign = WhatsAppCampaign::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if (!in_array($campaign->status, [WhatsAppCampaign::STATUS_DRAFT, WhatsAppCampaign::STATUS_PAUSED, WhatsAppCampaign::STATUS_PENDING])) {
            return response()->json(['success' => false, 'message' => 'Campaign cannot be launched.'], 400);
        }

        $session = WhatsAppSession::where('id', $campaign->session_id)
            ->where('user_id', $user->id)
            ->where('status', 'connected')
            ->first();

        if (!$session) {
            return response()->json(['success' => false, 'message' => 'Selected WhatsApp session is not connected.'], 400);
        }

        $recipients = $campaign->recipients()->where('is_valid', true)->where('is_duplicate', false)->get();

        if ($recipients->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No recipients.'], 400);
        }

        $available = $this->subscriptionService->getRemainingMessages($user);
        if ($available < $recipients->count()) {
            return response()->json(['success' => false, 'message' => 'Insufficient message credits.', 'data' => ['credits' => $available, 'required' => $recipients->count()]], 403);
        }

        DB::transaction(function () use ($campaign, $recipients, $session, $user) {
            // Re-create messages for pending recipients if they don't exist
            foreach ($recipients as $recipient) {
                if ($recipient->status !== WhatsAppCampaignRecipient::STATUS_PENDING) {
                    continue;
                }

                $messageBody = $this->renderMessage($this->pickMessageTemplate($campaign), $recipient->name);

                $message = WhatsAppMessage::create([
                    'user_id' => $user->id,
                    'session_id' => $session->id,
                    'campaign_id' => $campaign->id,
                    'campaign_recipient_id' => $recipient->id,
                    'from' => $session->phone_number,
                    'to' => $recipient->phone,
                    'message' => $messageBody,
                    'message_type' => 'text',
                    'direction' => 'outgoing',
                    'status' => WhatsAppMessage::STATUS_QUEUED,
                    'queued_at' => now(),
                ]);

                $recipient->update([
                    'status' => WhatsAppCampaignRecipient::STATUS_QUEUED,
                    'message_id' => $message->message_id ?? null,
                ]);

                SendWhatsAppMessageJob::dispatch($message->id)->delay(now()->addSeconds(rand(1, 5)));
            }

            $campaign->update([
                'status' => WhatsAppCampaign::STATUS_RUNNING,
                'started_at' => now(),
                'paused_at' => null,
            ]);
        });

        $campaign->recomputeStats();

        return response()->json(['success' => true, 'message' => 'Campaign started.', 'data' => $campaign]);
    }

    public function pause($id)
    {
        $campaign = WhatsAppCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if ($campaign->status !== WhatsAppCampaign::STATUS_RUNNING) {
            return response()->json(['success' => false, 'message' => 'Campaign is not running.'], 400);
        }

        $campaign->update([
            'status' => WhatsAppCampaign::STATUS_PAUSED,
            'paused_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Campaign paused.']);
    }

    public function resume($id)
    {
        $campaign = WhatsAppCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if ($campaign->status !== WhatsAppCampaign::STATUS_PAUSED) {
            return response()->json(['success' => false, 'message' => 'Campaign is not paused.'], 400);
        }

        $campaign->update(['status' => WhatsAppCampaign::STATUS_PENDING]);

        return $this->launch(request(), $id);
    }

    public function resendFailed(Request $request, $id)
    {
        $user = Auth::user();
        $campaign = WhatsAppCampaign::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $failedRecipients = $campaign->recipients()
            ->where('status', WhatsAppCampaignRecipient::STATUS_FAILED)
            ->where('is_valid', true)
            ->get();

        if ($failedRecipients->isEmpty()) {
            return response()->json(['success' => true, 'message' => 'No failed recipients to resend.']);
        }

        $session = WhatsAppSession::where('id', $campaign->session_id)
            ->where('user_id', $user->id)
            ->where('status', 'connected')
            ->first();

        if (!$session) {
            return response()->json(['success' => false, 'message' => 'Selected WhatsApp session is not connected.'], 400);
        }

        $available = $this->subscriptionService->getRemainingMessages($user);
        if ($available < $failedRecipients->count()) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient message credits.',
                'data' => ['credits' => $available, 'required' => $failedRecipients->count()],
            ], 403);
        }

        DB::transaction(function () use ($campaign, $failedRecipients, $session, $user) {
            foreach ($failedRecipients as $recipient) {
                $messageBody = $this->renderMessage($this->pickMessageTemplate($campaign), $recipient->name);

                $message = WhatsAppMessage::create([
                    'user_id' => $user->id,
                    'session_id' => $session->id,
                    'campaign_id' => $campaign->id,
                    'campaign_recipient_id' => $recipient->id,
                    'from' => $session->phone_number,
                    'to' => $recipient->phone,
                    'message' => $messageBody,
                    'message_type' => 'text',
                    'direction' => 'outgoing',
                    'status' => WhatsAppMessage::STATUS_QUEUED,
                    'queued_at' => now(),
                ]);

                $recipient->update([
                    'status' => WhatsAppCampaignRecipient::STATUS_QUEUED,
                    'error_message' => null,
                    'failed_at' => null,
                    'message_id' => null,
                ]);

                SendWhatsAppMessageJob::dispatch($message->id)->delay(now()->addSeconds(rand(1, 5)));
            }

            $campaign->update([
                'status' => WhatsAppCampaign::STATUS_RUNNING,
                'paused_at' => null,
            ]);
        });

        $campaign->recomputeStats();

        return response()->json(['success' => true, 'message' => 'Failed messages are being resent.', 'data' => $campaign]);
    }

    public function recipients($id)
    {
        $campaign = WhatsAppCampaign::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $recipients = $campaign->recipients()
            ->orderBy('id')
            ->paginate(50);

        return response()->json(['success' => true, 'data' => $recipients]);
    }

    private function normalizePhone(string $phone): string
    {
        return PhoneNormalizer::normalize($phone);
    }

    /**
     * Collect up to 5 non-empty message variations from the request payload.
     * Accepts either the new `messages` array or the legacy single `message` field.
     */
    private function normalizeVariations(array $data): array
    {
        $raw = $data['messages'] ?? [$data['message'] ?? ''];

        $variations = [];
        foreach (array_slice($raw, 0, 5) as $text) {
            $text = trim((string) $text);
            if ($text !== '') {
                $variations[] = $text;
            }
        }

        return $variations;
    }

    /**
     * Pick a random message variation for a recipient so the campaign
     * does not send identical texts to everyone.
     */
    private function pickMessageTemplate(WhatsAppCampaign $campaign): string
    {
        $variations = array_values(array_filter(
            array_map('trim', (array) ($campaign->message_variations ?? [])),
            fn ($v) => $v !== ''
        ));

        if (empty($variations)) {
            $variations = [(string) $campaign->message];
        }

        return $variations[array_rand($variations)];
    }

    private function renderMessage(string $template, ?string $name): string
    {
        $replacement = 'عزيزي العميل';

        if (!empty($name)) {
            // Use the first name only to keep the message personal but concise
            $replacement = trim(explode(' ', trim($name))[0]);
        }

        return str_replace(['{{name}}', '{name}'], $replacement, $template);
    }

    private function parseFile($file): array
    {
        return ContactFileParser::parse($file);
    }

    private function parseVcf(string $path): array
    {
        $rows = [];
        $content = file_get_contents($path);
        if ($content === false) {
            return $rows;
        }

        // Normalize line endings and unfold continuation lines (space/tab at start)
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $lines = explode("\n", $content);
        $unfolded = [];
        foreach ($lines as $line) {
            if ($unfolded === []) {
                $unfolded[] = $line;
            } elseif ($line !== '' && ($line[0] === ' ' || $line[0] === "\t")) {
                $unfolded[count($unfolded) - 1] .= substr($line, 1);
            } elseif (substr($unfolded[count($unfolded) - 1] ?? '', -1) === '=') {
                // quoted-printable soft line break continuation
                $unfolded[count($unfolded) - 1] = rtrim($unfolded[count($unfolded) - 1], '=') . $line;
            } else {
                $unfolded[] = $line;
            }
        }
        $content = implode("\n", $unfolded);

        $cards = array_map('trim', explode('BEGIN:VCARD', $content));

        foreach ($cards as $card) {
            if ($card === '' || !str_contains($card, 'END:VCARD')) {
                continue;
            }

            $name = '';
            $family = '';
            $given = '';
            $phones = [];
            $lines = explode("\n", $card);

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line === 'END:VCARD') {
                    continue;
                }

                [$key, $value] = $this->parseVcfLine($line);
                if ($key === 'FN' && $value !== '') {
                    $name = $value;
                } elseif ($key === 'N' && $value !== '') {
                    // N:Family;Given;Middle;Prefix;Suffix
                    $parts = explode(';', $value);
                    $family = trim($parts[0] ?? '');
                    $given = trim($parts[1] ?? '');
                } elseif ($key === 'TEL' && $value !== '') {
                    $phones[] = $value;
                }
            }

            if ($name === '') {
                $name = trim(($given ? $given . ' ' : '') . $family);
            }

            foreach ($phones as $phone) {
                $rows[] = [
                    'name' => $name,
                    'phone' => $phone,
                ];
            }
        }

        return $rows;
    }

    private function parseVcfLine(string $line): array
    {
        $parts = explode(':', $line, 2);
        $property = $parts[0] ?? '';
        $value = $parts[1] ?? '';

        $params = array_map('strtoupper', explode(';', $property));
        $key = $params[0];

        // Strip optional group prefix (e.g., "item1.TEL")
        if (($dot = strpos($key, '.')) !== false) {
            $key = substr($key, $dot + 1);
        }

        $charset = 'UTF-8';
        $encoding = null;
        foreach ($params as $param) {
            if (str_starts_with($param, 'CHARSET=')) {
                $charset = substr($param, 8);
            }
            if (str_starts_with($param, 'ENCODING=')) {
                $encoding = substr($param, 9);
            }
        }

        if (in_array($encoding, ['QUOTED-PRINTABLE', 'Q'])) {
            $value = $this->decodeQuotedPrintable($value, $charset);
        } elseif (strtoupper($charset) !== 'UTF-8') {
            $value = $this->convertToUtf8($value, $charset);
        }

        return [strtoupper($key), trim($value)];
    }

    private function decodeQuotedPrintable(string $value, string $charset): string
    {
        // Remove any remaining soft line breaks
        $value = str_replace("=\n", '', $value);
        $value = str_replace("=\r\n", '', $value);

        $decoded = '';
        $len = strlen($value);
        for ($i = 0; $i < $len; $i++) {
            if ($value[$i] === '=' && $i + 2 < $len) {
                $hex = substr($value, $i + 1, 2);
                if (ctype_xdigit($hex)) {
                    $decoded .= chr(hexdec($hex));
                    $i += 2;
                    continue;
                }
            }
            $decoded .= $value[$i];
        }

        return $this->convertToUtf8($decoded, $charset);
    }

    private function convertToUtf8(string $value, string $charset): string
    {
        $charset = strtoupper($charset);

        if ($charset !== 'UTF-8') {
            if (function_exists('mb_convert_encoding')) {
                $converted = @mb_convert_encoding($value, 'UTF-8', $charset);
                if ($converted !== false) {
                    $value = $converted;
                }
            } elseif (function_exists('iconv')) {
                $converted = @iconv($charset, 'UTF-8//IGNORE', $value);
                if ($converted !== false) {
                    $value = $converted;
                }
            }
        }

        // Sanitize the final string so incomplete encoded bytes don't break MySQL inserts.
        if (function_exists('mb_convert_encoding')) {
            $sanitized = @mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            if ($sanitized !== false) {
                return $sanitized;
            }
        }

        if (function_exists('iconv')) {
            $sanitized = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
            if ($sanitized !== false) {
                return $sanitized;
            }
        }

        return $value;
    }

    private function parseCsv(string $path): array
    {
        $rows = [];
        if (($handle = fopen($path, 'r')) !== false) {
            $headers = fgetcsv($handle, 0, ',', '"', '\\');
            if (!$headers) {
                fclose($handle);
                return $rows;
            }

            $headers = array_map('strtolower', array_map('trim', $headers));
            $phoneIndex = $this->findColumn($headers, ['phone', 'mobile', 'phone_number', 'رقم', 'هاتف', 'رقم الهاتف']);
            $nameIndex = $this->findColumn($headers, ['name', 'first_name', 'names', 'الاسم', 'اسم', 'الاسم الأول']);

            if ($phoneIndex === null) {
                fclose($handle);
                return $rows;
            }

            while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                $rows[] = [
                    'name' => $nameIndex !== null && isset($data[$nameIndex]) ? trim($data[$nameIndex]) : '',
                    'phone' => isset($data[$phoneIndex]) ? trim($data[$phoneIndex]) : '',
                ];
            }
            fclose($handle);
        }

        return $rows;
    }

    private function parseXlsx(string $path): array
    {
        $zip = new \ZipArchive();
        $rows = [];

        if ($zip->open($path) === true) {
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $shared = $zip->getFromName('xl/sharedStrings.xml');
            $zip->close();

            if (!$xml) {
                return $rows;
            }

            $values = [];
            if ($shared) {
                $sharedXml = simplexml_load_string($shared);
                foreach ($sharedXml->si as $si) {
                    $text = '';
                    if ($si->t) {
                        $text = (string) $si->t;
                    } else {
                        foreach ($si->r as $r) {
                            $text .= (string) ($r->t ?? '');
                        }
                    }
                    $values[] = $text;
                }
            }

            $sheet = simplexml_load_string($xml);
            $ns = $sheet->getNamespaces(true);

            $dataNode = $sheet->sheetData ?? $sheet->children($ns[''] ?? '')->sheetData;

            $headers = [];
            $phoneIndex = null;
            $nameIndex = null;

            foreach ($dataNode->row as $rowIndex => $row) {
                $rowData = [];
                foreach ($row->c as $c) {
                    $attr = $c->attributes();
                    $ref = (string) $attr['r'];
                    preg_match('/([A-Z]+)(\d+)/', $ref, $m);
                    $col = $m[1] ?? '';
                    $rowNum = (int) ($m[2] ?? 0);
                    $type = (string) ($attr['t'] ?? '');
                    $value = (string) $c->v;

                    if ($type === 's' && isset($values[(int) $value])) {
                        $value = $values[(int) $value];
                    }

                    $colIndex = $this->columnToIndex($col);
                    $rowData[$colIndex] = $value;
                }

                if ($rowIndex === 0) {
                    $headers = array_map('strtolower', array_map('trim', $rowData));
                    $phoneIndex = $this->findColumn($headers, ['phone', 'mobile', 'phone_number', 'رقم', 'هاتف', 'رقم الهاتف']);
                    $nameIndex = $this->findColumn($headers, ['name', 'first_name', 'names', 'الاسم', 'اسم', 'الاسم الأول']);
                    continue;
                }

                if ($phoneIndex !== null) {
                    $rows[] = [
                        'name' => $nameIndex !== null && isset($rowData[$nameIndex]) ? trim($rowData[$nameIndex]) : '',
                        'phone' => isset($rowData[$phoneIndex]) ? trim($rowData[$phoneIndex]) : '',
                    ];
                }
            }
        }

        return $rows;
    }

    private function findColumn(array $headers, array $needles): ?int
    {
        foreach ($headers as $index => $header) {
            foreach ($needles as $needle) {
                if (str_contains($header, strtolower($needle))) {
                    return $index;
                }
            }
        }
        return null;
    }

    private function columnToIndex(string $col): int
    {
        $index = 0;
        $len = strlen($col);
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($col[$i]) - ord('A') + 1);
        }
        return $index - 1;
    }
}
