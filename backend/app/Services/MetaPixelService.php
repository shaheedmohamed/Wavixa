<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaPixelService
{
    /**
     * Send a single Facebook/Meta Pixel event from the backend.
     *
     * Convenience wrapper around sendEvents() for controllers that call
     * MetaPixelService::track() with the old static-style signature.
     *
     * @param string $eventName e.g. CompleteRegistration, Purchase, Subscribe
     * @param mixed $user The user model (optional)
     * @param array $customData Extra custom data such as content_name, value, currency
     * @param string|null $eventId Optional event id for deduplication
     * @param string|null $url Optional event source URL
     * @return array
     */
    public static function track(string $eventName, $user = null, array $customData = [], ?string $eventId = null, ?string $url = null): array
    {
        $event = [
            'event_name' => $eventName,
            'event_time' => time(),
            'event_source_url' => $url ?? 'https://wavixa.net',
            'action_source' => 'website',
            'custom_data' => $customData,
        ];

        if ($eventId) {
            $event['event_id'] = $eventId;
        }

        if ($user) {
            $userData = [];

            if (!empty($user->email)) {
                $userData['em'] = self::hash($user->email);
            }

            if (!empty($user->phone_number)) {
                $userData['ph'] = self::hash($user->phone_number);
            }

            if (!empty($user->id)) {
                $event['custom_data']['user_id'] = (string) $user->id;
            }

            if (!empty($userData)) {
                $event['user_data'] = $userData;
            }
        }

        return (new self())->sendEvents([$event]);
    }

    /**
     * Send one or more Facebook/Meta Pixel events from the backend.
     *
     * Prefers the Conversions API when an access token is configured; otherwise
     * falls back to the browser <noscript> /tr endpoint.
     *
     * @param array $events Array of event arrays. Each must contain at least
     *                     event_name and may contain event_source_url,
     *                     custom_data, action_source, user_data and event_id.
     * @return array
     */
    public function sendEvents(array $events): array
    {
        $pixelId = config('services.meta_pixel.pixel_id');
        $accessToken = config('services.meta_pixel.access_token');
        $enabled = config('services.meta_pixel.enabled', true);

        if (!$enabled) {
            return ['success' => false, 'message' => 'Meta Pixel is disabled.'];
        }

        if (empty($pixelId)) {
            Log::warning('MetaPixelService: pixel_id not configured.');
            return ['success' => false, 'message' => 'Pixel ID not configured.'];
        }

        if (!empty($accessToken)) {
            return $this->sendConversionsApi($pixelId, $accessToken, $events);
        }

        return $this->sendTrFallback($pixelId, $events);
    }

    /**
     * Send events through the Meta Conversions API.
     */
    protected function sendConversionsApi(string $pixelId, string $accessToken, array $events): array
    {
        $version = config('services.meta_marketing.api_version', 'v21.0');
        $url = "https://graph.facebook.com/{$version}/{$pixelId}/events";

        $payload = [
            'data' => array_map([$this, 'normalizeEvent'], $events),
            'access_token' => $accessToken,
        ];

        try {
            $response = Http::timeout(30)->post($url, $payload);

            if ($response->successful()) {
                Log::info('MetaPixelService: Conversions API events sent', ['count' => count($events)]);
                return ['success' => true, 'data' => $response->json()];
            }

            $body = $response->json() ?? $response->body();
            Log::warning('MetaPixelService: Conversions API error', ['status' => $response->status(), 'body' => $body]);
            return ['success' => false, 'message' => 'Conversions API error.', 'data' => $body];
        } catch (\Throwable $e) {
            Log::error('MetaPixelService: Conversions API exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Fallback to the public /tr noscript endpoint when no access token is set.
     */
    protected function sendTrFallback(string $pixelId, array $events): array
    {
        $results = [];

        foreach ($events as $event) {
            $params = [
                'id' => $pixelId,
                'ev' => $event['event_name'] ?? 'PageView',
                'dl' => $event['event_source_url'] ?? 'https://wavixa.net/',
                'rl' => $event['referrer'] ?? '',
                'if' => '0',
                'ts' => $event['event_time'] ?? time(),
                'noscript' => '1',
            ];

            if (!empty($event['event_id'])) {
                $params['eid'] = $event['event_id'];
            }

            $customData = $event['custom_data'] ?? [];
            foreach ($customData as $key => $value) {
                if (is_scalar($value) && !is_null($value)) {
                    $params["cd[{$key}]"] = $value;
                }
            }

            try {
                $response = Http::get('https://www.facebook.com/tr', $params);

                $results[] = [
                    'event' => $params['ev'],
                    'sent' => $response->successful(),
                    'status' => $response->status(),
                ];
            } catch (\Throwable $e) {
                Log::error('MetaPixelService: /tr exception', ['error' => $e->getMessage(), 'event' => $event['event_name'] ?? 'PageView']);
                $results[] = [
                    'event' => $params['ev'],
                    'sent' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $failed = collect($results)->where('sent', false)->count();

        if ($failed === 0) {
            Log::info('MetaPixelService: /tr events sent', ['count' => count($events)]);
            return ['success' => true, 'data' => $results];
        }

        Log::warning('MetaPixelService: some /tr events failed', ['failed' => $failed, 'results' => $results]);
        return ['success' => false, 'message' => 'Some events failed.', 'data' => $results];
    }

    /**
     * Normalize an event array for the Conversions API payload.
     */
    protected function normalizeEvent(array $event): array
    {
        $normalized = [
            'event_name' => $event['event_name'] ?? 'PageView',
            'event_time' => $event['event_time'] ?? time(),
            'event_source_url' => $event['event_source_url'] ?? 'https://wavixa.net/',
            'action_source' => $event['action_source'] ?? 'website',
        ];

        if (!empty($event['event_id'])) {
            $normalized['event_id'] = $event['event_id'];
        }

        $userData = array_merge($this->defaultUserData(), $event['user_data'] ?? []);
        if (!empty($userData)) {
            $normalized['user_data'] = $userData;
        }

        $customData = $event['custom_data'] ?? [];
        if (!empty($customData) && is_array($customData)) {
            $normalized['custom_data'] = $customData;
        }

        return $normalized;
    }

    /**
     * Default user data from the current request for event matching.
     */
    protected function defaultUserData(): array
    {
        $ip = request()?->ip();
        $ua = request()?->userAgent();
        $data = [];

        if (!empty($ip)) {
            $data['client_ip_address'] = $ip;
        }

        if (!empty($ua)) {
            $data['client_user_agent'] = $ua;
        }

        return $data;
    }

    /**
     * Normalize and SHA-256 hash a user identifier for the Conversions API.
     */
    protected static function hash(?string $value): string
    {
        return hash('sha256', strtolower(trim((string) $value)));
    }
}
