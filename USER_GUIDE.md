# User Guide - How to Use This Package in Your Laravel Application

This guide provides step-by-step instructions for integrating the Sayed Payment Laravel package into your application.

## Table of Contents

1. [Installation](#installation)
2. [Configuration](#configuration)
3. [Basic Setup](#basic-setup)
4. [Creating Payments](#creating-payments)
5. [Handling Webhooks](#handling-webhooks)
6. [Advanced Features](#advanced-features)

---

## Installation

### Step 1: Install the Package

Install via Composer:

```bash
composer require sayed/payment-laravel
```

### Step 2: Publish Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=payment-config
```

This creates `config/payment.php` in your application.

---

## Configuration

### Step 3: Add Environment Variables

Add your payment provider credentials to `.env`:

```env
# Choose your default provider
PAYMENT_PROVIDER=stripe

# Stripe Configuration
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=

# PayPal Configuration
PAYPAL_CLIENT_ID=
PAYPAL_CLIENT_SECRET=
PAYPAL_MODE=sandbox

# Paddle Configuration
PADDLE_VENDOR_ID=
PADDLE_VENDOR_AUTH_CODE=
PADDLE_PUBLIC_KEY=
PADDLE_ENVIRONMENT=
```

### Step 4: Verify Configuration

Check `config/payment.php` to ensure providers are configured:

---

## Basic Setup

### Step 5: Create Payment Controller

Create a controller to handle payments:

```bash
php artisan make:controller PaymentController
```

**app/Http/Controllers/PaymentController.php:**

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Sayed\Payment\Facades\Payment;
use Exception;

class PaymentController extends Controller
{
    /**
     * Create payment checkout session
     */
    public function createCheckout(Request $request)
    {
        try {
            $result = Payment::driver('stripe')->checkout([
                'currency' => 'usd',
                'amount' => 2000, // $20.00 in cents
                'products' => [
                    [
                        'title' => 'Premium Plan',
                        'amount' => 2000,
                        'quantity' => 1,
                    ]
                ],
                'is_subscription' => false,
                'success_url' => route('payment.success'),
                'cancel_url' => route('payment.cancel'),
                'metadata' => [
                    'user_id' => auth()->id(),
                    'order_id' => 'ORD-' . time(),
                ],
            ]);

            return redirect($result['paymentLinkUrl']);
        } catch (Exception $e) {
            return back()->with('error', 'Payment failed: ' . $e->getMessage());
        }
    }

}
```



### One-Time Payment

```php
use Sayed\Payment\Facades\Payment;

$result = Payment::driver('stripe')->checkout([
    'products' => [
        [
            'title' => 'Product Name',
            'amount' => 5000,
            'quantity' => 1,
        ]
    ],
    'is_subscription' => false,
    'success_url' => route('payment.success'),
    'cancel_url' => route('payment.cancel'),
]);

// Redirect user to payment page
return redirect($result['paymentLinkUrl']);
```

### Subscription Payment

```php
$result = Payment::driver('stripe')->checkout([
    'currency' => 'usd',
    'amount' => 2999, // $29.99
    'products' => [
        [
            'title' => 'Monthly Subscription',
            'amount' => 2999,
            'quantity' => 1,
        ]
    ],
    'is_subscription' => true,
    'interval' => 'month', // day, week, month, year
    'success_url' => route('subscription.success'),
    'cancel_url' => route('subscription.cancel'),
]);
```

### Multiple Products

```php
$result = Payment::driver('stripe')->checkout([
    'currency' => 'usd',
    'products' => [
        [
            'title' => 'Product 1',
            'amount' => 1000,
            'quantity' => 2,
        ],
        [
            'title' => 'Product 2',
            'amount' => 1500,
            'quantity' => 1,
        ],
    ],
    'is_subscription' => false,
    'success_url' => route('payment.success'),
    'cancel_url' => route('payment.cancel'),
]);
```

### Invoice Payment (Stripe Only)

```php
// Step 3: Create and pay invoice
$invoice = Payment::driver('stripe')->payWithInvoice([
    'customer_id' => $customer['customer_id'],
    'payment_method_id' => $request->payment_method_id,
    'currency' => 'usd',
    'items' => [
        [
            'description' => 'Service Fee',
            'amount' => 5000,
            'quantity' => 1,
        ],
    ],
]);

return response()->json([
    'success' => true,
    'invoice_pdf' => $invoice['invoice_pdf'],
]);
```

### Refund Payment

```php
$refund = Payment::driver('stripe')->refundPayment(
    transactionId: 'ch_xxxxx',
    amount: 1000 // $10.00
);

```

---

## Handling Webhooks

### Step 8: Create Webhook Controller

```bash
php artisan make:controller WebhookController
```

**app/Http/Controllers/WebhookController.php:**

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Sayed\Payment\Facades\Webhook;
use App\Models\Order;
use App\Models\Subscription;
use Exception;

class WebhookController extends Controller
{
    /**
     * Handle incoming webhooks
     */
    public function handle(Request $request, string $provider)
    {
        $payload = $request->getContent();
        $headers = collect($request->headers->all())
            ->map(fn($v) => is_array($v) ? $v[0] : $v)
            ->toArray();

        try {
            $event = Webhook::process($provider, $payload, $headers);
            
            // Log webhook
            \Log::info('Webhook received', [
                'provider' => $provider,
                'event_type' => $event['event_type'],
            ]);

            // Handle different event types
            $this->handleEvent($event);

            return response()->json(['received' => true], 200);
        } catch (Exception $e) {
            \Log::error('Webhook error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Handle specific event types
     */
    protected function handleEvent(array $event)
    {
        $eventType = $event['event_type'];

        // Checkout/Payment completed
        if (str_contains($eventType, 'checkout') || str_contains($eventType, 'payment')) {
            $this->handlePaymentCompleted($event);
        }

        // Subscription events
        if (str_contains($eventType, 'subscription')) {
            $this->handleSubscriptionEvent($event);
        }

        // Invoice events
        if (str_contains($eventType, 'invoice')) {
            $this->handleInvoiceEvent($event);
        }
    }

    /**
     * Handle payment completed
     */
    protected function handlePaymentCompleted(array $event)
    {
        // Find order by metadata
        $metadata = $event['metadata'] ?? [];
        
        if (isset($metadata['order_id'])) {
            $order = Order::where('order_id', $metadata['order_id'])->first();
            
            if ($order) {
                $order->update([
                    'status' => 'paid',
                    'payment_id' => $event['id'],
                    'amount_paid' => $event['amount'],
                    'paid_at' => now(),
                ]);

                // Send confirmation email
                // Mail::to($order->user)->send(new PaymentConfirmation($order));
            }
        }
    }

    /**
     * Handle subscription events
     */
    protected function handleSubscriptionEvent(array $event)
    {
        $status = $event['status'];
        $subscriptionId = $event['subscription_id'];

        $subscription = Subscription::where('provider_subscription_id', $subscriptionId)
            ->first();

        if ($subscription) {
            $subscription->update([
                'status' => $status,
                'current_period_end' => $event['current_period_end'] ?? null,
            ]);

            // Handle cancellation
            if ($status === 'canceled' || $status === 'cancelled') {
                // Notify user
                // Disable features
            }
        }
    }

    /**
     * Handle invoice events
     */
    protected function handleInvoiceEvent(array $event)
    {
        if ($event['paid']) {
            // Invoice was paid successfully
            \Log::info('Invoice paid', [
                'invoice_id' => $event['id'],
                'amount' => $event['amount_paid'],
            ]);
        } else {
            // Payment failed
            \Log::warning('Invoice payment failed', [
                'invoice_id' => $event['id'],
            ]);
        }
    }
}
```

### Step 9: Add Webhook Routes

Add to `routes/api.php`:

```php
use App\Http\Controllers\WebhookController;

Route::post('/webhooks/payment/{provider}', [WebhookController::class, 'handle'])
    ->name('webhooks.payment');
```

### Step 10: Disable CSRF for Webhooks

The package already includes middleware, but verify in `app/Http/Middleware/VerifyCsrfToken.php`:

```php
protected $except = [
    'webhooks/*',
];
```

Or the package middleware is automatically applied via the service provider.

### Step 11: Register Webhook URLs

Register webhook URLs with your payment providers:

**Stripe:**
- URL: `https://yourdomain.com/api/webhooks/payment/stripe`
- Events: `checkout.session.completed`, `customer.subscription.*`, `invoice.*`

**PayPal:**
- URL: `https://yourdomain.com/api/webhooks/payment/paypal`
- Events: `PAYMENT.CAPTURE.COMPLETED`, `BILLING.SUBSCRIPTION.*`

**Paddle:**
- URL: `https://yourdomain.com/api/webhooks/payment/paddle`
- Events: `transaction.completed`, `subscription.*`

---

## Advanced Features

### Dynamic Provider Selection

```php
// Use default provider from config
$payment = Payment::driver();

// Use specific provider
$stripePayment = Payment::driver('stripe');
$paypalPayment = Payment::driver('paypal');
$paddlePayment = Payment::driver('paddle');
```

### Custom Metadata

```php
Payment::driver('stripe')->checkout([
    // ... other fields
    'metadata' => [
        'user_id' => auth()->id(),
        'order_id' => $order->id,
        'plan' => 'premium',
        'source' => 'web',
    ],
]);
```

### Get Invoice Details (Stripe)

```php
$invoice = Payment::driver('stripe')->getInvoice('in_xxxxx');

return view('invoice', [
    'invoice_pdf' => $invoice['invoice_pdf'],
    'amount' => $invoice['amount_paid'],
    'status' => $invoice['status'],
]);
```

