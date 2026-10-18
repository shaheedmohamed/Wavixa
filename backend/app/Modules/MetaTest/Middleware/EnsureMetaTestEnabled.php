<?php

namespace App\Modules\MetaTest\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMetaTestEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('meta.enabled')) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Meta Test module is disabled.',
                ], 404);
            }

            abort(404, 'Meta Test module is disabled.');
        }

        return $next($request);
    }
}
