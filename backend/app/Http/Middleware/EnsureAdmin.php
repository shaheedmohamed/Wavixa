<?php

namespace App\Http\Middleware;

use App\Services\FraudDetectionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->is_admin) {
            FraudDetectionService::report('admin_access_denied', 'high', 'Non-admin user or guest attempted to access admin endpoint', [
                'endpoint' => $request->path(),
                'user_id' => $user?->id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Admin access required.',
            ], 403);
        }

        return $next($request);
    }
}
