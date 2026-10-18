<?php

namespace App\Providers;

use App\Models\ApiKey;
use App\Models\Subscription;
use App\Models\WhatsAppSession;
use App\Services\EmailService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Realtime channel auth — Echo hits /api/broadcasting/auth with the
        // same sanctum bearer token the CRM SPA already uses.
        \Illuminate\Support\Facades\Broadcast::routes(['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']]);

        // Public endpoints (health, pricing, test endpoints)
        RateLimiter::for('public', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // Authentication endpoints (login, register, password reset)
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Authenticated API endpoints
        RateLimiter::for('api', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();
            return Limit::perMinute(120)->by($key);
        });

        // API key endpoints
        RateLimiter::for('api-key', function (Request $request) {
            $apiKey = $request->header('X-API-Key') ?? $request->header('Authorization') ?? $request->query('api_key');
            $apiKey = $apiKey ? str_replace('Bearer ', '', $apiKey) : null;
            return Limit::perMinute(60)->by($apiKey ?: $request->ip());
        });

        // Admin endpoints
        RateLimiter::for('admin', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();
            return Limit::perMinute(120)->by($key);
        });

        // WhatsApp service webhooks (internal, high limit)
        RateLimiter::for('webhook', function (Request $request) {
            return Limit::perMinute(1000)->by($request->ip());
        });

        // Email notifications for important model events
        ApiKey::created(function (ApiKey $apiKey) {
            $apiKey->load('user');
            if ($apiKey->user) {
                resolve(EmailService::class)->sendApiKeyCreated($apiKey->user, $apiKey);
            }
        });

        Subscription::created(function (Subscription $subscription) {
            $subscription->load(['user', 'plan']);
            if ($subscription->user) {
                resolve(EmailService::class)->sendSubscriptionCreated($subscription->user, $subscription);
            }
        });

        WhatsAppSession::updated(function (WhatsAppSession $session) {
            if ($session->wasChanged('status') && $session->status === 'disconnected' && $session->getOriginal('status') !== 'disconnected') {
                $session->load('user');
                if ($session->user) {
                    resolve(EmailService::class)->sendDeviceDisconnected($session->user, $session);
                }
            }
        });
    }
}
