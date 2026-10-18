<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Services\FraudDetectionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthOrApiKey
{
    /**
     * Handle an incoming request. Allow either Sanctum auth or API key.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check Sanctum authentication first
        if (Auth::guard('sanctum')->check()) {
            Auth::shouldUse('sanctum');
            return $next($request);
        }

        // Check API key authentication via headers only
        $apiKey = $request->header('X-API-Key') ?? $request->header('Authorization');

        if ($apiKey) {
            $apiKey = str_replace('Bearer ', '', $apiKey);

            $key = ApiKey::where('key', $apiKey)->first();

            if ($key && $key->isUsable()) {
                $key->increment('requests_count');
                $key->update(['last_used_at' => now()]);
                $request->attributes->set('api_key_user', $key->user);
                $request->attributes->set('api_key', $key);
                return $next($request);
            }

            FraudDetectionService::report('auth_or_apikey_invalid', 'high', 'Request to protected API endpoint carried an invalid or inactive API key', [
                'endpoint' => $request->path(),
                'api_key_prefix' => substr($apiKey, 0, 8) . '***',
            ]);
        } else {
            FraudDetectionService::report('auth_or_apikey_missing', 'high', 'Request to protected API endpoint without auth token or API key', [
                'endpoint' => $request->path(),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated.',
        ], 401);
    }
}
