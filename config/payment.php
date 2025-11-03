<?php

return [
    'default' => env('PAYMENT_PROVIDER', 'stripe'),

    'providers' => [
        'stripe' => [
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],

        'paypal' => [
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET'),
            'mode' => env('PAYPAL_MODE', 'sandbox'),
            'webhook_secret' => env('PAYPAL_WEBHOOK_SECRET'),
        ],

        'paddle' => [
            'vendor_id' => env('PADDLE_VENDOR_ID'),
            'vendor_auth_code' => env('PADDLE_VENDOR_AUTH_CODE'),
            'public_key' => env('PADDLE_PUBLIC_KEY'),
            'environment' => env('PADDLE_ENVIRONMENT', 'sandbox'),
            'webhook_secret' => env('PADDLE_WEBHOOK_SECRET'),
        ],
    ],
];
