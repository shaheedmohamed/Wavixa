<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class MetaWhatsAppService
{
    protected string $graphUrl;
    protected string $apiVersion;
    protected ?string $appId;
    protected ?string $appSecret;
    protected ?string $verifyToken;

    public function __construct()
    {
        $this->graphUrl = rtrim(config('services.meta_whatsapp.graph_url', 'https://graph.facebook.com'), '/');
        $this->apiVersion = config('services.meta_whatsapp.api_version', 'v21.0');
        $this->appId = config('services.meta_whatsapp.app_id');
        $this->appSecret = config('services.meta_whatsapp.app_secret');
        $this->verifyToken = config('services.meta_whatsapp.webhook_verify_token');
    }

    /**
     * Build a Graph API URL.
     */
    public function graphUrl(string $path): string
    {
        return "{$this->graphUrl}/{$this->apiVersion}/" . ltrim($path, '/');
    }

    /**
     * Build the Facebook OAuth/Embedded Signup dialog URL.
     */
    public function getAuthUrl(string $redirectUri, string $state, array $scopes = []): string
    {
        $this->requireAppCredentials();

        $defaultScopes = [
            'business_management',
            'whatsapp_business_management',
            'whatsapp_business_messaging',
        ];

        $scope = implode(',', array_unique(array_merge($defaultScopes, $scopes)));

        $query = http_build_query([
            'client_id' => $this->appId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => $scope,
            'response_type' => 'code',
            'enable_ssl_config' => '1',
        ]);

        return "https://www.facebook.com/{$this->apiVersion}/dialog/oauth?{$query}";
    }

    /**
     * Exchange an OAuth code for a short-lived user access token.
     */
    public function exchangeCode(string $code, string $redirectUri): array
    {
        $this->requireAppCredentials();

        $url = $this->graphUrl('/oauth/access_token');
        $response = Http::timeout(30)->get($url, [
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);

        return $this->handleResponse($response, 'exchangeCode');
    }

    /**
     * Exchange a short-lived token for a long-lived token (60 days).
     */
    public function exchangeLongLivedToken(string $shortLivedToken): array
    {
        $this->requireAppCredentials();

        $url = $this->graphUrl('/oauth/access_token');
        $response = Http::timeout(30)->get($url, [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'fb_exchange_token' => $shortLivedToken,
        ]);

        return $this->handleResponse($response, 'exchangeLongLivedToken');
    }

    /**
     * Verify a token and get metadata.
     */
    public function debugToken(string $token): array
    {
        $this->requireAppCredentials();

        $url = $this->graphUrl('/debug_token');
        $response = Http::timeout(30)->get($url, [
            'input_token' => $token,
            'access_token' => $this->appId . '|' . $this->appSecret,
        ]);

        return $this->handleResponse($response, 'debugToken');
    }

    /**
     * List WhatsApp Business Accounts the user can manage.
     */
    public function listWABAs(string $userAccessToken, ?string $businessId = null): array
    {
        $fields = 'id,name,timezone,currency,status,phone_numbers,message_templates';

        if ($businessId) {
            $url = $this->graphUrl("/{$businessId}/whatsapp_business_accounts");
        } else {
            $url = $this->graphUrl('/me/whatsapp_business_accounts');
        }

        $response = Http::timeout(30)->get($url, [
            'access_token' => $userAccessToken,
            'fields' => $fields,
            'limit' => 100,
        ]);

        return $this->handleResponse($response, 'listWABAs')['data'] ?? [];
    }

    /**
     * List phone numbers for a WABA.
     */
    public function listPhoneNumbers(string $wabaId, string $accessToken): array
    {
        $url = $this->graphUrl("/{$wabaId}/phone_numbers");
        $response = Http::timeout(30)->get($url, [
            'access_token' => $accessToken,
            'fields' => 'id,display_phone_number,verified_name,quality_rating,status,account_mode,name_status,certificate',
        ]);

        return $this->handleResponse($response, 'listPhoneNumbers')['data'] ?? [];
    }

    /**
     * Register a phone number with the API.
     */
    public function registerPhoneNumber(string $phoneNumberId, string $accessToken): array
    {
        $url = $this->graphUrl("/{$phoneNumberId}/register");
        $response = Http::timeout(30)->post($url, [
            'messaging_product' => 'whatsapp',
            'access_token' => $accessToken,
        ]);

        return $this->handleResponse($response, 'registerPhoneNumber');
    }

    /**
     * List message templates for a WABA.
     */
    public function listTemplates(string $wabaId, string $accessToken, ?string $status = null): array
    {
        $params = [
            'access_token' => $accessToken,
            'fields' => 'id,name,language,category,status,components',
            'limit' => 100,
        ];

        if ($status) {
            $params['status'] = $status;
        }

        $url = $this->graphUrl("/{$wabaId}/message_templates");
        $response = Http::timeout(30)->get($url, $params);

        return $this->handleResponse($response, 'listTemplates')['data'] ?? [];
    }

    /**
     * Create a new message template.
     */
    public function createTemplate(string $wabaId, string $accessToken, array $template): array
    {
        $url = $this->graphUrl("/{$wabaId}/message_templates");
        $response = Http::timeout(60)->withToken($accessToken, 'Bearer')
            ->post($url, $template);

        return $this->handleResponse($response, 'createTemplate');
    }

    /**
     * Delete a message template.
     */
    public function deleteTemplate(string $wabaId, string $templateName, string $accessToken): bool
    {
        $url = $this->graphUrl("/{$wabaId}/message_templates");
        $response = Http::timeout(30)->withToken($accessToken, 'Bearer')
            ->delete($url, ['name' => $templateName]);

        try {
            $this->handleResponse($response, 'deleteTemplate');
            return true;
        } catch (Exception $e) {
            Log::warning("MetaWhatsApp deleteTemplate failed: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Send a free-form text message.
     */
    public function sendTextMessage(string $phoneNumberId, string $to, string $text, string $accessToken): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizePhone($to),
            'type' => 'text',
            'text' => [
                'body' => $text,
            ],
        ];

        return $this->sendMessage($phoneNumberId, $payload, $accessToken);
    }

    /**
     * Send a template message.
     */
    public function sendTemplateMessage(string $phoneNumberId, string $to, string $templateName, string $languageCode, string $accessToken, array $components = []): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizePhone($to),
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode,
                ],
            ],
        ];

        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        return $this->sendMessage($phoneNumberId, $payload, $accessToken);
    }

    /**
     * Send a message via the Cloud API.
     */
    public function sendMessage(string $phoneNumberId, array $payload, string $accessToken): array
    {
        $url = $this->graphUrl("/{$phoneNumberId}/messages");
        $response = Http::timeout(60)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])
            ->post($url, $payload);

        return $this->handleResponse($response, 'sendMessage');
    }

    /**
     * Verify an incoming webhook signature.
     */
    public function verifyWebhookSignature(string $payload, string $signatureHeader): bool
    {
        if (empty($this->appSecret)) {
            Log::warning('Meta WhatsApp webhook signature verification skipped: app secret missing');
            return false;
        }

        if (empty($signatureHeader) || !str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $payload, $this->appSecret);
        return hash_equals($expected, $signatureHeader);
    }

    /**
     * Validate a webhook verification challenge.
     */
    public function verifyWebhookChallenge(string $mode, string $token, string $challenge): ?string
    {
        if (strtolower($mode) !== 'subscribe') {
            return null;
        }

        if ($token !== $this->verifyToken) {
            return null;
        }

        return $challenge;
    }

    /**
     * Parse webhook payload entry list.
     */
    public function parseWebhookPayload(array $payload): array
    {
        $events = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $events[] = [
                    'value' => $change['value'] ?? [],
                    'field' => $change['field'] ?? null,
                ];
            }
        }

        return $events;
    }

    /**
     * Normalize an E.164-ish phone number.
     */
    protected function normalizePhone(string $phone): string
    {
        return PhoneNormalizer::digits($phone);
    }

    /**
     * Handle Graph API response and surface Meta errors.
     */
    protected function handleResponse(Response $response, string $context): array
    {
        if ($response->successful()) {
            return $response->json() ?? [];
        }

        $body = $response->json() ?? [];
        $error = $body['error']['message'] ?? $body['error']['error_user_msg'] ?? $response->body() ?: 'Meta WhatsApp API request failed';
        $code = $body['error']['code'] ?? $response->status();

        Log::error("Meta WhatsApp API error [{$context}]: code={$code} message={$error}");

        throw new Exception("Meta WhatsApp API error: {$error}", (int) $code);
    }

    protected function requireAppCredentials(): void
    {
        if (empty($this->appId) || empty($this->appSecret)) {
            throw new Exception('Meta WhatsApp App ID and App Secret are not configured.');
        }
    }
}
