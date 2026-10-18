<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMetaTestCampaignJob;
use App\Modules\MetaTest\Models\MetaTestCampaign;
use App\Modules\MetaTest\Models\MetaTestCampaignRecipient;
use App\Modules\MetaTest\Models\MetaTestMessage;
use App\Modules\MetaTest\Models\MetaTestPhoneNumber;
use App\Modules\MetaTest\Models\MetaTestTemplate;
use App\Modules\MetaTest\Services\MetaTestMessagingService;
use App\Services\PhoneNormalizer;
use App\Services\SubscriptionService;
use App\Services\UserReputationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Public (X-API-Key) endpoints for the official WhatsApp Business Cloud API
 * integration. Each request maps to the key owner's Meta connection, so a
 * customer sends through their own WABA/phone numbers while Wavixa handles
 * delivery, tracking, and credits.
 */
class PublicMetaWhatsAppController extends Controller
{
    private SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    private function getUser(Request $request)
    {
        return $request->attributes->get('api_key_user');
    }

    /**
     * List the API key owner's connected official WhatsApp numbers.
     */
    public function numbers(Request $request): JsonResponse
    {
        $numbers = MetaTestPhoneNumber::where('user_id', $this->getUser($request)->id)
            ->with('waba:id,name,meta_waba_id')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'phone_number' => $n->phone_number,
                'display_name' => $n->display_name,
                'status' => $n->status,
                'quality_rating' => $n->quality_rating,
                'is_active' => $n->is_active,
                'waba' => $n->waba?->name,
            ]);

        return response()->json(['success' => true, 'data' => $numbers]);
    }

    /**
     * List the API key owner's message templates (all statuses).
     */
    public function templates(Request $request): JsonResponse
    {
        $templates = MetaTestTemplate::where('user_id', $this->getUser($request)->id)
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'language' => $t->language,
                'category' => $t->category,
                'status' => $t->status,
                'components' => $t->components,
            ]);

        return response()->json(['success' => true, 'data' => $templates]);
    }

    /**
     * Send a free-form text message (requires an open 24h customer window).
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'to' => 'required|string',
            'message' => 'required|string|max:4096',
            'phone_number_id' => 'sometimes',
            'template' => 'sometimes', // optional fallback when the 24h window is closed
        ]);

        return $this->guard($request, function ($user) use ($data, $request) {
            $phone = $this->resolvePhoneNumber($user->id, $request->input('phone_number_id'), $request);
            if (!$phone) {
                return $this->noNumberResponse();
            }

            $messaging = (new MetaTestMessagingService($user->id))
                ->setApiKeyId($request->attributes->get('api_key')?->id);
            $fallback = !empty($data['template']) ? $this->resolveTemplate($user->id, $data['template']) : null;

            try {
                $message = $messaging->sendTextMessage($phone, $data['to'], $data['message']);
            } catch (\Throwable $e) {
                if ($fallback && $this->isWindowError($e)) {
                    $message = $messaging->sendTemplateMessage($phone, $data['to'], $fallback, []);
                } else {
                    throw $e;
                }
            }

            $this->subscriptionService->consumeMessage($user);

            return response()->json([
                'success' => true,
                'message' => 'Message sent via WhatsApp Business API.',
                'data' => [
                    'message_id' => $message->id,
                    'meta_message_id' => $message->meta_message_id,
                    'to' => $message->to_phone,
                    'status' => $message->status,
                ],
            ]);
        });
    }

    /**
     * Send an approved template message (works outside the 24h window).
     */
    public function sendTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'to' => 'required|string',
            'template' => 'required', // local id or template name
            'phone_number_id' => 'sometimes',
            'language' => 'sometimes|string|max:10',
            'variables' => 'sometimes|array', // shorthand for body {{1}}..{{n}}
            'components' => 'sometimes|array', // raw WhatsApp components
        ]);

        return $this->guard($request, function ($user) use ($data, $request) {
            $phone = $this->resolvePhoneNumber($user->id, $request->input('phone_number_id'), $request);
            if (!$phone) {
                return $this->noNumberResponse();
            }

            $template = $this->resolveTemplate($user->id, $data['template']);
            if (!$template) {
                return response()->json(['success' => false, 'message' => 'Template not found.'], 404);
            }

            $components = $data['components'] ?? $this->buildBodyComponents($template, $data['variables'] ?? []);
            $messaging = (new MetaTestMessagingService($user->id))
                ->setApiKeyId($request->attributes->get('api_key')?->id);

            // Free-form first: inside the 24h window the rendered template
            // body goes out as a free "service conversation" message;
            // Meta's closed-window error falls back to the real template.
            $freeText = $this->renderTemplateText($template, $data['variables'] ?? []);
            if (!empty($freeText)) {
                try {
                    $message = $messaging->sendTextMessage($phone, $data['to'], $freeText);
                } catch (\Throwable $e) {
                    if (!$this->isWindowError($e)) {
                        throw $e;
                    }
                    $message = $messaging->sendTemplateMessage($phone, $data['to'], $template, $components);
                }
            } else {
                $message = $messaging->sendTemplateMessage($phone, $data['to'], $template, $components);
            }

            $this->subscriptionService->consumeMessage($user);

            return response()->json([
                'success' => true,
                'message' => 'Template message sent via WhatsApp Business API.',
                'data' => [
                    'message_id' => $message->id,
                    'meta_message_id' => $message->meta_message_id,
                    'to' => $message->to_phone,
                    'template' => $template->name,
                    'status' => $message->status,
                ],
            ]);
        });
    }

    /**
     * Bulk send: creates a campaign and queues delivery in the background.
     * Poll GET /campaigns/{id} for progress.
     */
    public function sendBulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone_number_id' => 'sometimes',
            'template' => 'required_without:text', // id or name
            'text' => 'required_without:template|nullable|string|max:4096',
            'type' => 'sometimes|in:auto,text,template',
            'delay_seconds' => 'sometimes|integer|min:1|max:120',
            'recipients' => 'required|array|min:1|max:50000',
            'recipients.*.phone' => 'required|string',
            'recipients.*.name' => 'nullable|string|max:255',
            'recipients.*.variables' => 'sometimes|array',
            'recipients.*.text' => 'nullable|string|max:4096',
            'opt_in_confirmed' => 'sometimes|boolean|accepted',
        ]);

        return $this->guard($request, function ($user) use ($data, $request) {
            $phone = $this->resolvePhoneNumber($user->id, $request->input('phone_number_id'), $request);
            if (!$phone) {
                return $this->noNumberResponse();
            }

            $template = null;
            if (!empty($data['template'])) {
                $template = $this->resolveTemplate($user->id, $data['template']);
                if (!$template) {
                    return response()->json(['success' => false, 'message' => 'Template not found.'], 404);
                }
                if ($template->status !== MetaTestTemplate::STATUS_APPROVED) {
                    return response()->json(['success' => false, 'message' => "Template '{$template->name}' is not approved."], 400);
                }
            }

            // Deduplicate + normalize recipients.
            $seen = [];
            $rows = [];
            foreach ($data['recipients'] as $r) {
                $p = PhoneNormalizer::normalize($r['phone'] ?? '');
                if (strlen($p) < 7 || isset($seen[$p])) {
                    continue;
                }
                $seen[$p] = true;
                $rows[] = $r + ['phone' => $p];
            }

            if (empty($rows)) {
                return response()->json(['success' => false, 'message' => 'No valid recipients.'], 422);
            }

            if ($this->subscriptionService->getRemainingMessages($user) < count($rows)) {
                return response()->json(['success' => false, 'message' => 'Insufficient message credits for this bulk send.'], 403);
            }

            $requestedType = $data['type'] ?? ($template ? 'auto' : 'text');
            if ($requestedType === 'auto' && !$template) {
                return response()->json(['success' => false, 'message' => "type 'auto' requires a template as fallback."], 422);
            }
            if ($requestedType === 'text' && empty($data['text'])) {
                return response()->json(['success' => false, 'message' => "type 'text' requires a text body."], 422);
            }

            $campaign = DB::transaction(function () use ($user, $phone, $template, $data, $rows, $requestedType, $request) {
                $campaign = MetaTestCampaign::create([
                    'user_id' => $user->id,
                    'api_key_id' => $request->attributes->get('api_key')?->id,
                    'connection_id' => $phone->waba->connection_id,
                    'waba_id' => $phone->waba_id,
                    'phone_number_id' => $phone->id,
                    'template_id' => $template?->id,
                    'name' => $data['name'] ?? 'API Bulk ' . now()->format('Y-m-d H:i'),
                    'message_type' => $requestedType === 'auto'
                        ? 'auto'
                        : ($requestedType === 'text' || !$template ? 'text' : strtolower($template->category)),
                    'text' => $data['text'] ?? null,
                    'status' => MetaTestCampaign::STATUS_RUNNING,
                    'delay_seconds' => $data['delay_seconds'] ?? 2,
                    'opt_in_confirmed' => $data['opt_in_confirmed'] ?? false,
                    'total_recipients' => count($rows),
                    'valid_recipients' => count($rows),
                    'pending_count' => count($rows),
                    'started_at' => now(),
                ]);

                $inserts = [];
                $now = now();
                foreach ($rows as $r) {
                    $inserts[] = [
                        'campaign_id' => $campaign->id,
                        'name' => $r['name'] ?? null,
                        'phone' => $r['phone'],
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

                return $campaign;
            });

            ProcessMetaTestCampaignJob::dispatch($campaign->id);

            return response()->json([
                'success' => true,
                'message' => 'Bulk send queued.',
                'data' => [
                    'campaign_id' => $campaign->id,
                    'total_recipients' => $campaign->total_recipients,
                    'status' => $campaign->status,
                ],
            ], 202);
        });
    }

    /**
     * Track a bulk send created via /send-bulk or the dashboard.
     */
    public function campaignStatus(Request $request, int $id): JsonResponse
    {
        $campaign = MetaTestCampaign::where('id', $id)
            ->where('user_id', $this->getUser($request)->id)
            ->first();

        if (!$campaign) {
            return response()->json(['success' => false, 'message' => 'Campaign not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'campaign_id' => $campaign->id,
                'name' => $campaign->name,
                'status' => $campaign->status,
                'total_recipients' => $campaign->total_recipients,
                'pending' => $campaign->pending_count,
                'sent' => $campaign->sent_count,
                'delivered' => $campaign->delivered_count,
                'read' => $campaign->read_count,
                'failed' => $campaign->failed_count,
                'started_at' => $campaign->started_at,
                'completed_at' => $campaign->completed_at,
            ],
        ]);
    }

    /**
     * Look up a sent message by local id or Meta wamid.
     */
    public function messageStatus(Request $request, string $id): JsonResponse
    {
        $userId = $this->getUser($request)->id;

        $message = is_numeric($id)
            ? MetaTestMessage::where('user_id', $userId)->find($id)
            : MetaTestMessage::where('user_id', $userId)->where('meta_message_id', $id)->first();

        if (!$message) {
            return response()->json(['success' => false, 'message' => 'Message not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'message_id' => $message->id,
                'meta_message_id' => $message->meta_message_id,
                'to' => $message->to_phone,
                'type' => $message->message_type,
                'status' => $message->status,
                'error_message' => $message->error_message,
                'sent_at' => $message->sent_at,
                'delivered_at' => $message->delivered_at,
                'read_at' => $message->read_at,
                'failed_at' => $message->failed_at,
            ],
        ]);
    }

    /**
     * Shared guard: credits, reputation, consistent error envelope.
     */
    private function guard(Request $request, callable $callback): JsonResponse
    {
        $user = $this->getUser($request);

        if (!$this->subscriptionService->hasRemainingMessages($user)) {
            return response()->json([
                'success' => false,
                'message' => 'No remaining messages in your subscription. Please upgrade your plan.',
            ], 403);
        }

        if (!$user->is_admin && !UserReputationService::canSendMessages($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Account is restricted due to high risk behavior. Please contact support.',
            ], 403);
        }

        try {
            return $callback($user);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Log::error('Public Meta WhatsApp API error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Meta WhatsApp API error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resolve the sender number, in priority order:
     *  1. The number bound to the API key on the dashboard (meta_phone_number_id)
     *  2. An explicit phone_number_id in the request (local id or Meta id)
     *  3. The user's first active official number
     */
    private function resolvePhoneNumber(int $userId, $ref = null, ?Request $request = null): ?MetaTestPhoneNumber
    {
        $query = MetaTestPhoneNumber::where('user_id', $userId)->where('is_active', true);

        $boundId = $request?->attributes->get('api_key')?->meta_phone_number_id;
        if ($boundId) {
            $number = (clone $query)->find($boundId);
            if ($number) {
                return $number;
            }
        }

        if ($ref !== null && $ref !== '') {
            $number = (clone $query)->where('id', $ref)->first()
                ?? (clone $query)->where('meta_phone_number_id', $ref)->first();
            if ($number) {
                return $number;
            }
        }

        return $query->latest('created_at')->first();
    }

    private function resolveTemplate(int $userId, $ref): ?MetaTestTemplate
    {
        $query = MetaTestTemplate::where('user_id', $userId);

        return is_numeric($ref)
            ? (clone $query)->find($ref) ?? (clone $query)->where('name', $ref)->first()
            : (clone $query)->where('name', $ref)->first();
    }

    /**
     * True when Meta says the 24h customer-service window is closed.
     */
    private function isWindowError(\Throwable $e): bool
    {
        if (in_array((int) $e->getCode(), [131047, 131026, 132000, 132001, 132016], true)) {
            return true;
        }

        return (bool) preg_match('/re-?engagement|outside.*(24|window)|24.?hour/i', $e->getMessage());
    }

    /**
     * Render a template's BODY component as plain text with variables
     * substituted — used for the free-form-first delivery attempt.
     */
    private function renderTemplateText(MetaTestTemplate $template, array $variables): ?string
    {
        $body = null;
        foreach (($template->components ?? []) as $component) {
            if (($component['type'] ?? '') === 'BODY') {
                $body = $component['text'] ?? '';
                break;
            }
        }

        if (empty($body)) {
            return null;
        }

        foreach (array_values($variables) as $i => $value) {
            $body = str_replace('{{' . ($i + 1) . '}}', (string) $value, $body);
        }

        return $body;
    }

    /**
     * Convert flat variables [v1, v2, ...] into WhatsApp body parameters.
     */
    private function buildBodyComponents(MetaTestTemplate $template, array $variables): array
    {
        if (empty($variables)) {
            return [];
        }

        $params = [];
        foreach (array_values($variables) as $v) {
            $params[] = ['type' => 'text', 'text' => (string) $v];
        }

        return [['type' => 'body', 'parameters' => $params]];
    }

    private function noNumberResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'No active official WhatsApp number. Connect one from the dashboard first.',
        ], 400);
    }
}
