<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendWebhookJob;
use App\Models\Webhook;
use App\Models\WebhookLog;
use App\Services\FraudDetectionService;
use App\Services\SafeUrl;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class WebhookController extends Controller
{
    private function getUserId(Request $request): ?int
    {
        return Auth::id() ?? $request->attributes->get('api_key_user')?->id;
    }

    private const EVENTS = [
        'message.sent',
        'message.delivered',
        'message.read',
        'message.failed',
        'message.received',
        'session.connected',
        'session.disconnected',
        'session.expired',
    ];

    public function index(Request $request)
    {
        try {
            $userId = $this->getUserId($request);
            $webhooks = Webhook::withCount('logs')
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $webhooks,
                'events' => self::EVENTS,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading webhooks: '. 'An error occurred',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'url' => 'required|url|max:1000',
                'secret' => 'nullable|string|max:255',
                'events' => 'nullable|array',
                'events.*' => Rule::in(self::EVENTS),
                'max_retries' => 'nullable|integer|min:0|max:10',
            ]);

            $userId = $this->getUserId($request);

            if (!SafeUrl::isSafe($request->url)) {
                FraudDetectionService::report('ssrf_attempt', 'critical', 'User attempted to create a webhook pointing to an unsafe/internal URL', [
                    'url' => $request->url,
                    'user_id' => $userId,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Webhook URL is not safe. It must be a public http(s) address and not point to a private network.',
                ], 400);
            }

            $webhook = Webhook::create([
                'user_id' => $userId,
                'name' => $request->name,
                'url' => $request->url,
                'secret' => $request->secret,
                'events' => $request->events,
                'max_retries' => $request->input('max_retries', 3),
                'is_active' => $request->input('is_active', true),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook created',
                'data' => $webhook,
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
                'message' => 'Error creating webhook: '. 'An error occurred',
            ], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'url' => 'required|url|max:1000',
                'secret' => 'nullable|string|max:255',
                'events' => 'nullable|array',
                'events.*' => Rule::in(self::EVENTS),
                'max_retries' => 'nullable|integer|min:0|max:10',
                'is_active' => 'boolean',
            ]);

            $userId = $this->getUserId($request);
            $webhook = Webhook::where('user_id', $userId)->where('id', $id)->first();

            if (!$webhook) {
                return response()->json(['success' => false, 'message' => 'Webhook not found'], 404);
            }

            if (!SafeUrl::isSafe($request->url)) {
                FraudDetectionService::report('ssrf_attempt', 'critical', 'User attempted to update a webhook to an unsafe/internal URL', [
                    'url' => $request->url,
                    'webhook_id' => $id,
                    'user_id' => $userId,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Webhook URL is not safe. It must be a public http(s) address and not point to a private network.',
                ], 400);
            }

            $webhook->update([
                'name' => $request->name,
                'url' => $request->url,
                'secret' => $request->secret,
                'events' => $request->events,
                'max_retries' => $request->input('max_retries', 3),
                'is_active' => $request->input('is_active', $webhook->is_active),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook updated',
                'data' => $webhook,
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
                'message' => 'Error updating webhook: '. 'An error occurred',
            ], 500);
        }
    }

    public function destroy(Request $request, int $id)
    {
        try {
            $userId = $this->getUserId($request);
            $webhook = Webhook::where('user_id', $userId)->where('id', $id)->first();

            if (!$webhook) {
                return response()->json(['success' => false, 'message' => 'Webhook not found'], 404);
            }

            $webhook->delete();

            return response()->json([
                'success' => true,
                'message' => 'Webhook deleted',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting webhook: '. 'An error occurred',
            ], 500);
        }
    }

    public function logs(Request $request, int $id)
    {
        try {
            $userId = $this->getUserId($request);
            $webhook = Webhook::where('user_id', $userId)->where('id', $id)->first();

            if (!$webhook) {
                return response()->json(['success' => false, 'message' => 'Webhook not found'], 404);
            }

            $logs = $webhook->logs()->orderBy('created_at', 'desc')->paginate(50);

            return response()->json([
                'success' => true,
                'data' => $logs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading logs: '. 'An error occurred',
            ], 500);
        }
    }

    public function test(Request $request, int $id)
    {
        try {
            $userId = $this->getUserId($request);
            $webhook = Webhook::where('user_id', $userId)->where('id', $id)->first();

            if (!$webhook) {
                return response()->json(['success' => false, 'message' => 'Webhook not found'], 404);
            }

            $payload = [
                'event' => 'test',
                'timestamp' => now()->toIso8601String(),
                'webhook_id' => $webhook->id,
                'user_id' => $userId,
                'message' => 'This is a test webhook from Wavixa',
                'webhook_name' => $webhook->name,
            ];

            $log = WebhookLog::create([
                'webhook_id' => $webhook->id,
                'user_id' => $userId,
                'event' => 'test',
                'status' => 'pending',
                'payload' => json_encode($payload),
                'attempts' => 0,
            ]);

            SendWebhookJob::dispatch($log);

            return response()->json([
                'success' => true,
                'message' => 'Test webhook dispatched',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error testing webhook: '. 'An error occurred',
            ], 500);
        }
    }

    public function resendLog(Request $request, int $id)
    {
        try {
            $userId = $this->getUserId($request);
            $log = WebhookLog::where('user_id', $userId)->where('id', $id)->first();

            if (!$log) {
                return response()->json(['success' => false, 'message' => 'Log not found'], 404);
            }

            $log->update([
                'status' => 'pending',
                'attempts' => 0,
                'error_message' => null,
                'response' => null,
                'sent_at' => null,
                'next_retry_at' => null,
            ]);

            SendWebhookJob::dispatch($log);

            return response()->json([
                'success' => true,
                'message' => 'Webhook resent',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error resending webhook: '. 'An error occurred',
            ], 500);
        }
    }

    public function events(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => self::EVENTS,
        ]);
    }
}
