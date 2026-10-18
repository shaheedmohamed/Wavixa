<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PerformanceMonitoring
{
    /**
     * Monitor request duration and log slow requests.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $duration = (microtime(true) - $start) * 1000;

        $response->headers->set('X-Response-Time', round($duration, 2) . 'ms');

        if ($duration > 500) {
            Log::warning('Slow request detected', [
                'method' => $request->getMethod(),
                'url' => $request->url(),
                'duration_ms' => round($duration, 2),
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
            ]);
        }

        return $response;
    }
}
