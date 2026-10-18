<?php

namespace App\Modules\MetaTest\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MetaTest\Models\MetaTestApiLog;
use App\Modules\MetaTest\Models\MetaTestConnection;
use App\Modules\MetaTest\Models\MetaTestEvent;
use App\Modules\MetaTest\Models\MetaTestPhoneNumber;
use App\Modules\MetaTest\Services\MetaTestGraphClient;
use App\Modules\MetaTest\Services\MetaTestSetupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class MetaTestDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $setupService = new MetaTestSetupService();
        $setup = $setupService->checklist();

        $connection = MetaTestConnection::where('user_id', Auth::id())
            ->withCount('wabas')
            ->withCount('phoneNumbers')
            ->first();

        $phoneCount = MetaTestPhoneNumber::where('user_id', Auth::id())->where('is_active', true)->count();
        $wabaCount = $connection?->wabas_count ?? 0;
        $totalPhoneCount = $connection?->phone_numbers_count ?? 0;

        $lastLog = MetaTestApiLog::where('user_id', Auth::id())
            ->orWhereNull('user_id')
            ->orderByDesc('created_at')
            ->first();

        $lastError = MetaTestApiLog::failed()
            ->where(function ($q) {
                $q->where('user_id', Auth::id())->orWhereNull('user_id');
            })
            ->orderByDesc('created_at')
            ->first();

        $lastEvent = MetaTestEvent::where('user_id', Auth::id())
            ->orWhereNull('user_id')
            ->orderByDesc('created_at')
            ->first();

        $accessTokenStatus = $this->getAccessTokenStatus($setup);

        return response()->json([
            'success' => true,
            'data' => [
                'app_id' => config('meta.app_id'),
                'api_version' => config('meta.api_version'),
                'graph_url' => config('meta.graph_url'),
                'feature_enabled' => config('meta.enabled'),
                'connection_status' => $connection?->status ?? 'none',
                'onboarding_type' => $connection?->onboarding_type,
                'webhook_subscribed' => !empty($connection?->webhook_subscribed_at),
                'access_token_status' => $accessTokenStatus,
                'webhook_status' => config('meta.webhook_verify_token') ? 'configured' : 'missing',
                'webhook_url' => url(config('meta.webhook_path')),
                'redirect_uri' => config('meta.redirect_uri'),
                'setup_status' => $setup['all_configured'] ? 'configured' : 'incomplete',
                'requires_action' => $setup['requires_action'],
                'waba_count' => $wabaCount,
                'phone_number_count' => $phoneCount,
                'total_phone_numbers' => $totalPhoneCount,
                'last_api_request' => $lastLog ? [
                    'method' => $lastLog->method,
                    'endpoint' => $lastLog->endpoint,
                    'status' => $lastLog->http_status,
                    'success' => $lastLog->success,
                    'created_at' => $lastLog->created_at,
                ] : null,
                'last_api_response' => $lastLog ? [
                    'response_payload' => $lastLog->response_payload,
                    'meta_error_message' => $lastLog->meta_error_message,
                ] : null,
                'last_error' => $lastError ? [
                    'endpoint' => $lastError->endpoint,
                    'http_status' => $lastError->http_status,
                    'meta_error_message' => $lastError->meta_error_message,
                    'created_at' => $lastError->created_at,
                ] : null,
                'last_event' => $lastEvent ? [
                    'event_type' => $lastEvent->event_type,
                    'status' => $lastEvent->status,
                    'created_at' => $lastEvent->created_at,
                ] : null,
            ],
        ]);
    }

    protected function getAccessTokenStatus(array $setup): array
    {
        $tokenItem = collect($setup['items'])
            ->first(fn ($i) => $i['name'] === 'Access Token Valid');

        $accessToken = config('meta.access_token');

        return [
            'status' => $tokenItem['status'] ?? 'unknown',
            'message' => $tokenItem['message'] ?? 'Not checked',
            'masked' => $this->mask($accessToken),
            'details' => $tokenItem['details'] ?? null,
        ];
    }

    protected function mask(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $len = strlen($value);

        return substr($value, 0, 4) . str_repeat('*', max(0, $len - 8)) . substr($value, -4);
    }
}
