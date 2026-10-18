<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: [
            __DIR__.'/../routes/api.php',
            __DIR__.'/../routes/meta-test.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        // API-only backend: never redirect guests to a "login" route (none exists).
        // Returning null yields a clean AuthenticationException -> JSON 401 below.
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'security' => \App\Http\Middleware\SecurityHeaders::class,
            'performance' => \App\Http\Middleware\PerformanceMonitoring::class,
            'meta-test' => \App\Modules\MetaTest\Middleware\EnsureMetaTestEnabled::class,
        ]);

        // Ensure the Meta Test feature flag is evaluated before auth so disabled
        // routes return 404/403 without hitting authentication first.
        $middleware->priority([
            \App\Modules\MetaTest\Middleware\EnsureMetaTestEnabled::class,
            \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class,
            \Illuminate\Contracts\Session\Middleware\AuthenticatesSessions::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        // Apply security and performance monitoring to all API routes
        $middleware->api(prepend: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\PerformanceMonitoring::class,
        ]);

        // Exclude API routes from CSRF protection
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });

        $exceptions->renderable(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please slow down.',
                ], 429);
            }
        });

        $exceptions->renderable(function (\Throwable $e, $request) {
            if (($request->is('api/*') || $request->expectsJson()) && !app()->environment('local')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Server error',
                ], 500);
            }
        });

        $exceptions->reportable(function (\Throwable $e) {
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return;
            }

            try {
                app(\App\Services\AdminAlertService::class)->notifyException($e);
            } catch (\Throwable $alertError) {
                // Prevent alert failures from breaking normal error reporting
            }
        });
    })
    ->withCommands([
        \App\Modules\MetaTest\Console\Commands\MetaTestCommand::class,
        \App\Modules\MetaTest\Console\Commands\MetaTestTokenCommand::class,
        \App\Modules\MetaTest\Console\Commands\MetaTestWebhookCommand::class,
        \App\Modules\MetaTest\Console\Commands\MetaTestWabaCommand::class,
        \App\Modules\MetaTest\Console\Commands\MetaTestPhoneCommand::class,
        \App\Modules\MetaTest\Console\Commands\MetaTestSendCommand::class,
    ])
    ->create();
