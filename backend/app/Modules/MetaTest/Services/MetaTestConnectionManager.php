<?php

namespace App\Modules\MetaTest\Services;

use App\Modules\MetaTest\Exceptions\MetaTestApiException;
use App\Modules\MetaTest\Models\MetaTestConnection;
use App\Modules\MetaTest\Models\MetaTestPhoneNumber;
use App\Modules\MetaTest\Models\MetaTestTemplate;
use App\Modules\MetaTest\Models\MetaTestWaba;
use App\Services\PhoneNormalizer;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Manages Meta OAuth, WABA/phone sync, and template management for the test module.
 */
class MetaTestConnectionManager
{
    protected MetaTestGraphClient $client;

    public function __construct(?int $userId = null)
    {
        $this->client = new MetaTestGraphClient($userId);
    }

    /**
     * Build the OAuth/Embedded Signup URL.
     */
    public function getAuthUrl(int $userId): array
    {
        $appId = config('meta.app_id');
        $appSecret = config('meta.app_secret');

        if (empty($appId) || empty($appSecret)) {
            throw new MetaTestApiException('Meta App ID and App Secret are not configured.', 500);
        }

        $redirectUri = config('meta.redirect_uri');
        $state = base64_encode(json_encode([
            'user_id' => $userId,
            'nonce' => bin2hex(random_bytes(8)),
        ]));

        $scopes = implode(',', config('meta.scopes', [
            'business_management',
            'whatsapp_business_management',
            'whatsapp_business_messaging',
        ]));

        $apiVersion = config('meta.api_version', 'v21.0');

        $params = [
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => $scopes,
            'response_type' => 'code',
            'enable_ssl_config' => '1',
        ];

        // Facebook Login for Business config turns this into full Embedded
        // Signup (WABA + number creation inside the dialog) instead of a
        // plain permission grant for existing accounts.
        $configId = config('meta.login_config_id');
        if (!empty($configId)) {
            $params['config_id'] = $configId;
        }

        $query = http_build_query($params);

        return [
            'url' => "https://www.facebook.com/{$apiVersion}/dialog/oauth?{$query}",
            'state' => $state,
            'redirect_uri' => $redirectUri,
        ];
    }

    /**
     * Exchange a short-lived code for a token, then optionally exchange for a long-lived token.
     */
    public function exchangeCode(string $code, string $redirectUri): array
    {
        return $this->client->request('GET', '/oauth/access_token', [
            'client_id' => config('meta.app_id'),
            'client_secret' => config('meta.app_secret'),
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ], null, false);
    }

    /**
     * Exchange a code returned by the JavaScript SDK Embedded Signup flow.
     * These codes are not bound to a redirect_uri, so none is sent.
     */
    public function exchangeEmbeddedSignupCode(string $code): array
    {
        return $this->client->request('GET', '/oauth/access_token', [
            'client_id' => config('meta.app_id'),
            'client_secret' => config('meta.app_secret'),
            'code' => $code,
        ], null, false);
    }

    public function exchangeLongLivedToken(string $shortLivedToken): array
    {
        return $this->client->request('GET', '/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => config('meta.app_id'),
            'client_secret' => config('meta.app_secret'),
            'fb_exchange_token' => $shortLivedToken,
        ], null, false);
    }

    public function debugToken(string $token): array
    {
        return $this->client->debugToken($token);
    }

    /**
     * Process the OAuth/Embedded Signup callback and persist the connection.
     */
    public function handleCallback(int $userId, string $code, ?string $wabaId = null, ?string $phoneNumberId = null): MetaTestConnection
    {
        $redirectUri = config('meta.redirect_uri');

        $tokenData = $this->exchangeCode($code, $redirectUri);
        $accessToken = $tokenData['access_token'] ?? null;
        $expiresIn = $tokenData['expires_in'] ?? 3600;

        if (empty($accessToken)) {
            throw new MetaTestApiException('Meta did not return an access token.', 500, $tokenData);
        }

        // Try to exchange for a long-lived token (60 days)
        try {
            $longLived = $this->exchangeLongLivedToken($accessToken);
            if (!empty($longLived['access_token'])) {
                $accessToken = $longLived['access_token'];
                $expiresIn = $longLived['expires_in'] ?? 5184000;
            }
        } catch (Exception $e) {
            Log::warning("MetaTestConnectionManager: long-lived token exchange failed: {$e->getMessage()}");
        }

        $connection = MetaTestConnection::updateOrCreate(
            ['user_id' => $userId],
            [
                'access_token' => $accessToken,
                'token_expires_at' => now()->addSeconds((int) $expiresIn),
                'status' => MetaTestConnection::STATUS_CONNECTED,
                'connection_type' => MetaTestConnection::CONNECTION_TYPE_META_CLOUD_API,
                'onboarding_type' => MetaTestConnection::ONBOARDING_OAUTH_REDIRECT,
                'scopes' => config('meta.scopes'),
                'error_message' => null,
            ]
        );

        try {
            $this->syncWABAsAndNumbers($connection, $wabaId, $phoneNumberId);

            if ($connection->wabas()->count() === 0) {
                $connection->update(['error_message' => 'OAuth succeeded, but no WABAs were found. Verify the WhatsApp Business account is assigned to your Meta user and use Accounts > Sync.']);
            }
        } catch (Exception $e) {
            Log::error('MetaTestConnectionManager: WABA sync failed after OAuth: ' . $e->getMessage());
            $connection->update(['error_message' => 'OAuth succeeded, but WABA sync failed: ' . $e->getMessage()]);
        }

        return $connection->fresh('wabas', 'wabas.phoneNumbers');
    }

    /**
     * Map a WA_EMBEDDED_SIGNUP session event name to a stored onboarding type.
     * Only events documented by Meta are mapped; anything else is 'unknown'.
     */
    public static function mapOnboardingType(?string $event): string
    {
        return match ($event) {
            'FINISH' => MetaTestConnection::ONBOARDING_CLOUD_API,
            'FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING' => MetaTestConnection::ONBOARDING_BUSINESS_APP,
            'FINISH_ONLY_WABA' => MetaTestConnection::ONBOARDING_WABA_ONLY,
            'FINISH_OBO_MIGRATION' => MetaTestConnection::ONBOARDING_OBO_MIGRATION,
            'FINISH_GRANT_ONLY_API_ACCESS' => MetaTestConnection::ONBOARDING_GRANT_ONLY,
            default => MetaTestConnection::ONBOARDING_UNKNOWN,
        };
    }

    /**
     * Process a JavaScript SDK Embedded Signup completion.
     *
     * The frontend sends the exchangeable code returned by FB.login plus the
     * session info delivered via WA_EMBEDDED_SIGNUP postMessage events. The
     * code is exchanged server-to-server; WABA/phone IDs from the session are
     * treated only as hints — the actual data is fetched from Meta.
     */
    public function handleEmbeddedSignupCallback(
        int $userId,
        string $code,
        ?string $wabaId = null,
        ?string $phoneNumberId = null,
        ?string $event = null,
        ?array $sessionInfo = null
    ): MetaTestConnection {
        Log::info('meta embedded_signup_callback', [
            'user_id' => $userId,
            'event' => $event,
            'waba_id' => $wabaId,
            'phone_number_id' => $phoneNumberId,
        ]);

        try {
            $tokenData = $this->exchangeEmbeddedSignupCode($code);
        } catch (Exception $e) {
            Log::warning('meta token_exchange_failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $accessToken = $tokenData['access_token'] ?? null;
        $expiresIn = $tokenData['expires_in'] ?? 3600;

        if (empty($accessToken)) {
            Log::warning('meta token_exchange_failed', [
                'user_id' => $userId,
                'reason' => 'empty_access_token',
            ]);

            throw new MetaTestApiException('Meta did not return an access token.', 500, $tokenData);
        }

        Log::info('meta token_exchange_success', ['user_id' => $userId]);

        // Try to exchange for a long-lived token (60 days)
        try {
            $longLived = $this->exchangeLongLivedToken($accessToken);
            if (!empty($longLived['access_token'])) {
                $accessToken = $longLived['access_token'];
                $expiresIn = $longLived['expires_in'] ?? 5184000;
            }
        } catch (Exception $e) {
            Log::warning("MetaTestConnectionManager: long-lived token exchange failed: {$e->getMessage()}");
        }

        $connection = MetaTestConnection::updateOrCreate(
            ['user_id' => $userId],
            [
                'access_token' => $accessToken,
                'token_expires_at' => now()->addSeconds((int) $expiresIn),
                'status' => MetaTestConnection::STATUS_CONNECTED,
                'connection_type' => MetaTestConnection::CONNECTION_TYPE_META_CLOUD_API,
                'onboarding_type' => self::mapOnboardingType($event),
                'signup_event' => $event,
                'session_info' => $sessionInfo,
                'scopes' => config('meta.scopes'),
                'error_message' => null,
            ]
        );

        try {
            $this->syncWABAsAndNumbers($connection, $wabaId, $phoneNumberId);

            if ($connection->wabas()->count() === 0) {
                $connection->update(['error_message' => 'Embedded Signup succeeded, but no WABAs were found. Verify the WhatsApp Business account is assigned to your Meta user and use Accounts > Sync.']);
            }
        } catch (Exception $e) {
            Log::error('MetaTestConnectionManager: WABA sync failed after Embedded Signup: ' . $e->getMessage());
            $connection->update(['error_message' => 'Embedded Signup succeeded, but WABA sync failed: ' . $e->getMessage()]);
        }

        Log::info('meta connection_success', [
            'user_id' => $userId,
            'onboarding_type' => $connection->onboarding_type,
            'waba_count' => $connection->wabas()->count(),
        ]);

        return $connection->fresh('wabas', 'wabas.phoneNumbers');
    }

    /**
     * Sync WABAs, phone numbers, and templates from Meta into the local test tables.
     */
    public function syncWABAsAndNumbers(MetaTestConnection $connection, ?string $wabaId = null, ?string $phoneNumberId = null): MetaTestConnection
    {
        $wabasData = [];

        // If Embedded Signup provided a specific WABA ID, try to fetch it directly.
        if (!empty($wabaId)) {
            try {
                $waba = $this->client->request(
                    'GET',
                    "/{$wabaId}",
                    ['fields' => 'id,name,timezone_id,message_template_namespace,account_review_status,business_verification_status,country'],
                    $connection->access_token
                );

                if (!empty($waba['id'])) {
                    $wabasData[] = $waba;
                }
            } catch (Exception $e) {
                Log::warning("MetaTestConnectionManager: could not fetch WABA {$wabaId}: {$e->getMessage()}");
            }
        }

        // Discover WABAs assigned to the current user.
        if (empty($wabasData)) {
            $me = $this->client->request('GET', '/me', ['fields' => 'id,name'], $connection->access_token);
            $metaUserId = $me['id'] ?? null;

            if (!empty($metaUserId)) {
                try {
                    $wabas = $this->client->request(
                        'GET',
                        "/{$metaUserId}/assigned_whatsapp_business_accounts",
                        [
                            'fields' => 'id,name',
                            'limit' => 100,
                        ],
                        $connection->access_token
                    );

                    $wabasData = $wabas['data'] ?? [];
                } catch (Exception $e) {
                    Log::warning("MetaTestConnectionManager: assigned WABAs lookup failed for user {$metaUserId}: {$e->getMessage()}");
                }
            }
        }

        // Fallback: list WABAs owned by any business the user can access.
        if (empty($wabasData)) {
            try {
                $businesses = $this->client->request('GET', '/me/businesses', ['limit' => 100], $connection->access_token);

                foreach ($businesses['data'] ?? [] as $business) {
                    try {
                        $wabas = $this->client->request(
                            'GET',
                            "/{$business['id']}/owned_whatsapp_business_accounts",
                            [
                                'fields' => 'id,name',
                                'limit' => 100,
                            ],
                            $connection->access_token
                        );

                        foreach ($wabas['data'] ?? [] as $waba) {
                            $wabasData[] = $waba;
                        }
                    } catch (Exception $e) {
                        Log::warning("MetaTestConnectionManager: could not list owned WABAs for business {$business['id']}: {$e->getMessage()}");
                    }
                }
            } catch (Exception $e) {
                Log::warning("MetaTestConnectionManager: businesses lookup failed: {$e->getMessage()}");
            }
        }

        $webhookSubscribed = false;

        DB::transaction(function () use ($connection, $wabasData, &$webhookSubscribed) {
            foreach ($wabasData as $wabaData) {
                $waba = MetaTestWaba::updateOrCreate(
                    [
                        'user_id' => $connection->user_id,
                        'meta_waba_id' => $wabaData['id'],
                    ],
                    [
                        'connection_id' => $connection->id,
                        'name' => $wabaData['name'] ?? null,
                        'timezone' => $wabaData['timezone'] ?? null,
                        'currency' => $wabaData['currency'] ?? null,
                        'status' => $wabaData['status'] ?? 'ACTIVE',
                        'raw_data' => $wabaData,
                    ]
                );

                Log::info('meta waba_discovered', [
                    'user_id' => $connection->user_id,
                    'waba_id' => $wabaData['id'],
                ]);

                try {
                    $this->subscribeWabaToWebhook($wabaData['id'], $connection->access_token);
                    $webhookSubscribed = true;
                    Log::info('meta webhook_subscription_success', [
                        'user_id' => $connection->user_id,
                        'waba_id' => $wabaData['id'],
                    ]);
                } catch (Exception $e) {
                    Log::warning('meta webhook_subscription_failed', [
                        'user_id' => $connection->user_id,
                        'waba_id' => $wabaData['id'],
                        'error' => $e->getMessage(),
                    ]);
                }

                try {
                    $this->syncPhoneNumbers($connection, $waba);
                } catch (Exception $e) {
                    Log::warning("MetaTestConnectionManager: phone number sync failed for WABA {$wabaData['id']}: {$e->getMessage()}");
                }

                try {
                    $this->syncTemplates($connection, $waba);
                } catch (Exception $e) {
                    Log::warning("MetaTestConnectionManager: template sync failed for WABA {$wabaData['id']}: {$e->getMessage()}");
                }
            }
        });

        if ($webhookSubscribed) {
            $connection->update(['webhook_subscribed_at' => now()]);
        }

        return $connection->fresh('wabas', 'wabas.phoneNumbers');
    }

    /**
     * Subscribe the app to a WABA's webhook notifications. Without this call
     * Meta never sends messages/statuses for that WABA to our webhook.
     * Idempotent: Meta returns success even if already subscribed.
     */
    public function subscribeWabaToWebhook(string $wabaId, string $accessToken): array
    {
        return $this->client->request(
            'POST',
            "/{$wabaId}/subscribed_apps",
            [],
            $accessToken
        );
    }

    protected function syncPhoneNumbers(MetaTestConnection $connection, MetaTestWaba $waba): void
    {
        $response = $this->client->request(
            'GET',
            "/{$waba->meta_waba_id}/phone_numbers",
            [
                'fields' => 'id,display_phone_number,verified_name,quality_rating,status,account_mode,name_status,certificate,messaging_limit_tier,platform_type,is_on_biz_app',
            ],
            $connection->access_token
        );

        $numbers = $response['data'] ?? [];

        foreach ($numbers as $phone) {
            MetaTestPhoneNumber::updateOrCreate(
                [
                    'user_id' => $connection->user_id,
                    'meta_phone_number_id' => $phone['id'],
                ],
                [
                    'waba_id' => $waba->id,
                    'phone_number' => $phone['display_phone_number'] ?? $phone['phone_number'] ?? $phone['id'],
                    'display_name' => $phone['verified_name'] ?? null,
                    'quality_rating' => $phone['quality_rating'] ?? null,
                    'status' => $phone['status'] ?? 'CONNECTED',
                    'messaging_limit_tier' => $phone['messaging_limit_tier'] ?? ($phone['messaging_limit'] ?? null),
                    'platform_type' => $phone['platform_type'] ?? null,
                    'account_mode' => $phone['account_mode'] ?? null,
                    'is_active' => true,
                    'raw_data' => $phone,
                ]
            );

            Log::info('meta phone_number_discovered', [
                'user_id' => $connection->user_id,
                'waba_id' => $waba->meta_waba_id,
                'phone_number_id' => $phone['id'] ?? null,
                'platform_type' => $phone['platform_type'] ?? null,
                'account_mode' => $phone['account_mode'] ?? null,
            ]);
        }
    }

    protected function syncTemplates(MetaTestConnection $connection, MetaTestWaba $waba): void
    {
        $response = $this->client->request(
            'GET',
            "/{$waba->meta_waba_id}/message_templates",
            [
                'fields' => 'id,name,language,category,status,components,quality_score',
                'limit' => 100,
            ],
            $connection->access_token
        );

        $templates = $response['data'] ?? [];

        foreach ($templates as $template) {
            MetaTestTemplate::updateOrCreate(
                [
                    'user_id' => $connection->user_id,
                    'waba_id' => $waba->id,
                    'name' => $template['name'],
                ],
                [
                    'meta_template_id' => $template['id'] ?? null,
                    'category' => $template['category'] ?? 'MARKETING',
                    'language' => $template['language'] ?? 'ar',
                    'status' => $template['status'] ?? MetaTestTemplate::STATUS_PENDING,
                    'components' => $template['components'] ?? null,
                    'raw_data' => $template,
                ]
            );
        }
    }

    public function registerPhoneNumber(MetaTestConnection $connection, string $phoneNumberId, string $pin = '123456'): array
    {
        return $this->client->request(
            'POST',
            "/{$phoneNumberId}/register",
            [
                'messaging_product' => 'whatsapp',
                'pin' => $pin,
            ],
            $connection->access_token
        );
    }

    /**
     * Add a (still unverified) phone number to a customer's WABA.
     * Requires Tech Provider access on that WABA.
     * Returns the new Meta phone_number_id.
     */
    public function addPhoneNumber(MetaTestConnection $connection, MetaTestWaba $waba, string $cc, string $phoneNumber, string $displayName): array
    {
        return $this->client->request(
            'POST',
            "/{$waba->meta_waba_id}/phone_numbers",
            [
                'cc' => $cc,
                'phone_number' => $phoneNumber,
                'verified_name' => $displayName,
            ],
            $connection->access_token
        );
    }

    /**
     * Ask Meta to send the ownership verification code via SMS or voice call.
     */
    public function requestPhoneVerificationCode(MetaTestConnection $connection, string $phoneNumberId, string $method = 'SMS', string $language = 'ar'): array
    {
        return $this->client->request(
            'POST',
            "/{$phoneNumberId}/request_code",
            [
                'code_method' => $method,
                'language' => $language,
            ],
            $connection->access_token
        );
    }

    /**
     * Submit the received verification code to Meta.
     */
    public function verifyPhoneCode(MetaTestConnection $connection, string $phoneNumberId, string $code): array
    {
        return $this->client->request(
            'POST',
            "/{$phoneNumberId}/verify_code",
            ['code' => $code],
            $connection->access_token
        );
    }

    /**
     * Deregister a phone number from the Cloud API (required before deletion
     * for numbers already registered).
     */
    public function deregisterPhoneNumber(MetaTestConnection $connection, string $phoneNumberId): array
    {
        return $this->client->request(
            'POST',
            "/{$phoneNumberId}/deregister",
            ['messaging_product' => 'whatsapp'],
            $connection->access_token
        );
    }

    /**
     * Permanently remove a phone number from its WABA on Meta.
     */
    public function deletePhoneNumber(MetaTestConnection $connection, string $phoneNumberId): array
    {
        return $this->client->request(
            'DELETE',
            "/{$phoneNumberId}",
            [],
            $connection->access_token
        );
    }

    public function createTemplate(MetaTestConnection $connection, MetaTestWaba $waba, array $template): MetaTestTemplate
    {
        $payload = [
            'name' => strtolower(preg_replace('/[^a-z0-9_]/', '_', $template['name'])),
            'category' => $template['category'] ?? 'MARKETING',
            'language' => $template['language'] ?? 'ar',
            'components' => $template['components'] ?? [],
        ];

        $result = $this->client->request(
            'POST',
            "/{$waba->meta_waba_id}/message_templates",
            $payload,
            $connection->access_token
        );

        return MetaTestTemplate::create([
            'user_id' => $connection->user_id,
            'waba_id' => $waba->id,
            'meta_template_id' => $result['id'] ?? null,
            'name' => $payload['name'],
            'category' => $payload['category'],
            'language' => $payload['language'],
            'status' => $result['status'] ?? MetaTestTemplate::STATUS_PENDING,
            'components' => $payload['components'],
            'raw_data' => $result,
        ]);
    }

    public function deleteTemplate(MetaTestConnection $connection, MetaTestWaba $waba, MetaTestTemplate $template): bool
    {
        try {
            $this->client->request(
                'DELETE',
                "/{$waba->meta_waba_id}/message_templates",
                ['name' => $template->name],
                $connection->access_token
            );

            return true;
        } catch (Exception $e) {
            Log::warning("MetaTestConnectionManager: delete template failed: {$e->getMessage()}");
            return false;
        }
    }

    public function normalizePhone(string $phone): string
    {
        return PhoneNormalizer::digits($phone);
    }
}
