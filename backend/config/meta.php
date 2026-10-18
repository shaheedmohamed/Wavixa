<?php

/**
 * Meta Official / Tech Provider Test integration configuration.
 *
 * This config is isolated from the existing Meta Marketing / Messenger setup.
 * It powers the experimental Meta WhatsApp Business Platform integration
 * under the "Meta Test" module and can be removed without affecting production.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Master feature flag
    |--------------------------------------------------------------------------
    |
    | When disabled, all Meta Test routes and console commands are unavailable.
    */
    'enabled' => (bool) env('META_OFFICIAL_TEST_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Meta Graph API settings
    |--------------------------------------------------------------------------
    */
    'app_id' => env('META_APP_ID'),
    'app_secret' => env('META_APP_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Convenience fallback for a single long-lived access token.
    | Used for health checks and CLI probes only, NOT for customer WABAs.
    |--------------------------------------------------------------------------
    */
    'access_token' => env('META_ACCESS_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Marketing / Ads identifiers. These are used by the existing Marketing
    | integration and are NOT required for WhatsApp Business Platform.
    | They are listed here only for reference / validation purposes.
    |--------------------------------------------------------------------------
    */
    'ad_account_id' => env('META_AD_ACCOUNT_ID'),
    'page_id' => env('META_PAGE_ID'),
    'pixel_id' => env('META_PIXEL_ID'),

    /*
    |--------------------------------------------------------------------------
    | API version and endpoint
    |--------------------------------------------------------------------------
    */
    'api_version' => env('META_API_VERSION', 'v21.0'),
    'graph_url' => rtrim(env('META_GRAPH_URL', 'https://graph.facebook.com'), '/'),

    /*
    |--------------------------------------------------------------------------
    | WhatsApp / Webhook settings
    |--------------------------------------------------------------------------
    */
    'webhook_verify_token' => env('META_TEST_WEBHOOK_VERIFY_TOKEN'),
    'webhook_path' => env('META_TEST_WEBHOOK_PATH', '/api/meta-test/webhook'),

    /*
    |--------------------------------------------------------------------------
    | OAuth / Embedded Signup
    |--------------------------------------------------------------------------
    */
    'redirect_uri' => env(
        'META_TEST_REDIRECT_URI',
        env('APP_URL', 'http://localhost:8000') . '/api/meta-test/callback'
    ),

    /*
    |--------------------------------------------------------------------------
    | Facebook Login for Business configuration ID
    |--------------------------------------------------------------------------
    |
    | When set, the OAuth dialog URL carries this config_id, which turns the
    | redirect flow into the full Embedded Signup experience (including WABA
    | creation for customers who don't have one yet).
    */
    'login_config_id' => env('META_LOGIN_CONFIG_ID'),

    /*
    |--------------------------------------------------------------------------
    | Embedded Signup launch options (JavaScript SDK flow)
    |--------------------------------------------------------------------------
    |
    | 'whatsapp_business_app_onboarding' enables the WhatsApp Business App
    | onboarding branch (a.k.a. Coexistence) inside Embedded Signup, letting
    | customers connect a number already registered on the WhatsApp Business
    | app. Set META_EMBEDDED_SIGNUP_FEATURE to an empty string to launch the
    | plain Cloud API flow instead.
    |
    | sessionInfoVersion '3' enables session logging — Meta posts
    | WA_EMBEDDED_SIGNUP message events to the spawning window. Required for
    | WhatsApp Business App onboarding.
    */
    'embedded_signup_feature' => env('META_EMBEDDED_SIGNUP_FEATURE', 'whatsapp_business_app_onboarding'),
    'session_info_version' => env('META_SESSION_INFO_VERSION', '3'),

    'scopes' => [
        'business_management',
        'whatsapp_business_management',
        'whatsapp_business_messaging',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limiting
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        'per_minute' => (int) env('META_TEST_RATE_LIMIT_PER_MINUTE', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security / UX
    |--------------------------------------------------------------------------
    */
    'mask_secrets' => (bool) env('META_TEST_MASK_SECRETS', true),

    /*
    |--------------------------------------------------------------------------
    | Estimated per-message pricing (USD)
    |--------------------------------------------------------------------------
    |
    | Meta webhooks report the pricing category + a billable flag per message
    | but never the charged amount. These rates are the default estimate for
    | Egyptian recipients — override per market via META_PRICE_* env vars.
    | "service" (free-form inside the 24h window) is always free.
    */
    'pricing_rates' => [
        'marketing' => (float) env('META_PRICE_MARKETING', 0.0529),
        'utility' => (float) env('META_PRICE_UTILITY', 0.0115),
        'authentication' => (float) env('META_PRICE_AUTHENTICATION', 0.0115),
        'authentication_international' => (float) env('META_PRICE_AUTHENTICATION_INTL', 0.0242),
        'service' => 0.0,
    ],
];
