<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Auth\Controllers\ForgotPasswordController;

use App\Modules\WhatsApp\Controllers\WhatsAppController;
use App\Modules\WhatsApp\Controllers\CampaignController;
use App\Modules\WhatsApp\Controllers\MetaWhatsAppController;
use App\Modules\WhatsApp\Controllers\MetaWhatsAppCampaignController;
use App\Modules\WhatsApp\Controllers\AutoReplyController;
use App\Http\Controllers\WhatsAppCloudWebhookController;
use App\Modules\Telegram\Controllers\TelegramController;
use App\Modules\Telegram\Controllers\TelegramAutoReplyController;
use App\Modules\Telegram\Controllers\TelegramCampaignController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\PublicApi\PublicTelegramController;
use App\Modules\Messenger\Controllers\MessengerController;
use App\Modules\Messenger\Controllers\MessengerAutoReplyController;
use App\Modules\Messenger\Controllers\MessengerCampaignController;
use App\Modules\Messenger\Controllers\MessengerAdController;
use App\Modules\Messenger\Controllers\MessengerWebhookController;
use App\Http\Middleware\AuthOrApiKey;
use App\Http\Middleware\HandleCors;
use App\Http\Middleware\VerifyApiKey;
use App\Http\Middleware\VerifyServiceSignature;
use App\Http\Controllers\ApiKeysController;
use App\Http\Controllers\PublicApi\PublicWhatsAppController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\N8nController;
use App\Http\Controllers\PublicApi\N8nApiController;
use App\Modules\Billing\Controllers\SubscriptionController;
use App\Modules\Billing\Controllers\BillingController;
use App\Modules\Billing\Controllers\PlanController;
use App\Modules\Billing\Controllers\PayPalController;
use App\Modules\Auth\Controllers\SettingsController;
use App\Modules\Dashboard\Controllers\AdminController;
use App\Http\Controllers\Admin\MetaAdsController;
use App\Http\Controllers\Admin\MessengerAdminController;
use App\Http\Controllers\Api\MetaPixelController;
use App\Http\Controllers\Api\ReputationController;
use App\Http\Controllers\VoiceAssistantController;

Route::middleware(['api', HandleCors::class])->group(function () {
    // Internal service webhooks. Signed by the Node services with HMAC-SHA256
    // using the shared service API key; rate-limited separately from public traffic.
    Route::middleware(['throttle:webhook', VerifyServiceSignature::class])->group(function () {
        // WhatsApp service webhooks
        Route::post('/whatsapp/message-received', [\App\Http\Controllers\WhatsAppWebhookController::class, 'messageReceived']);
        Route::post('/whatsapp/message-sent', [\App\Http\Controllers\WhatsAppWebhookController::class, 'messageSent']);
        Route::post('/whatsapp/message-delivered', [\App\Http\Controllers\WhatsAppWebhookController::class, 'messageDelivered']);
        Route::post('/whatsapp/message-read', [\App\Http\Controllers\WhatsAppWebhookController::class, 'messageRead']);
        Route::post('/whatsapp/qr-generated', [\App\Http\Controllers\WhatsAppWebhookController::class, 'qrGenerated']);
        Route::post('/whatsapp/connected', [\App\Http\Controllers\WhatsAppWebhookController::class, 'connected']);
        Route::post('/whatsapp/disconnected', [\App\Http\Controllers\WhatsAppWebhookController::class, 'disconnected']);
        Route::post('/whatsapp/message-reaction', [\App\Http\Controllers\WhatsAppWebhookController::class, 'messageReaction']);
        Route::post('/whatsapp/message-revoked', [\App\Http\Controllers\WhatsAppWebhookController::class, 'messageRevoked']);
        Route::post('/whatsapp/history-sync', [\App\Http\Controllers\WhatsAppWebhookController::class, 'historySync']);
        Route::post('/whatsapp/message-media', [\App\Http\Controllers\WhatsAppWebhookController::class, 'messageMedia']);
        Route::post('/whatsapp/chatstate', [\App\Http\Controllers\WhatsAppWebhookController::class, 'chatstate']);

        // Telegram service webhooks
        Route::post('/telegram/connected', [TelegramWebhookController::class, 'connected']);
        Route::post('/telegram/disconnected', [TelegramWebhookController::class, 'disconnected']);
        Route::post('/telegram/qr-generated', [TelegramWebhookController::class, 'qrGenerated']);
        Route::post('/telegram/message-received', [TelegramWebhookController::class, 'messageReceived']);
    });

    // Public endpoints with shared rate limiting
    Route::middleware('throttle:public')->group(function () {
        // Webhook test receiver (public endpoint for testing)
        Route::post('/webhook-test-receiver', [\App\Http\Controllers\WebhookTestReceiver::class, 'receive']);
        Route::get('/webhook-test-receiver', [\App\Http\Controllers\WebhookTestReceiver::class, 'getReceived']);
        Route::delete('/webhook-test-receiver', [\App\Http\Controllers\WebhookTestReceiver::class, 'clear']);

        // Messenger webhooks (public endpoints for Meta events)
        Route::get('/messenger/webhook', [MessengerWebhookController::class, 'verify']);
        Route::post('/messenger/webhook', [MessengerWebhookController::class, 'receive']);

        // Messenger OAuth callback (public, server-side redirect from Facebook)
        Route::get('/messenger/callback', [MessengerController::class, 'oauthCallback']);

        // WhatsApp Cloud webhooks (public endpoints for Meta events)
        Route::get('/whatsapp/cloud/webhook', [WhatsAppCloudWebhookController::class, 'verify']);
        Route::post('/whatsapp/cloud/webhook', [WhatsAppCloudWebhookController::class, 'receive']);

        // WhatsApp Cloud OAuth callback (public, server-side redirect from Facebook)
        Route::get('/whatsapp/cloud/callback', [MetaWhatsAppController::class, 'callback']);

        // Public link redirect endpoints
        Route::get('/r/{code}', [\App\Http\Controllers\LinkRedirectController::class, 'redirect'])->where('code', '[a-zA-Z0-9_-]+');
        Route::get('/l/{code}', [\App\Http\Controllers\LinkRedirectController::class, 'redirect'])->where('code', '[a-zA-Z0-9_-]+');

        // Health check
        // Meta Pixel Conversions API (also used by frontend as /api/meta-pixel/*)
        Route::prefix('meta-pixel')->group(function () {
            Route::post('/track', [MetaPixelController::class, 'track']);
            Route::post('/test', [MetaPixelController::class, 'test']);
        });

        Route::get('/health', function () {
            return response()->json([
                'success' => true,
                'message' => 'API is running',
                'timestamp' => now(),
            ]);
        });

        // Voice AI custom LLM endpoint (called by Agora Conversational AI Engine)
        Route::post('/voice/chat', [VoiceAssistantController::class, 'chat']);
    });

    // Public auth routes
    Route::middleware('throttle:auth')->prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/forgot-password-phone', [ForgotPasswordController::class, 'sendOtp']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
        Route::post('/reset-password-phone', [ForgotPasswordController::class, 'reset']);
        Route::post('/verify-otp', [AuthController::class, 'verifyOTP']);
        Route::post('/resend-otp', [AuthController::class, 'resendOTP']);
        Route::post('/resend-verification-email', [AuthController::class, 'resendVerificationEmail']);
        Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');
    });


    // Protected auth routes
    Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
        Route::get('/onboarding', [AuthController::class, 'getOnboarding']);
        Route::put('/onboarding', [AuthController::class, 'updateOnboarding']);
    });

    // User reputation and anti-abuse score
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('/reputation', [ReputationController::class, 'show']);
    });

    // PayPal configuration (public)
    Route::get('/paypal/config', [PayPalController::class, 'config']);

    // PayPal payment routes
    Route::middleware('auth:sanctum')->prefix('paypal')->group(function () {
        Route::post('/create-order', [PayPalController::class, 'createOrder']);
        Route::post('/capture-order', [PayPalController::class, 'captureOrder']);
        Route::get('/order-details', [PayPalController::class, 'orderDetails']);
    });

    // Protected settings routes
    Route::middleware('auth:sanctum')->prefix('settings')->group(function () {
        Route::get('/', [SettingsController::class, 'index']);
        Route::put('/profile', [SettingsController::class, 'updateProfile']);
        Route::post('/avatar', [SettingsController::class, 'uploadAvatar']);
        Route::delete('/avatar', [SettingsController::class, 'removeAvatar']);
        Route::post('/change-password', [SettingsController::class, 'changePassword']);
        Route::put('/notifications', [SettingsController::class, 'updateNotifications']);
        Route::delete('/account', [SettingsController::class, 'deleteAccount']);
        Route::get('/login-history', [SettingsController::class, 'loginHistory']);
        Route::get('/devices', [SettingsController::class, 'devices']);
        Route::delete('/devices/{id}', [SettingsController::class, 'revokeDevice']);
        Route::post('/devices/revoke-others', [SettingsController::class, 'revokeOtherDevices']);
    });

    // Protected dashboard routes
    Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('dashboard')->group(function () {
        Route::get('/', function (Request $request) {
            return response()->json([
                'success' => true,
                'message' => 'Dashboard data',
                'data' => [
                    'user' => $request->user(),
                    'stats' => [
                        'total_sessions' => 0,
                        'total_messages' => 0,
                        'active_sessions' => 0,
                    ],
                ],
            ]);
        });
    });

    // Protected Voice AI routes
    Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('voice')->group(function () {
        Route::post('/sessions', [VoiceAssistantController::class, 'start']);
        Route::post('/sessions/{sessionId}/transcript', [VoiceAssistantController::class, 'transcript']);
        Route::post('/sessions/{sessionId}/end', [VoiceAssistantController::class, 'end']);
        Route::get('/sessions/{sessionId}/history', [VoiceAssistantController::class, 'history']);
    });

    // Protected WhatsApp routes
    Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('whatsapp')->group(function () {
        Route::get('/status', [WhatsAppController::class, 'getStatus']);
        Route::post('/connect', [WhatsAppController::class, 'connect']);
        Route::post('/disconnect', [WhatsAppController::class, 'disconnect']);
        Route::post('/send-message', [WhatsAppController::class, 'sendMessage']);
        Route::post('/delete-session', [WhatsAppController::class, 'deleteSession']);
        Route::get('/qr', [WhatsAppController::class, 'getQR']);
        Route::get('/dashboard-stats', [WhatsAppController::class, 'getDashboardStats']);
        Route::get('/sessions', [WhatsAppController::class, 'listSessions']);

        // Auto Reply
        Route::get('/auto-reply', [AutoReplyController::class, 'getSettings']);
        Route::post('/auto-reply', [AutoReplyController::class, 'saveSettings']);
        Route::get('/auto-reply/logs', [AutoReplyController::class, 'getLogs']);
        Route::post('/auto-reply/test-ai', [AutoReplyController::class, 'testAI']);

        // Campaigns
        Route::prefix('campaigns')->group(function () {
            Route::get('/', [CampaignController::class, 'index']);
            Route::get('/stats', [CampaignController::class, 'stats']);
            Route::post('/', [CampaignController::class, 'store']);
            Route::get('/{id}', [CampaignController::class, 'show']);
            Route::put('/{id}', [CampaignController::class, 'update']);
            Route::delete('/{id}', [CampaignController::class, 'destroy']);
            Route::post('/validate-recipients', [CampaignController::class, 'validateRecipients']);
            Route::post('/{id}/recipients/validate', [CampaignController::class, 'validateRecipients']);
            Route::post('/{id}/recipients', [CampaignController::class, 'saveRecipients']);
            Route::get('/{id}/recipients', [CampaignController::class, 'recipients']);
            Route::post('/{id}/launch', [CampaignController::class, 'launch']);
            Route::post('/{id}/pause', [CampaignController::class, 'pause']);
            Route::post('/{id}/resume', [CampaignController::class, 'resume']);
            Route::post('/{id}/resend-failed', [CampaignController::class, 'resendFailed']);
        });

        // Meta Pixel Conversions API
        Route::prefix('meta-pixel')->group(function () {
            Route::post('/track', [MetaPixelController::class, 'track']);
            Route::post('/test', [MetaPixelController::class, 'test']);
        });

        // Meta WhatsApp Cloud (Official API)
        Route::prefix('cloud')->group(function () {
            Route::get('/auth-url', [MetaWhatsAppController::class, 'authUrl']);
            Route::get('/accounts', [MetaWhatsAppController::class, 'accounts']);
            Route::get('/sync', [MetaWhatsAppController::class, 'sync']);
            Route::post('/connect', [MetaWhatsAppController::class, 'connect']);
            Route::get('/numbers', [MetaWhatsAppController::class, 'index']);
            Route::delete('/numbers/{id}', [MetaWhatsAppController::class, 'destroy']);
            Route::post('/send', [MetaWhatsAppController::class, 'send']);

            // Templates
            Route::get('/templates', [MetaWhatsAppController::class, 'templates']);
            Route::post('/templates', [MetaWhatsAppController::class, 'storeTemplate']);
            Route::delete('/templates/{id}', [MetaWhatsAppController::class, 'destroyTemplate']);

            // Campaigns
            Route::prefix('campaigns')->group(function () {
                Route::get('/', [MetaWhatsAppCampaignController::class, 'index']);
                Route::get('/stats', [MetaWhatsAppCampaignController::class, 'stats']);
                Route::post('/', [MetaWhatsAppCampaignController::class, 'store']);
                Route::get('/{id}', [MetaWhatsAppCampaignController::class, 'show']);
                Route::put('/{id}', [MetaWhatsAppCampaignController::class, 'update']);
                Route::delete('/{id}', [MetaWhatsAppCampaignController::class, 'destroy']);
                Route::post('/validate-recipients', [MetaWhatsAppCampaignController::class, 'validateRecipients']);
                Route::post('/{id}/recipients/validate', [MetaWhatsAppCampaignController::class, 'validateRecipients']);
                Route::post('/{id}/recipients', [MetaWhatsAppCampaignController::class, 'saveRecipients']);
                Route::get('/{id}/recipients', [MetaWhatsAppCampaignController::class, 'recipients']);
                Route::post('/{id}/launch', [MetaWhatsAppCampaignController::class, 'launch']);
                Route::post('/{id}/pause', [MetaWhatsAppCampaignController::class, 'pause']);
            });
        });
    });

    // Protected Telegram routes
    Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('telegram')->group(function () {
        Route::get('/sessions', [TelegramController::class, 'getSessions']);
        Route::get('/status', [TelegramController::class, 'getStatus']);
        Route::post('/connect', [TelegramController::class, 'connectBot']);
        Route::post('/disconnect/{id}', [TelegramController::class, 'disconnectBot']);
        Route::post('/sync/{id}', [TelegramController::class, 'syncWebhook']);
        Route::delete('/sessions/{id}', [TelegramController::class, 'deleteBot']);
        Route::post('/send-message', [TelegramController::class, 'sendMessage']);
        Route::post('/upload-media', [TelegramController::class, 'uploadMedia']);
        Route::get('/messages', [TelegramController::class, 'getMessages']);
        Route::get('/dashboard-stats', [TelegramController::class, 'getDashboardStats']);

        // Auto Reply
        Route::get('/auto-reply', [TelegramAutoReplyController::class, 'getSettings']);
        Route::post('/auto-reply', [TelegramAutoReplyController::class, 'saveSettings']);
        Route::get('/auto-reply/logs', [TelegramAutoReplyController::class, 'getLogs']);
        Route::post('/auto-reply/test-ai', [TelegramAutoReplyController::class, 'testAI']);

        // Campaigns
        Route::prefix('campaigns')->group(function () {
            Route::get('/', [TelegramCampaignController::class, 'index']);
            Route::get('/stats', [TelegramCampaignController::class, 'stats']);
            Route::post('/', [TelegramCampaignController::class, 'store']);
            Route::get('/{id}', [TelegramCampaignController::class, 'show']);
            Route::put('/{id}', [TelegramCampaignController::class, 'update']);
            Route::delete('/{id}', [TelegramCampaignController::class, 'destroy']);
            Route::post('/{id}/recipients/validate', [TelegramCampaignController::class, 'validateRecipients']);
            Route::post('/{id}/recipients', [TelegramCampaignController::class, 'saveRecipients']);
            Route::get('/{id}/recipients', [TelegramCampaignController::class, 'recipients']);
            Route::post('/{id}/launch', [TelegramCampaignController::class, 'launch']);
            Route::post('/{id}/pause', [TelegramCampaignController::class, 'pause']);
            Route::post('/{id}/resume', [TelegramCampaignController::class, 'resume']);
            Route::post('/{id}/resend-failed', [TelegramCampaignController::class, 'resendFailed']);
        });
    });

    // Protected Messenger routes
    Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('messenger')->group(function () {
        Route::get('/config', [MessengerController::class, 'config']);
        Route::get('/auth', [MessengerController::class, 'oauthAuth']);
        Route::get('/status', [MessengerController::class, 'status']);
        Route::post('/connect/token', [MessengerController::class, 'pagesFromToken']);
        Route::post('/connect', [MessengerController::class, 'connect']);
        Route::post('/disconnect/{id}', [MessengerController::class, 'disconnect']);
        Route::post('/reconnect/{id}', [MessengerController::class, 'reconnect']);
        Route::get('/dashboard-stats', [MessengerController::class, 'dashboardStats']);
        Route::get('/inbox', [MessengerController::class, 'inbox']);
        Route::get('/conversations/{id}/messages', [MessengerController::class, 'conversationMessages']);
        Route::post('/send-message', [MessengerController::class, 'sendMessage']);
        Route::post('/conversations/{id}/toggle-ai', [MessengerController::class, 'toggleAi']);
        Route::get('/contacts', [MessengerController::class, 'contacts']);
        Route::get('/settings/{id}', [MessengerController::class, 'settings']);

        // Auto Reply
        Route::get('/auto-reply', [MessengerAutoReplyController::class, 'getSettings']);
        Route::post('/auto-reply', [MessengerAutoReplyController::class, 'saveSettings']);
        Route::get('/auto-reply/logs', [MessengerAutoReplyController::class, 'getLogs']);
        Route::post('/auto-reply/test-ai', [MessengerAutoReplyController::class, 'testAI']);

        // Ads
        Route::prefix('ads')->group(function () {
            Route::post('/test', [MessengerAdController::class, 'test']);
            Route::post('/', [MessengerAdController::class, 'store']);
        });

        // Campaigns
        Route::prefix('campaigns')->group(function () {
            Route::get('/', [MessengerCampaignController::class, 'index']);
            Route::get('/stats', [MessengerCampaignController::class, 'stats']);
            Route::post('/', [MessengerCampaignController::class, 'store']);
            Route::get('/{id}', [MessengerCampaignController::class, 'show']);
            Route::put('/{id}', [MessengerCampaignController::class, 'update']);
            Route::delete('/{id}', [MessengerCampaignController::class, 'destroy']);
            Route::post('/{id}/recipients/validate', [MessengerCampaignController::class, 'validateRecipients']);
            Route::post('/{id}/recipients', [MessengerCampaignController::class, 'saveRecipients']);
            Route::get('/{id}/recipients', [MessengerCampaignController::class, 'recipients']);
            Route::post('/{id}/launch', [MessengerCampaignController::class, 'launch']);
            Route::post('/{id}/pause', [MessengerCampaignController::class, 'pause']);
            Route::post('/{id}/resume', [MessengerCampaignController::class, 'resume']);
            Route::post('/{id}/resend-failed', [MessengerCampaignController::class, 'resendFailed']);
        });
    });

    // Protected Messages routes
    Route::middleware([AuthOrApiKey::class, 'throttle:api-key'])->group(function () {
        Route::get('/messages', [MessageController::class, 'index']);
        Route::get('/messages/filters', [MessageController::class, 'filters']);
        Route::get('/messages/{id}', [MessageController::class, 'show']);
        Route::post('/messages/{id}/resend', [MessageController::class, 'resend']);
        Route::get('/messages/export/csv', [MessageController::class, 'export']);
    });

    // Protected Contacts routes
    Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('contacts')->group(function () {
        Route::get('/', [ContactController::class, 'index']);
        Route::post('/', [ContactController::class, 'store']);
        Route::post('/import', [ContactController::class, 'import']);
        Route::delete('/{id}', [ContactController::class, 'destroy']);
    });

    // Protected Webhooks routes
    Route::middleware([AuthOrApiKey::class, 'throttle:api-key'])->group(function () {
        Route::get('/webhooks', [WebhookController::class, 'index']);
        Route::post('/webhooks', [WebhookController::class, 'store']);
        Route::get('/webhooks/events', [WebhookController::class, 'events']);
        Route::put('/webhooks/{id}', [WebhookController::class, 'update']);
        Route::delete('/webhooks/{id}', [WebhookController::class, 'destroy']);
        Route::post('/webhooks/{id}/test', [WebhookController::class, 'test']);
        Route::get('/webhooks/{id}/logs', [WebhookController::class, 'logs']);
        Route::post('/webhook-logs/{id}/resend', [WebhookController::class, 'resendLog']);
    });

    // Protected API Keys routes
    Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('api-keys')->group(function () {
        Route::get('/', [ApiKeysController::class, 'index']);
        Route::post('/', [ApiKeysController::class, 'store']);
        Route::post('/{id}/regenerate', [ApiKeysController::class, 'regenerate']);
        Route::post('/{id}/test', [ApiKeysController::class, 'test']);
        Route::put('/{id}', [ApiKeysController::class, 'update']);
        Route::delete('/{id}', [ApiKeysController::class, 'destroy']);
        Route::get('/{id}/logs', [ApiKeysController::class, 'logs']);
    });

    // Protected n8n integration routes
    Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('n8n')->group(function () {
        Route::get('/', [N8nController::class, 'index']);
        Route::post('/', [N8nController::class, 'store']);
        Route::get('/status', [N8nController::class, 'status']);
        Route::get('/stats', [N8nController::class, 'stats']);
        Route::get('/sessions', [N8nController::class, 'sessions']);
        Route::get('/templates', [N8nController::class, 'templates']);
        Route::get('/{id}', [N8nController::class, 'show']);
        Route::put('/{id}', [N8nController::class, 'update']);
        Route::delete('/{id}', [N8nController::class, 'destroy']);
        Route::post('/{id}/test', [N8nController::class, 'test']);
    });

    // Public plans (pricing page)
    Route::middleware('throttle:public')->prefix('plans')->group(function () {
        Route::get('/', [PlanController::class, 'index']);
        Route::get('/{slug}', [PlanController::class, 'show']);
    });

    // Protected subscription routes
    Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('subscription')->group(function () {
        Route::get('/', [SubscriptionController::class, 'status']);
        Route::post('/upgrade', [SubscriptionController::class, 'upgrade']);
        Route::post('/renew', [SubscriptionController::class, 'renew']);
        Route::post('/cancel', [SubscriptionController::class, 'cancel']);
    });

    // Protected billing routes
    Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('billing')->group(function () {
        Route::get('/transactions', [BillingController::class, 'transactions']);
        Route::get('/invoices', [BillingController::class, 'invoices']);
        Route::get('/invoices/{id}', [BillingController::class, 'showInvoice']);
    });

    // Admin plan management routes
    Route::middleware(['auth:sanctum', 'admin', 'throttle:admin'])->prefix('admin/plans')->group(function () {
        Route::get('/', [AdminController::class, 'plans']);
        Route::post('/', [PlanController::class, 'store']);
        Route::put('/{id}', [PlanController::class, 'update']);
        Route::delete('/{id}', [PlanController::class, 'destroy']);
    });

    // Admin dashboard routes
    Route::middleware(['auth:sanctum', 'admin', 'throttle:admin'])->prefix('admin')->group(function () {
        Route::get('/overview', [AdminController::class, 'overview']);

        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/users/{id}', [AdminController::class, 'showUser']);
        Route::put('/users/{id}', [AdminController::class, 'updateUser']);
        Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);
        Route::post('/users/{id}/suspend', [AdminController::class, 'suspendUser']);
        Route::post('/users/{id}/ban', [AdminController::class, 'banUser']);
        Route::post('/users/{id}/reactivate', [AdminController::class, 'reactivateUser']);
        Route::post('/users/{id}/login-as', [AdminController::class, 'loginAsUser']);
        Route::post('/users/{id}/balance', [AdminController::class, 'adjustUserBalance']);
        Route::post('/users/{id}/change-plan', [AdminController::class, 'changeUserPlan']);
        Route::get('/users/{id}/login-history', [AdminController::class, 'userLoginHistory']);
        Route::get('/users/{id}/devices', [AdminController::class, 'userDevices']);
        Route::get('/users/{id}/api-keys', [AdminController::class, 'userApiKeys']);
        Route::get('/users/{id}/messages', [AdminController::class, 'userMessages']);

        Route::get('/sessions', [AdminController::class, 'sessions']);
        Route::delete('/sessions/{id}', [AdminController::class, 'deleteSession']);
        Route::post('/sessions/{id}/disconnect', [AdminController::class, 'deleteSession']);
        Route::post('/sessions/{id}/set-system', [AdminController::class, 'setSystemSession']);
        Route::post('/sessions/{id}/send-message', [AdminController::class, 'sendMessageFromSession']);

        Route::get('/api-keys', [AdminController::class, 'apiKeys']);
        Route::get('/api-keys/{id}/meta-usage', [AdminController::class, 'apiKeyMetaUsage']);
        Route::delete('/api-keys/{id}', [AdminController::class, 'deleteApiKey']);

        Route::get('/api-monitoring', [AdminController::class, 'apiMonitoring']);

        Route::get('/messages', [AdminController::class, 'messages']);
        Route::get('/messages/{id}', [AdminController::class, 'showMessage']);
        Route::delete('/messages/{id}', [AdminController::class, 'deleteMessage']);
        Route::post('/messages/{id}/resend', [AdminController::class, 'resendMessage']);
        Route::get('/messages/export/csv', [AdminController::class, 'exportMessagesCSV']);
        Route::get('/messages/export/excel', [AdminController::class, 'exportMessagesExcel']);
        Route::get('/messages/export/pdf', [AdminController::class, 'exportMessagesPDF']);

        Route::get('/subscriptions', [AdminController::class, 'subscriptions']);
        Route::put('/subscriptions/{id}', [AdminController::class, 'updateSubscription']);
        Route::post('/subscriptions/{id}/extend', [AdminController::class, 'updateSubscription']);
        Route::post('/subscriptions/{id}/cancel', [AdminController::class, 'updateSubscription']);
        Route::post('/subscriptions/{id}/renew', [AdminController::class, 'updateSubscription']);

        Route::get('/webhooks', [AdminController::class, 'webhooks']);
        Route::get('/webhooks/{id}/logs', [AdminController::class, 'webhookLogs']);
        Route::delete('/webhooks/{id}', [AdminController::class, 'deleteWebhook']);

        Route::get('/payments/transactions', [AdminController::class, 'transactions']);
        Route::post('/payments/transactions/{id}/refund', [AdminController::class, 'refundTransaction']);

        Route::get('/queue', [AdminController::class, 'queueStatus']);
        Route::post('/queue/failed/{id}/retry', [AdminController::class, 'retryFailedJob']);
        Route::delete('/queue/failed/{id}', [AdminController::class, 'deleteFailedJob']);

        Route::get('/devices', [AdminController::class, 'devices']);
        Route::delete('/devices/{id}', [AdminController::class, 'deleteDevice']);

        Route::get('/security/login-history', [AdminController::class, 'loginHistory']);
        Route::get('/security/failed-logins', [AdminController::class, 'failedLogins']);
        Route::get('/security/fraud-alerts', [AdminController::class, 'fraudAlerts']);
        Route::post('/security/fraud-alerts/{id}/resolve', [AdminController::class, 'resolveFraudAlert']);

        Route::get('/live-events', [AdminController::class, 'realTimeEvents']);

        Route::get('/system-stats', [AdminController::class, 'systemStats']);
        Route::get('/analytics', [AdminController::class, 'analytics']);
        Route::get('/activity-logs', [AdminController::class, 'activityLogs']);
        Route::get('/audit-logs', [AdminController::class, 'auditLogs']);

        Route::get('/settings', [AdminController::class, 'systemSettings']);
        Route::post('/settings', [AdminController::class, 'updateSystemSetting']);

        Route::get('/notifications', [AdminController::class, 'notifications']);
        Route::post('/notifications', [AdminController::class, 'sendNotification']);
        Route::delete('/notifications/{id}', [AdminController::class, 'deleteNotification']);

        // Meta Ads Management
        Route::prefix('meta-ads')->group(function () {
            Route::get('/status', [MetaAdsController::class, 'status']);
            Route::post('/connect', [MetaAdsController::class, 'connect']);
            Route::post('/sync', [MetaAdsController::class, 'sync']);
            Route::get('/overview', [MetaAdsController::class, 'overview']);
            Route::get('/campaigns', [MetaAdsController::class, 'campaigns']);
            Route::get('/campaigns/{id}', [MetaAdsController::class, 'showCampaign']);
            Route::post('/campaigns', [MetaAdsController::class, 'createCampaignWizard']);
            Route::post('/campaigns/{id}/publish', [MetaAdsController::class, 'publish']);
            Route::post('/campaigns/{id}/pause', [MetaAdsController::class, 'pause']);
            Route::post('/campaigns/{id}/activate', [MetaAdsController::class, 'activate']);
            Route::put('/campaigns/{id}/budget', [MetaAdsController::class, 'updateBudget']);
            Route::delete('/campaigns/{id}', [MetaAdsController::class, 'destroy']);
            Route::get('/campaigns/{id}/insights', [MetaAdsController::class, 'insights']);
            Route::post('/media/upload', [MetaAdsController::class, 'uploadMedia']);
        });

        // Telegram Management
        Route::prefix('telegram')->group(function () {
            Route::get('/sessions', [AdminController::class, 'telegramSessions']);
            Route::delete('/sessions/{id}', [AdminController::class, 'deleteTelegramSession']);
            Route::post('/sessions/{id}/disconnect', [AdminController::class, 'disconnectTelegramSession']);
            Route::get('/messages', [AdminController::class, 'telegramMessages']);
            Route::delete('/messages/{id}', [AdminController::class, 'deleteTelegramMessage']);
            Route::get('/campaigns', [AdminController::class, 'telegramCampaigns']);
            Route::delete('/campaigns/{id}', [AdminController::class, 'deleteTelegramCampaign']);
        });

        // Messenger Management
        Route::prefix('messenger')->group(function () {
            Route::get('/overview', [MessengerAdminController::class, 'overview']);
            Route::get('/pages', [MessengerAdminController::class, 'pages']);
            Route::get('/messages', [MessengerAdminController::class, 'messages']);
            Route::get('/campaigns', [MessengerAdminController::class, 'campaigns']);
        });
    });

    // Public API routes protected by API key
    Route::middleware([VerifyApiKey::class, 'throttle:api-key'])->prefix('public')->group(function () {
        // Public Telegram API routes
        Route::prefix('telegram')->group(function () {
            Route::get('/sessions', [PublicTelegramController::class, 'sessions']);
            Route::post('/send-message', [PublicTelegramController::class, 'sendMessage']);
        });

        // Public Messenger API routes
        Route::prefix('messenger')->group(function () {
            Route::post('/send-message', [\App\Http\Controllers\PublicApi\PublicMessengerController::class, 'sendMessage']);
        });

        // Public Meta WhatsApp (official Cloud API) routes
        Route::prefix('meta')->group(function () {
            Route::get('/numbers', [\App\Http\Controllers\PublicApi\PublicMetaWhatsAppController::class, 'numbers']);
            Route::get('/templates', [\App\Http\Controllers\PublicApi\PublicMetaWhatsAppController::class, 'templates']);
            Route::post('/send-message', [\App\Http\Controllers\PublicApi\PublicMetaWhatsAppController::class, 'sendMessage']);
            Route::post('/send-template', [\App\Http\Controllers\PublicApi\PublicMetaWhatsAppController::class, 'sendTemplate']);
            Route::post('/send-bulk', [\App\Http\Controllers\PublicApi\PublicMetaWhatsAppController::class, 'sendBulk']);
            Route::get('/campaigns/{id}', [\App\Http\Controllers\PublicApi\PublicMetaWhatsAppController::class, 'campaignStatus']);
            Route::get('/messages/{id}', [\App\Http\Controllers\PublicApi\PublicMetaWhatsAppController::class, 'messageStatus']);
        });

        // Public n8n API routes
        Route::prefix('n8n')->group(function () {
            Route::get('/status', [N8nApiController::class, 'account']);
            Route::get('/sessions', [N8nApiController::class, 'sessions']);
            Route::post('/send-message', [N8nApiController::class, 'sendMessage']);
            Route::post('/send-otp', [N8nApiController::class, 'sendOtp']);
            Route::post('/send-image', [N8nApiController::class, 'sendImage']);
            Route::post('/send-file', [N8nApiController::class, 'sendFile']);
            Route::post('/message-status/{id}', [N8nApiController::class, 'messageStatus']);
            Route::get('/session-status', [N8nApiController::class, 'sessionStatus']);
            Route::get('/balance', [N8nApiController::class, 'balance']);
            Route::get('/account', [N8nApiController::class, 'account']);
        });

        Route::get('/status', function (Request $request) {
            $user = $request->attributes->get('api_key_user');
            $apiKey = $request->attributes->get('api_key');
            return response()->json([
                'success' => true,
                'message' => 'API key is valid',
                'data' => [
                    'user' => $user ? [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ] : null,
                    'key_name' => $apiKey?->name,
                    'requests_count' => $apiKey?->requests_count,
                    'last_used_at' => $apiKey?->last_used_at,
                ],
            ]);
        });

        // Test endpoint for WhatsApp connection status
        Route::get('/whatsapp/status', function (Request $request) {
            $user = $request->attributes->get('api_key_user');
            $session = \App\Models\WhatsAppSession::where('user_id', $user->id)->first();
            return response()->json([
                'success' => true,
                'data' => [
                    'whatsapp_status' => $session?->status ?? 'disconnected',
                    'phone_number' => $session?->phone_number,
                    'connected_at' => $session?->connected_at,
                ],
            ]);
        });

        // Public WhatsApp messaging endpoints (for external integrations)
        Route::prefix('whatsapp')->group(function () {
            Route::post('/send-message', [PublicWhatsAppController::class, 'sendMessage']);
            Route::post('/send-image', [PublicWhatsAppController::class, 'sendImage']);
            Route::post('/send-file', [PublicWhatsAppController::class, 'sendFile']);
            Route::post('/send-video', [PublicWhatsAppController::class, 'sendVideo']);
            Route::post('/send-voice', [PublicWhatsAppController::class, 'sendVoice']);
            Route::post('/send-contact', [PublicWhatsAppController::class, 'sendContact']);
            Route::post('/send-location', [PublicWhatsAppController::class, 'sendLocation']);
            Route::post('/send-otp', [PublicWhatsAppController::class, 'sendOtp']);
            Route::post('/verify-otp', [PublicWhatsAppController::class, 'verifyOtp']);
            Route::post('/send-template', [PublicWhatsAppController::class, 'sendTemplateMessage']);
            Route::get('/message-status/{id}', [PublicWhatsAppController::class, 'messageStatus']);
            Route::get('/messages/{id}', [PublicWhatsAppController::class, 'messageStatus']);
            Route::get('/queue-status', [PublicWhatsAppController::class, 'queueStatus']);

            // Remote WhatsApp pairing for external integrations
            Route::post('/pairing-sessions', [PublicWhatsAppController::class, 'createPairingSession']);
            Route::get('/pairing-sessions/{id}', [PublicWhatsAppController::class, 'pairingStatus']);
            Route::delete('/pairing-sessions/{id}', [PublicWhatsAppController::class, 'deletePairingSession']);
        });
    });
});


