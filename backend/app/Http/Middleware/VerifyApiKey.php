<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\ApiLog;
use App\Services\FraudDetectionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $this->extractApiKey($request);

        if (!$apiKey) {
            $this->logRequest($request, null, 401, 0, 0, 0, 'API key missing');
            FraudDetectionService::report('api_key_missing', 'high', 'API key request without valid X-API-Key or Authorization header', [
                'endpoint' => $request->path(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'API key is required',
            ], 401);
        }

        $keyRecord = ApiKey::where('key', $apiKey)->first();

        if (!$keyRecord) {
            $this->logRequest($request, null, 401, 0, 0, 0, 'Invalid API key');
            FraudDetectionService::report('api_key_invalid', 'high', 'Request used an unrecognized API key', [
                'endpoint' => $request->path(),
                'api_key_prefix' => substr($apiKey, 0, 8) . '***',
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid API key',
            ], 401);
        }

        if (!$keyRecord->isUsable()) {
            $this->logRequest($request, $keyRecord, 403, 0, 0, 0, 'API key is inactive or expired');
            FraudDetectionService::report('api_key_inactive', 'medium', 'Request used an inactive or expired API key', [
                'endpoint' => $request->path(),
                'api_key_id' => $keyRecord->id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'API key is inactive or expired',
            ], 403);
        }

        // Update usage stats
        $keyRecord->markUsed();

        // Attach API key and user to request
        $request->attributes->set('api_key', $keyRecord);
        $request->attributes->set('api_key_user', $keyRecord->user);

        $start = microtime(true);
        $requestSize = strlen($request->getContent());

        $response = $next($request);

        $duration = (int) ((microtime(true) - $start) * 1000);
        $responseSize = strlen($response->getContent());

        // Log the request
        $this->logRequest($request, $keyRecord, $response->getStatusCode(), $duration, $requestSize, $responseSize, 'Success');

        return $response;
    }

    /**
     * Extract API key from request header only.
     *
     * Query-string API keys are intentionally disallowed because they are
     * leaked to server logs, browser history, and referrer headers.
     */
    private function extractApiKey(Request $request): ?string
    {
        // Header: X-API-Key or Authorization: Bearer <key>
        $header = $request->header('X-API-Key');
        if ($header) {
            return $header;
        }

        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        return null;
    }

    /**
     * Log API request
     */
    private function logRequest(Request $request, ?ApiKey $apiKey, int $statusCode, int $duration, int $requestSize, int $responseSize, string $message): void
    {
        try {
            ApiLog::create([
                'api_key_id' => $apiKey?->id,
                'user_id' => $apiKey?->user_id,
                'method' => $request->method(),
                'endpoint' => $request->path(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status_code' => $statusCode,
                'duration_ms' => $duration,
                'request_size' => $requestSize,
                'response_size' => $responseSize,
                'country' => $request->header('CF-IPCountry'), // Cloudflare header if available
                'response_message' => $message,
                'request_data' => $request->except(['password', 'key', 'api_key', 'token', 'secret', 'otp', 'code', 'gateway_response', 'raw', 'authorization', 'x-api-key']),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to log API request: ' . $e->getMessage());
        }
    }
}
