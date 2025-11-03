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

    /*
    |--------------------------------------------------------------------------
    | Event Listeners
    |--------------------------------------------------------------------------
    |
    | Map webhook events to your custom event classes. Create your own event
    | classes by extending the base event classes (InvoiceEvent, CheckoutEvent, 
    | SubscriptionEvent) and register them here.
    |
    | Example:
    | 'invoice' => [
    |     'payment_succeeded' => \App\Events\InvoicePaymentSucceeded::class,
    |     'payment_failed' => \App\Events\InvoicePaymentFailed::class,
    | ],
    |
    */
    'events' => [
        'invoice' => [
            // 'payment_succeeded' => \App\Events\InvoicePaymentSucceeded::class,
            // 'payment_failed' => \App\Events\InvoicePaymentFailed::class,
            // 'finalized' => \App\Events\InvoiceFinalized::class,
            // 'updated' => \App\Events\InvoiceUpdated::class,
        ],
        'checkout' => [
            // 'completed' => \App\Events\CheckoutCompleted::class,
            // 'expired' => \App\Events\CheckoutExpired::class,
        ],
        'subscription' => [
            // 'created' => \App\Events\SubscriptionCreated::class,
            // 'updated' => \App\Events\SubscriptionUpdated::class,
            // 'deleted' => \App\Events\SubscriptionDeleted::class,
            // 'trial_ending' => \App\Events\SubscriptionTrialEnding::class,
        ],
    ],
];
