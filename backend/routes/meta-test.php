<?php

use App\Modules\MetaTest\Controllers\MetaTestAccountController;
use App\Modules\MetaTest\Controllers\MetaTestCampaignController;
use App\Modules\MetaTest\Controllers\MetaTestChatController;
use App\Modules\MetaTest\Controllers\MetaTestConnectController;
use App\Modules\MetaTest\Controllers\MetaTestDashboardController;
use App\Modules\MetaTest\Controllers\MetaTestLogController;
use App\Modules\MetaTest\Controllers\MetaTestMessageController;
use App\Modules\MetaTest\Controllers\MetaTestPhoneNumberController;
use App\Modules\MetaTest\Controllers\MetaTestReportController;
use App\Modules\MetaTest\Controllers\MetaTestSetupController;
use App\Modules\MetaTest\Controllers\MetaTestStatusController;
use App\Modules\MetaTest\Controllers\MetaTestTemplateController;
use App\Modules\MetaTest\Controllers\MetaTestWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Meta Official / Tech Provider Test Routes
|--------------------------------------------------------------------------
|
| These routes are completely isolated from the main Wavixa production API.
| They are only loaded when the 'meta.enabled' config is true.
|
| Webhook routes are public; all other routes require authentication.
*/

// Public routes used by Meta during OAuth and webhooks
Route::middleware(['api', 'meta-test'])
    ->group(function () {
        Route::get('/meta-test/callback', [MetaTestConnectController::class, 'callback']);
        Route::get('/meta-test/webhook', [MetaTestWebhookController::class, 'verify']);
        Route::post('/meta-test/webhook', [MetaTestWebhookController::class, 'receive']);
    });

// Authenticated Meta Test API routes
Route::middleware(['meta-test', 'auth:sanctum'])
    ->prefix('meta-test')
    ->group(function () {
        // Dashboard & setup
        Route::get('/dashboard', [MetaTestDashboardController::class, 'index']);
        Route::get('/setup', [MetaTestSetupController::class, 'index']);
        Route::get('/status', [MetaTestStatusController::class, 'index']);
        Route::get('/report', [MetaTestReportController::class, 'index']);

        // Connection / Embedded Signup
        Route::get('/connect', [MetaTestConnectController::class, 'authUrl']);
        Route::post('/disconnect', [MetaTestConnectController::class, 'disconnect']);

        // JavaScript SDK Embedded Signup (supports WhatsApp Business App
        // onboarding / Coexistence). The callback accepts the exchangeable
        // code produced by FB.login — never any secret.
        Route::get('/embedded-signup/config', [MetaTestConnectController::class, 'embeddedSignupConfig']);
        Route::post('/embedded-signup/callback', [MetaTestConnectController::class, 'embeddedSignupCallback'])
            ->middleware('throttle:10,1');

        // Accounts / WABA
        Route::get('/accounts', [MetaTestAccountController::class, 'index']);
        Route::post('/accounts/sync', [MetaTestAccountController::class, 'sync']);

        // Phone numbers
        Route::get('/phone-numbers', [MetaTestPhoneNumberController::class, 'index']);
        Route::post('/phone-numbers/sync', [MetaTestPhoneNumberController::class, 'sync']);
        Route::post('/phone-numbers', [MetaTestPhoneNumberController::class, 'store']);
        Route::post('/phone-numbers/{id}/send-code', [MetaTestPhoneNumberController::class, 'sendCode']);
        Route::post('/phone-numbers/{id}/verify', [MetaTestPhoneNumberController::class, 'verify']);
        Route::post('/phone-numbers/{id}/register', [MetaTestPhoneNumberController::class, 'register']);
        Route::delete('/phone-numbers/{id}', [MetaTestPhoneNumberController::class, 'destroy']);

        // Templates
        Route::get('/chats', [MetaTestChatController::class, 'index']);
        Route::get('/chats/messages', [MetaTestChatController::class, 'messages']);
        Route::post('/chats/send', [MetaTestChatController::class, 'send']);
        Route::post('/chats/send-media', [MetaTestChatController::class, 'sendMedia']);
        Route::post('/chats/name', [MetaTestChatController::class, 'setName']);
        Route::get('/chats/media/{id}', [MetaTestChatController::class, 'media']);

        Route::get('/templates', [MetaTestTemplateController::class, 'index']);
        Route::post('/templates/sync', [MetaTestTemplateController::class, 'sync']);
        Route::post('/templates', [MetaTestTemplateController::class, 'store']);
        Route::delete('/templates/{id}', [MetaTestTemplateController::class, 'destroy']);

        // Bulk campaigns
        Route::prefix('campaigns')->group(function () {
            Route::get('/', [MetaTestCampaignController::class, 'index']);
            Route::get('/stats', [MetaTestCampaignController::class, 'stats']);
            Route::post('/', [MetaTestCampaignController::class, 'store']);
            Route::post('/validate-recipients', [MetaTestCampaignController::class, 'validateRecipients']);
            Route::get('/{id}', [MetaTestCampaignController::class, 'show']);
            Route::put('/{id}', [MetaTestCampaignController::class, 'update']);
            Route::delete('/{id}', [MetaTestCampaignController::class, 'destroy']);
            Route::post('/{id}/recipients', [MetaTestCampaignController::class, 'saveRecipients']);
            Route::get('/{id}/recipients', [MetaTestCampaignController::class, 'recipients']);
            Route::post('/{id}/launch', [MetaTestCampaignController::class, 'launch']);
            Route::post('/{id}/pause', [MetaTestCampaignController::class, 'pause']);
        });

        // Messages / inbox
        Route::get('/messages', [MetaTestMessageController::class, 'index']);
        Route::get('/messages/conversation/{phone}', [MetaTestMessageController::class, 'conversation']);
        Route::post('/messages/send', [MetaTestMessageController::class, 'send']);
        Route::post('/messages/{id}/reply', [MetaTestMessageController::class, 'reply']);

        // Logs
        Route::get('/logs', [MetaTestLogController::class, 'index']);
    });
