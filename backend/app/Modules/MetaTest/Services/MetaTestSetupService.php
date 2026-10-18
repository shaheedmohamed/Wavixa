<?php

namespace App\Modules\MetaTest\Services;

use App\Modules\MetaTest\Exceptions\MetaTestApiException;
use Illuminate\Support\Facades\Log;

/**
 * Builds the developer/configuration checklist and readiness report.
 */
class MetaTestSetupService
{
    protected MetaTestGraphClient $client;

    public function __construct()
    {
        $this->client = new MetaTestGraphClient();
    }

    public function checklist(): array
    {
        $appId = config('meta.app_id');
        $appSecret = config('meta.app_secret');
        $accessToken = config('meta.access_token');
        $webhookVerifyToken = config('meta.webhook_verify_token');
        $redirectUri = config('meta.redirect_uri');

        $items = [
            [
                'name' => 'Meta App ID',
                'status' => !empty($appId) ? 'configured' : 'missing',
                'message' => !empty($appId) ? 'App ID is set' : 'Add META_APP_ID to .env',
            ],
            [
                'name' => 'Meta App Secret',
                'status' => !empty($appSecret) ? 'configured' : 'missing',
                'message' => !empty($appSecret) ? 'App Secret is set' : 'Add META_APP_SECRET to .env',
            ],
            [
                'name' => 'Meta Access Token',
                'status' => !empty($accessToken) ? 'configured' : 'missing',
                'message' => !empty($accessToken) ? 'Access Token is set' : 'Add META_ACCESS_TOKEN to .env',
            ],
            [
                'name' => 'Webhook Verify Token',
                'status' => !empty($webhookVerifyToken) ? 'configured' : 'missing',
                'message' => !empty($webhookVerifyToken) ? 'Verify token is set' : 'Add META_TEST_WEBHOOK_VERIFY_TOKEN to .env',
            ],
            [
                'name' => 'Redirect URI',
                'status' => !empty($redirectUri) ? 'configured' : 'missing',
                'message' => !empty($redirectUri) ? 'Redirect URI is set' : 'Add META_TEST_REDIRECT_URI to .env',
            ],
            [
                'name' => 'Embedded Signup Config ID',
                'status' => !empty(config('meta.login_config_id')) ? 'configured' : 'missing',
                'message' => !empty(config('meta.login_config_id'))
                    ? 'Facebook Login for Business config is set — JS SDK Embedded Signup (incl. WhatsApp Business App onboarding) is available'
                    : 'Add META_LOGIN_CONFIG_ID to .env (App Dashboard > Facebook Login for Business > Configurations). Without it the popup flow is unavailable and the legacy redirect flow is used.',
            ],
        ];

        // Try to validate the token
        $tokenCheck = $this->checkToken($accessToken, $appId, $appSecret);
        $items[] = [
            'name' => 'Access Token Valid',
            'status' => $tokenCheck['status'],
            'message' => $tokenCheck['message'],
            'details' => $tokenCheck['details'] ?? null,
        ];

        $graphCheck = $this->checkGraphApi($accessToken);
        $items[] = [
            'name' => 'Graph API Reachable',
            'status' => $graphCheck['status'],
            'message' => $graphCheck['message'],
            'details' => $graphCheck['details'] ?? null,
        ];

        $wabaCheck = $this->checkWhatsAppApi($accessToken);
        $items[] = [
            'name' => 'WhatsApp API Accessible',
            'status' => $wabaCheck['status'],
            'message' => $wabaCheck['message'],
            'details' => $wabaCheck['details'] ?? null,
        ];

        return [
            'all_configured' => collect($items)->every(fn ($i) => in_array($i['status'], ['configured', 'valid', 'ok'], true)),
            'requires_action' => collect($items)
                ->whereNotIn('status', ['configured', 'valid', 'ok'])
                ->values(),
            'items' => $items,
        ];
    }

    protected function checkToken(?string $token, ?string $appId, ?string $appSecret): array
    {
        if (empty($token) || empty($appId) || empty($appSecret)) {
            return [
                'status' => 'missing',
                'message' => 'Cannot validate token without App ID, Secret, and Access Token.',
            ];
        }

        try {
            $debug = $this->client->debugToken($token);
            $data = $debug['data'] ?? [];

            $isValid = ($data['is_valid'] ?? false) === true;
            $expiresAt = $data['expires_at'] ?? null;
            $scopes = $data['scopes'] ?? [];

            $missingScopes = array_diff(config('meta.scopes', []), $scopes);

            if (!$isValid) {
                return [
                    'status' => 'invalid',
                    'message' => 'Access token is reported as invalid by Meta.',
                    'details' => ['expires_at' => $expiresAt, 'scopes' => $scopes],
                ];
            }

            if (!empty($missingScopes)) {
                return [
                    'status' => 'review_required',
                    'message' => 'Access token is valid but missing required scopes: ' . implode(', ', $missingScopes),
                    'details' => ['scopes' => $scopes, 'missing' => $missingScopes],
                ];
            }

            return [
                'status' => 'valid',
                'message' => 'Access token is valid and has required scopes.',
                'details' => [
                    'app_id' => $data['app_id'] ?? null,
                    'type' => $data['type'] ?? null,
                    'expires_at' => $expiresAt,
                    'scopes' => $scopes,
                ],
            ];
        } catch (MetaTestApiException $e) {
            $message = $this->interpretError($e->getMessage());

            return [
                'status' => $e->getCode() === 190 ? 'invalid' : 'error',
                'message' => $message,
                'details' => ['code' => $e->getCode(), 'error' => $e->getMetaError()],
            ];
        } catch (\Throwable $e) {
            Log::error('MetaTestSetupService token check error: ' . $e->getMessage());

            return [
                'status' => 'error',
                'message' => 'Unexpected error while validating token: ' . $e->getMessage(),
            ];
        }
    }

    protected function checkGraphApi(?string $token): array
    {
        if (empty($token)) {
            return [
                'status' => 'missing',
                'message' => 'No access token to test Graph API.',
            ];
        }

        try {
            $me = $this->client->request('GET', '/me', ['fields' => 'id,name,email'], $token);

            return [
                'status' => 'ok',
                'message' => 'Graph API is reachable.',
                'details' => $me,
            ];
        } catch (MetaTestApiException $e) {
            return [
                'status' => 'error',
                'message' => 'Graph API error: ' . $this->interpretError($e->getMessage()),
                'details' => ['code' => $e->getCode()],
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => 'Graph API transport error: ' . $e->getMessage(),
            ];
        }
    }

    protected function checkWhatsAppApi(?string $token): array
    {
        if (empty($token)) {
            return [
                'status' => 'missing',
                'message' => 'No access token to test WhatsApp API.',
            ];
        }

        try {
            $me = $this->client->request('GET', '/me', ['fields' => 'id'], $token);
            $userId = $me['id'] ?? null;

            if (empty($userId)) {
                return [
                    'status' => 'error',
                    'message' => 'Could not determine Meta user ID from token.',
                ];
            }

            $wabas = $this->client->request(
                'GET',
                "/{$userId}/assigned_whatsapp_business_accounts",
                ['fields' => 'id,name', 'limit' => 1],
                $token
            );

            $count = count($wabas['data'] ?? []);

            return [
                'status' => 'ok',
                'message' => "WhatsApp API responded. Found {$count} assigned WABA(s).",
                'details' => ['waba_count' => $count, 'user_id' => $userId],
            ];
        } catch (MetaTestApiException $e) {
            return [
                'status' => 'error',
                'message' => 'WhatsApp API error: ' . $this->interpretError($e->getMessage()),
                'details' => ['code' => $e->getCode(), 'error' => $e->getMessage()],
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => 'WhatsApp API transport error: ' . $e->getMessage(),
            ];
        }
    }

    protected function interpretError(string $message): string
    {
        if (str_contains($message, '190')) {
            return 'Access token expired or invalid. Check META_ACCESS_TOKEN.';
        }

        if (str_contains($message, '104')) {
            return 'App ID / App Secret mismatch or unsupported API version.';
        }

        if (str_contains($message, '200')) {
            return 'Permission missing. The app may need Advanced Access review.';
        }

        if (str_contains($message, '100')) {
            return 'Invalid parameter. Check API version and request format.';
        }

        return $message;
    }
}
