<?php

namespace App\Services;

use App\Models\MessengerConversation;
use App\Models\MessengerContact;
use App\Models\MessengerMessage;
use App\Models\MessengerPage;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MessengerService
{
    protected string $graphUrl;
    protected string $apiVersion;
    protected ?string $appId;
    protected ?string $appSecret;
    protected ?string $verifyToken;

    public function __construct()
    {
        $this->graphUrl = rtrim(config('services.messenger.graph_url', 'https://graph.facebook.com'), '/');
        $this->apiVersion = config('services.messenger.api_version', 'v21.0');
        $this->appId = config('services.messenger.app_id') ?: config('services.meta_marketing.app_id');
        $this->appSecret = config('services.messenger.app_secret') ?: config('services.meta_marketing.app_secret');
        $this->verifyToken = config('services.messenger.webhook_verify_token');
    }

    /**
     * Build a Graph API URL.
     */
    public function graphUrl(string $path): string
    {
        return "{$this->graphUrl}/{$this->apiVersion}/" . ltrim($path, '/');
    }

    /**
     * Exchange a short-lived user token for a long-lived user token.
     */
    public function exchangeUserToken(string $shortLivedToken): array
    {
        $this->requireAppCredentials();

        $url = $this->graphUrl('/oauth/access_token');
        $response = Http::timeout(30)->get($url, [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'fb_exchange_token' => $shortLivedToken,
        ]);

        return $this->handleResponse($response, 'exchangeUserToken');
    }

    /**
     * Exchange an OAuth authorization code for a short-lived user token.
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
     * List pages the authenticated user can manage, with access tokens.
     */
    public function listPages(string $userAccessToken, array $fields = ['id', 'name', 'access_token', 'tasks', 'picture']): array
    {
        $url = $this->graphUrl('/me/accounts');
        $response = Http::timeout(30)->get($url, [
            'access_token' => $userAccessToken,
            'fields' => implode(',', $fields),
            'limit' => 100,
        ]);

        return $this->handleResponse($response, 'listPages')['data'] ?? [];
    }

    /**
     * Get page details including access token.
     */
    public function getPage(string $pageId, string $userAccessToken): array
    {
        $url = $this->graphUrl("/{$pageId}");
        $response = Http::timeout(30)->get($url, [
            'access_token' => $userAccessToken,
            'fields' => 'id,name,access_token,picture',
        ]);

        return $this->handleResponse($response, 'getPage');
    }

    /**
     * Subscribe the app to page webhooks.
     */
    public function subscribePageToApp(string $pageId, string $pageAccessToken): bool
    {
        $url = $this->graphUrl("/{$pageId}/subscribed_apps");
        $response = Http::timeout(30)->post($url, [
            'access_token' => $pageAccessToken,
            'subscribed_fields' => [
                'messages',
                'messaging_postbacks',
                'messaging_optins',
                'messaging_deliveries',
                'messaging_reads',
            ],
        ]);

        try {
            $this->handleResponse($response, 'subscribePageToApp');
            return true;
        } catch (Exception $e) {
            Log::warning('Messenger subscribePageToApp failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Unsubscribe the app from page webhooks.
     */
    public function unsubscribePageFromApp(string $pageId, string $pageAccessToken): bool
    {
        $url = $this->graphUrl("/{$pageId}/subscribed_apps") . '?access_token=' . urlencode($pageAccessToken);
        $response = Http::timeout(30)->delete($url);

        try {
            $this->handleResponse($response, 'unsubscribePageFromApp');
            return true;
        } catch (Exception $e) {
            Log::warning('Messenger unsubscribePageFromApp failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send a text message via Messenger Send API.
     */
    public function sendMessage(MessengerPage $page, string $psid, string $text, ?string $tag = null, array $extra = []): array
    {
        $token = $page->getAccessToken();
        if (!$token) {
            throw new Exception('Page access token is missing');
        }

        $payload = [
            'recipient' => ['id' => $psid],
            'message' => ['text' => $text],
        ];

        if ($tag) {
            $payload['messaging_type'] = 'MESSAGE_TAG';
            $payload['tag'] = $tag;
        } else {
            $payload['messaging_type'] = 'RESPONSE';
        }

        if (!empty($extra)) {
            $payload['message'] = array_merge($payload['message'], $extra);
        }

        $url = $this->graphUrl("/{$page->page_id}/messages");
        $response = Http::timeout(30)->post($url, $payload + ['access_token' => $token]);

        return $this->handleResponse($response, 'sendMessage');
    }

    /**
     * Send an attachment (image/video/file/audio) by URL.
     */
    public function sendAttachment(MessengerPage $page, string $psid, string $type, string $url, ?string $tag = null): array
    {
        $token = $page->getAccessToken();
        if (!$token) {
            throw new Exception('Page access token is missing');
        }

        $payload = [
            'recipient' => ['id' => $psid],
            'message' => [
                'attachment' => [
                    'type' => $type,
                    'payload' => ['url' => $url, 'is_reusable' => false],
                ],
            ],
        ];

        if ($tag) {
            $payload['messaging_type'] = 'MESSAGE_TAG';
            $payload['tag'] = $tag;
        } else {
            $payload['messaging_type'] = 'RESPONSE';
        }

        $endpoint = $this->graphUrl("/{$page->page_id}/messages");
        $response = Http::timeout(60)->post($endpoint, $payload + ['access_token' => $token]);

        return $this->handleResponse($response, 'sendAttachment');
    }

    /**
     * Fetch user profile from a PSID.
     */
    public function getProfile(MessengerPage $page, string $psid, array $fields = ['id', 'name', 'profile_pic']): ?array
    {
        $token = $page->getAccessToken();
        if (!$token) {
            return null;
        }

        $url = $this->graphUrl("/{$psid}");
        $response = Http::timeout(15)->get($url, [
            'access_token' => $token,
            'fields' => implode(',', $fields),
        ]);

        try {
            return $this->handleResponse($response, 'getProfile');
        } catch (Exception $e) {
            Log::warning("Messenger getProfile failed for {$psid}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Verify an incoming webhook payload signature (X-Hub-Signature-256).
     */
    public function verifyWebhookSignature(string $payload, string $signatureHeader): bool
    {
        if (empty($this->appSecret)) {
            Log::warning('Messenger webhook signature verification skipped: app secret missing');
            return false;
        }

        if (empty($signatureHeader) || !str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $payload, $this->appSecret);
        return hash_equals($expected, $signatureHeader);
    }

    /**
     * Validate a webhook verification challenge from Meta.
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
     * Extract the messaging event list from a webhook payload.
     */
    public function parseWebhookPayload(array $payload): array
    {
        $events = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            $pageId = $entry['id'] ?? null;
            foreach ($entry['messaging'] ?? [] as $messaging) {
                $events[] = array_merge($messaging, ['page_id' => $pageId]);
            }
        }

        return $events;
    }

    /**
     * Determine the messaging tag to use for an outgoing message.
     */
    public function resolveMessagingTag(MessengerConversation $conversation, bool $isHumanReply = false): ?string
    {
        $lastUserMessage = MessengerMessage::where('conversation_id', $conversation->id)
            ->where('direction', 'incoming')
            ->orderByDesc('created_at')
            ->first();

        if ($lastUserMessage && $lastUserMessage->created_at->diffInHours(now()) < 24) {
            // Within 24h standard window: no tag needed, use RESPONSE.
            return null;
        }

        if ($isHumanReply && $lastUserMessage && $lastUserMessage->created_at->diffInDays(now()) <= 7) {
            // Outside 24h but within 7 days: human agent tag may be used for customer support.
            return 'HUMAN_AGENT';
        }

        // No valid messaging window available.
        throw new Exception('No active messaging window for this recipient. Messenger only allows replies within 24 hours of the last user message (or 7 days with the HUMAN_AGENT tag for support).');
    }

    /**
     * Handle Graph API response and surface Meta errors cleanly.
     */
    protected function handleResponse(Response $response, string $context): array
    {
        if ($response->successful()) {
            return $response->json() ?? [];
        }

        $body = $response->json() ?? [];
        $error = $body['error']['message'] ?? $body['error']['error_user_msg'] ?? $response->body() ?: 'Messenger API request failed';
        $code = $body['error']['code'] ?? $response->status();
        $subcode = $body['error']['error_subcode'] ?? null;

        Log::error("Messenger API error [{$context}]: code={$code} subcode={$subcode} message={$error}");

        throw new Exception("Messenger API error: {$error}", (int) $code);
    }

    protected function requireAppCredentials(): void
    {
        if (empty($this->appId) || empty($this->appSecret)) {
            throw new Exception('Messenger App ID and App Secret are not configured.');
        }
    }
}
