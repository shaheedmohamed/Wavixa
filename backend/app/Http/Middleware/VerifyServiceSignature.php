<?php

namespace App\Http\Middleware;

use App\Services\FraudDetectionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyServiceSignature
{
    /**
     * Handle an incoming service webhook request.
     *
     * The calling Node service must sign the raw JSON body with
     * HMAC-SHA256 using the shared service API key.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = $this->secretFor($request);

        if (!$secret) {
            return response()->json([
                'success' => false,
                'message' => 'Service webhook secret not configured.',
            ], 401);
        }

        $signature = $request->header('X-Service-Signature');

        if (!$signature) {
            FraudDetectionService::report('missing_service_signature', 'critical', 'Service webhook received without X-Service-Signature header', [
                'endpoint' => $request->path(),
                'expected_secret_source' => $request->is('api/telegram/*') ? 'telegram' : 'whatsapp',
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Missing service signature.',
            ], 401);
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (!hash_equals($expected, $signature)) {
            FraudDetectionService::report('invalid_service_signature', 'critical', 'Invalid service webhook signature (possible spoofing attempt)', [
                'endpoint' => $request->path(),
                'expected_secret_source' => $request->is('api/telegram/*') ? 'telegram' : 'whatsapp',
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid service signature.',
            ], 401);
        }

        return $next($request);
    }

    /**
     * Pick the correct shared secret based on the webhook path.
     */
    private function secretFor(Request $request): ?string
    {
        $path = $request->path();

        if (str_starts_with($path, 'api/telegram/')) {
            return config('services.telegram.api_key');
        }

        if (str_starts_with($path, 'api/whatsapp/')) {
            return config('services.whatsapp.api_key');
        }

        // Fallback for any other service routes.
        return config('services.whatsapp.api_key');
    }
}
