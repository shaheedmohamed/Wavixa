<?php

namespace App\Modules\MetaTest\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MetaTest\Models\MetaTestConnection;
use App\Modules\MetaTest\Services\MetaTestConnectionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MetaTestConnectController extends Controller
{
    public function authUrl(): JsonResponse
    {
        $manager = new MetaTestConnectionManager(Auth::id());

        try {
            $data = $manager->getAuthUrl(Auth::id());

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            Log::error('MetaTestConnectController authUrl failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle the OAuth/Embedded Signup callback from Meta.
     */
    public function callback(Request $request): RedirectResponse
    {
        $code = $request->input('code');
        $state = $request->input('state');
        $error = $request->input('error');
        $errorReason = $request->input('error_reason');
        $wabaId = $request->input('waba_id');
        $phoneNumberId = $request->input('phone_number_id');

        if ($error) {
            Log::warning('Meta Test OAuth denied', ['error' => $error, 'reason' => $errorReason]);
            return redirect('/meta/connect?status=denied&message=' . urlencode($error));
        }

        if (!$code) {
            return redirect('/meta/connect?status=error&message=missing_code');
        }

        $stateData = json_decode(base64_decode($state), true);
        if (empty($stateData['user_id'])) {
            return redirect('/meta/connect?status=error&message=invalid_state');
        }

        $userId = (int) $stateData['user_id'];
        $manager = new MetaTestConnectionManager($userId);

        try {
            $connection = $manager->handleCallback($userId, $code, $wabaId, $phoneNumberId);

            return redirect('/meta/connect?status=connected&waba_count=' . $connection->wabas()->count());
        } catch (\Throwable $e) {
            Log::error('Meta Test OAuth callback failed: ' . $e->getMessage());

            return redirect('/meta/connect?status=error&message=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Public (authenticated) launch configuration for the JavaScript SDK
     * Embedded Signup flow. The app ID and config ID are not secrets;
     * the app secret never leaves the server.
     */
    public function embeddedSignupConfig(): JsonResponse
    {
        $configId = config('meta.login_config_id');

        return response()->json([
            'success' => true,
            'data' => [
                'app_id' => config('meta.app_id'),
                'config_id' => $configId,
                'api_version' => config('meta.api_version'),
                'feature_type' => config('meta.embedded_signup_feature'),
                'session_info_version' => config('meta.session_info_version'),
                'embedded_signup_available' => !empty(config('meta.app_id')) && !empty($configId),
            ],
        ]);
    }

    /**
     * Handle the JavaScript SDK Embedded Signup completion. The browser sends
     * the exchangeable code plus the session info captured from the
     * WA_EMBEDDED_SIGNUP postMessage events. All Meta calls happen here,
     * server-to-server.
     */
    public function embeddedSignupCallback(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:4096',
            'waba_id' => 'nullable|string|max:64',
            'phone_number_id' => 'nullable|string|max:64',
            'event' => 'nullable|string|max:64',
            'session_info' => 'nullable|array',
        ]);

        $manager = new MetaTestConnectionManager(Auth::id());

        try {
            $connection = $manager->handleEmbeddedSignupCallback(
                Auth::id(),
                $data['code'],
                $data['waba_id'] ?? null,
                $data['phone_number_id'] ?? null,
                $data['event'] ?? null,
                $data['session_info'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => $connection->status,
                    'onboarding_type' => $connection->onboarding_type,
                    'signup_event' => $connection->signup_event,
                    'waba_count' => $connection->wabas->count(),
                    'phone_number_count' => $connection->phoneNumbers->count(),
                    'webhook_subscribed' => !empty($connection->webhook_subscribed_at),
                    'error_message' => $connection->error_message,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('meta connection_failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $this->friendlyCallbackError($e),
            ], 502);
        }
    }

    /**
     * Map Meta/API failures to user-safe messages. Raw Meta error details stay
     * in the server logs and meta_test_api_logs only.
     */
    protected function friendlyCallbackError(\Throwable $e): string
    {
        $msg = strtolower($e->getMessage() ?? '');

        return match (true) {
            str_contains($msg, 'code') && (str_contains($msg, 'invalid') || str_contains($msg, 'expired') || str_contains($msg, 'verification'))
                => 'The authorization code is invalid or expired. Please try connecting again.',
            str_contains($msg, 'access token') || str_contains($msg, 'oauth')
                => 'Could not complete authorization with Meta. Please try again.',
            str_contains($msg, 'rate limit') || str_contains($msg, '(#4)') || str_contains($msg, '(#17)')
                => 'Meta rate limit reached. Please wait a moment and try again.',
            str_contains($msg, 'permission') || str_contains($msg, 'scope')
                => 'A required Meta permission was not granted. Please retry and approve all requested permissions.',
            default => 'Meta connection failed. Please try again or contact support.',
        };
    }

    /**
     * Disconnect the current user's Meta Test connection.
     */
    public function disconnect(): JsonResponse
    {
        $connection = MetaTestConnection::where('user_id', Auth::id())->first();

        if ($connection) {
            $connection->update(['status' => MetaTestConnection::STATUS_DISCONNECTED]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Meta Test connection disconnected.',
        ]);
    }
}
