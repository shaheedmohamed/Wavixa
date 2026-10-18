<?php

return [
    'mode' => env('PAYPAL_MODE', 'sandbox'),

    'sandbox_client_id' => env('PAYPAL_SANDBOX_CLIENT_ID', ''),
    'sandbox_client_secret' => env('PAYPAL_SANDBOX_CLIENT_SECRET', ''),

    'live_client_id' => env('PAYPAL_LIVE_CLIENT_ID', ''),
    'live_client_secret' => env('PAYPAL_LIVE_CLIENT_SECRET', ''),

    'currency' => env('PAYPAL_CURRENCY', 'USD'),

    'return_url' => env('PAYPAL_RETURN_URL', env('APP_URL') . '/subscription'),
    'cancel_url' => env('PAYPAL_CANCEL_URL', env('APP_URL') . '/subscription'),
];
