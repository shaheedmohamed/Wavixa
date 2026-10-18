<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp' => [
        'url' => env('WHATSAPP_SERVICE_URL', 'http://127.0.0.1:3002'),
        'api_key' => env('WHATSAPP_SERVICE_API_KEY'),
        'admin_number' => env('ADMIN_WHATSAPP_NUMBER'),
    ],

    'telegram' => [
        'url' => env('TELEGRAM_SERVICE_URL', 'http://127.0.0.1:3003'),
        'api_key' => env('TELEGRAM_SERVICE_API_KEY', 'wavixa-telegram-service-secret-key-2026'),
    ],

    'openrouter' => [
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'api_key' => env('OPENROUTER_API_KEY'),
        'default_model' => env('OPENROUTER_DEFAULT_MODEL', 'google/gemma-4-31b-it:free'),
        'timeout' => (int) env('OPENROUTER_TIMEOUT', 60),
        'max_retries' => (int) env('OPENROUTER_MAX_RETRIES', 2),
    ],

    'gemini' => [
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'api_key' => env('GEMINI_API_KEY'),
        'default_model' => env('GEMINI_DEFAULT_MODEL', 'gemini-2.5-flash'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 60),
        'max_retries' => (int) env('GEMINI_MAX_RETRIES', 2),
    ],

    'meta_pixel' => [
        'pixel_id' => env('META_PIXEL_ID'),
        'access_token' => env('META_PIXEL_ACCESS_TOKEN'),
        'enabled' => env('META_PIXEL_ENABLED', true),
    ],

    'meta_marketing' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'access_token' => env('META_ACCESS_TOKEN'),
        'ad_account_id' => env('META_AD_ACCOUNT_ID'),
        'page_id' => env('META_PAGE_ID'),
        'pixel_id' => env('META_PIXEL_ID'),
        'api_version' => env('META_API_VERSION', 'v21.0'),
        'graph_url' => env('META_GRAPH_URL', 'https://graph.facebook.com'),
    ],

    'agora' => [
        'app_id' => env('AGORA_APP_ID'),
        'app_certificate' => env('AGORA_APP_CERTIFICATE'),
        'token_expiration' => (int) env('AGORA_TOKEN_EXPIRATION', 3600),
        'llm_api_key' => env('AGORA_LLM_API_KEY'),
    ],

    'meta_whatsapp' => [
        'app_id' => env('META_WHATSAPP_APP_ID', env('META_APP_ID')),
        'app_secret' => env('META_WHATSAPP_APP_SECRET', env('META_APP_SECRET')),
        'api_version' => env('META_WHATSAPP_API_VERSION', env('META_API_VERSION', 'v21.0')),
        'graph_url' => env('META_WHATSAPP_GRAPH_URL', env('META_GRAPH_URL', 'https://graph.facebook.com')),
        'webhook_verify_token' => env('META_WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        'redirect_uri' => env('META_WHATSAPP_REDIRECT_URI', env('APP_URL') . '/api/whatsapp/cloud/callback'),
        'enabled' => env('META_WHATSAPP_ENABLED', true),
    ],

    'messenger' => [
        'app_id' => env('MESSENGER_APP_ID', env('META_APP_ID')),
        'app_secret' => env('MESSENGER_APP_SECRET', env('META_APP_SECRET')),
        'api_version' => env('MESSENGER_API_VERSION', env('META_API_VERSION', 'v21.0')),
        'graph_url' => env('MESSENGER_GRAPH_URL', env('META_GRAPH_URL', 'https://graph.facebook.com')),
        'webhook_verify_token' => env('MESSENGER_WEBHOOK_VERIFY_TOKEN'),
        'webhook_path' => env('MESSENGER_WEBHOOK_PATH', '/api/messenger/webhook'),
        'enabled' => env('MESSENGER_ENABLED', true),
    ],

];
