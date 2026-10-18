<?php

namespace App\Modules\MetaTest\Services;

use App\Modules\MetaTest\Exceptions\MetaTestApiException;
use App\Modules\MetaTest\Models\MetaTestApiLog;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Low-level client for Meta Graph API used by the Meta Test module.
 *
 * Every request is logged to meta_test_api_logs with secrets masked.
 * This class is independent from the existing MetaWhatsAppService.
 */
class MetaTestGraphClient
{
    protected string $graphUrl;
    protected string $apiVersion;
    protected ?int $userId;
    protected bool $maskSecrets;

    public function __construct(?int $userId = null)
    {
        $this->graphUrl = rtrim(config('meta.graph_url', 'https://graph.facebook.com'), '/');
        $this->apiVersion = config('meta.api_version', 'v21.0');
        $this->userId = $userId;
        $this->maskSecrets = (bool) config('meta.mask_secrets', true);
    }

    public function get(string $path, array $params = [], ?string $bearerToken = null): array
    {
        return $this->request('GET', $path, $params, $bearerToken);
    }

    public function post(string $path, array $payload = [], ?string $bearerToken = null): array
    {
        return $this->request('POST', $path, $payload, $bearerToken);
    }

    public function delete(string $path, array $payload = [], ?string $bearerToken = null): array
    {
        return $this->request('DELETE', $path, $payload, $bearerToken);
    }

    public function request(string $method, string $path, array $payload = [], ?string $bearerToken = null, bool $requiresToken = true): array
    {
        $url = $this->graphUrl('/' . ltrim($path, '/'));
        $token = $bearerToken ?? $this->defaultAccessToken();

        if ($requiresToken && empty($token)) {
            throw new MetaTestApiException('No Meta access token available.', 401);
        }

        $startedAt = now();
        $response = null;
        $exception = null;
        $httpStatus = null;
        $durationMs = null;

        try {
            $headers = ['Content-Type' => 'application/json'];
            if (!empty($token)) {
                $headers['Authorization'] = 'Bearer ' . $token;
            }

            $http = Http::timeout(60)->withHeaders($headers);

            if (strtoupper($method) === 'GET') {
                $response = $http->get($url, $payload);
            } elseif (strtoupper($method) === 'POST') {
                $response = $http->post($url, $payload);
            } elseif (strtoupper($method) === 'DELETE') {
                $response = $http->delete($url, $payload);
            } else {
                throw new MetaTestApiException("Unsupported HTTP method: {$method}", 500);
            }

            $httpStatus = $response->status();
            $durationMs = (int) round((now()->getTimestampMs() - $startedAt->getTimestampMs()));

            $this->logRequest(
                $method,
                $url,
                $payload,
                $response,
                $httpStatus,
                $durationMs,
                $startedAt,
                now()
            );

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            $body = $response->json() ?? [];
            $error = $body['error'] ?? [];
            $message = $error['message'] ?? $response->body() ?: 'Meta Graph API request failed';
            $code = (int) ($error['code'] ?? $response->status());

            throw new MetaTestApiException($message, $code, $error);
        } catch (MetaTestApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $durationMs = $durationMs ?? (int) round((now()->getTimestampMs() - $startedAt->getTimestampMs()));

            $this->logRequest(
                $method,
                $url,
                $payload,
                $response,
                $httpStatus ?: 0,
                $durationMs,
                $startedAt,
                now(),
                $e->getMessage()
            );

            Log::error('MetaTestGraphClient unexpected error: ' . $e->getMessage());

            throw new MetaTestApiException('Meta Graph API transport error: ' . $e->getMessage(), 500, null, $e);
        }
    }

    public function graphUrl(string $path = ''): string
    {
        return "{$this->graphUrl}/{$this->apiVersion}" . ($path ? $path : '');
    }

    public function debugToken(string $token): array
    {
        $appId = config('meta.app_id');
        $appSecret = config('meta.app_secret');

        if (empty($appId) || empty($appSecret)) {
            throw new MetaTestApiException('Meta App ID and App Secret are not configured.', 500);
        }

        return $this->request('GET', '/debug_token', [
            'input_token' => $token,
        ], $appId . '|' . $appSecret);
    }

    public function me(): array
    {
        $token = $this->defaultAccessToken();

        if (empty($token)) {
            throw new MetaTestApiException('No Meta access token available.', 401);
        }

        return $this->request('GET', '/me', [], $token);
    }

    protected function defaultAccessToken(): ?string
    {
        // Return the fallback token from config only. Customer tokens are passed explicitly.
        return config('meta.access_token');
    }

    protected function logRequest(
        string $method,
        string $url,
        array $payload,
        ?Response $response,
        int $httpStatus,
        int $durationMs,
        $startedAt,
        $respondedAt,
        ?string $extraError = null
    ): MetaTestApiLog {
        $body = [];
        $metaErrorCode = null;
        $metaErrorMessage = null;
        $success = $httpStatus >= 200 && $httpStatus < 300;

        if ($response) {
            try {
                $body = $response->json() ?? [];
                if (!empty($body['error'])) {
                    $metaErrorCode = $body['error']['code'] ?? null;
                    $metaErrorMessage = $body['error']['message'] ?? null;
                }
            } catch (\Throwable $e) {
                $body = ['raw' => $response->body()];
            }
        } elseif ($extraError) {
            $metaErrorMessage = $extraError;
            $success = false;
        }

        return MetaTestApiLog::create([
            'user_id' => $this->userId,
            'method' => $method,
            'endpoint' => $url,
            'request_payload' => $this->mask($payload),
            'response_payload' => $this->mask($body),
            'http_status' => $httpStatus,
            'meta_error_code' => $metaErrorCode ? (string) $metaErrorCode : null,
            'meta_error_message' => $metaErrorMessage,
            'duration_ms' => $durationMs,
            'success' => $success,
            'requested_at' => $startedAt,
            'responded_at' => $respondedAt,
        ]);
    }

    /**
     * Recursively mask secret values before persisting to the database.
     */
    protected function mask(array $data): array
    {
        if (!$this->maskSecrets) {
            return $data;
        }

        $sensitiveKeys = [
            'access_token',
            'client_secret',
            'app_secret',
            'token',
            'authorization',
            'Authorization',
            'code',
            'fb_exchange_token',
            'input_token',
        ];

        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $value = $this->mask($value);
            } elseif (is_string($value) && (is_int($key) ? false : in_array(strtolower($key), array_map('strtolower', $sensitiveKeys), true))) {
                $value = $this->maskValue($value);
            }
        }

        return $data;
    }

    protected function maskValue(string $value): string
    {
        $len = strlen($value);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }

        return substr($value, 0, 4) . str_repeat('*', $len - 8) . substr($value, -4);
    }
}
