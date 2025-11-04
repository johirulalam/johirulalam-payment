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

## Advanced Features

### Dynamic Provider Selection

```php
use Sayed\Payment\Facades\Payment;
use Sayed\Payment\Enums\PaymentMethod;

// Use default provider from config
$payment = Payment::driver();

// Use specific provider with string
$stripePayment = Payment::driver('stripe');
$paypalPayment = Payment::driver('paypal');
$paddlePayment = Payment::driver('paddle');

// Use specific provider with enum (type-safe)
$stripePayment = Payment::driver(PaymentMethod::STRIPE);
$paypalPayment = Payment::driver(PaymentMethod::PAYPAL);
$paddlePayment = Payment::driver(PaymentMethod::PADDLE);

// Dynamic selection with enum
$provider = PaymentMethod::from($request->payment_method);
$payment = Payment::driver($provider);
```

### Using PaymentMethod Enum

```php
use Sayed\Payment\Enums\PaymentMethod;

// Get all available payment methods
$methods = PaymentMethod::values(); // ['stripe', 'paypal', 'paddle']

// Display names
foreach (PaymentMethod::cases() as $method) {
    echo $method->displayName(); // Stripe, PayPal, Paddle
}

// Type-safe payment creation
$result = Payment::driver(PaymentMethod::STRIPE)->checkout([
    'currency' => 'usd',
    'products' => [
        ['title' => 'Product', 'amount' => 2999, 'quantity' => 1]
    ],
    'is_subscription' => false,
    'success_url' => route('payment.success'),
]);
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

---

## Event-Driven Architecture

The package provides an event-driven architecture that allows you to easily handle payment events by creating custom event classes.

### How It Works

When a webhook is received:
1. The package validates and transforms the webhook data
2. It dispatches events based on the event type (invoice, checkout, subscription)
3. Your custom event classes are automatically instantiated and their `handle()` method is called
4. You can also listen to these events using Laravel's event listener system

### Step 1: Create Your Event Classes

Create event classes in `app/Events` by extending the base event classes provided by the package:

#### Invoice Events

```php
<?php

namespace App\Events;

use Sayed\Payment\Events\InvoiceEvent;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;

class InvoicePaymentSucceeded extends InvoiceEvent
{
    public function getEventName(): string
    {
        return 'invoice.payment_succeeded';
    }

    public function handle(): void
    {
        Log::info('Invoice paid', ['invoice_id' => $this->invoiceId]);

        // Update your database
        Invoice::where('payment_invoice_id', $this->invoiceId)->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Your business logic here
        // - Grant access to product
        // - Send confirmation email
        // - Trigger fulfillment process
    }
}
```

#### Checkout Events

```php
<?php

namespace App\Events;

use Sayed\Payment\Events\CheckoutEvent;
use App\Models\Order;

class CheckoutCompleted extends CheckoutEvent
{
    public function getEventName(): string
    {
        return 'checkout.completed';
    }

    public function handle(): void
    {
        // Get order from metadata
        $orderId = $this->metadata['order_id'] ?? null;
        
        if ($orderId) {
            Order::find($orderId)->update([
                'status' => 'paid',
                'transaction_id' => $this->transactionId,
                'paid_at' => now(),
            ]);
        }
    }
}
```

#### Subscription Events

```php
<?php

namespace App\Events;

use Sayed\Payment\Events\SubscriptionEvent;
use App\Models\Subscription;

class SubscriptionCreated extends SubscriptionEvent
{
    public function getEventName(): string
    {
        return 'subscription.created';
    }

    public function handle(): void
    {
        Subscription::create([
            'payment_subscription_id' => $this->subscriptionId,
            'customer_id' => $this->customerId,
            'plan_id' => $this->planId,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
        ]);
    }
}
```

### Step 2: Register Events in Config

Update `config/payment.php` to map webhook events to your custom classes:

```php
return [
    // ... other config

    'events' => [
        'invoice' => [
            'payment_succeeded' => \App\Events\InvoicePaymentSucceeded::class,
            'payment_failed' => \App\Events\InvoicePaymentFailed::class,
            'finalized' => \App\Events\InvoiceFinalized::class,
            'updated' => \App\Events\InvoiceUpdated::class,
        ],
        'checkout' => [
            'completed' => \App\Events\CheckoutCompleted::class,
            'expired' => \App\Events\CheckoutExpired::class,
        ],
        'subscription' => [
            'created' => \App\Events\SubscriptionCreated::class,
            'updated' => \App\Events\SubscriptionUpdated::class,
            'deleted' => \App\Events\SubscriptionDeleted::class,
            'trial_ending' => \App\Events\SubscriptionTrialEnding::class,
        ],
    ],
];
```

### Step 3: Access Event Data

Your event classes have access to the following properties:

#### InvoiceEvent Properties

```php
$this->provider;          // 'stripe', 'paypal', or 'paddle'
$this->invoiceId;         // Provider's invoice ID
$this->status;            // Invoice status
$this->amount;            // Amount in cents (integer)
$this->currency;          // Currency code (e.g., 'usd')
$this->customerId;        // Customer ID (if available)
$this->subscriptionId;    // Subscription ID (if applicable)
$this->metadata;          // Custom metadata array
$this->rawPayload;        // Original webhook payload (JSON string)
```

#### CheckoutEvent Properties

```php
$this->provider;          // Payment provider
$this->transactionId;     // Transaction/session ID
$this->status;            // Payment status
$this->amount;            // Amount in cents (integer)
$this->currency;          // Currency code
$this->customerId;        // Customer ID (if available)
$this->customerEmail;     // Customer email (if available)
$this->metadata;          // Custom metadata array
$this->rawPayload;        // Original webhook payload (JSON string)
```

#### SubscriptionEvent Properties

```php
$this->provider;              // Payment provider
$this->subscriptionId;        // Subscription ID
$this->status;                // Subscription status
$this->amount;                // Amount in cents (integer, nullable)
$this->currency;              // Currency code (nullable)
$this->customerId;            // Customer ID (if available)
$this->customerEmail;         // Customer email (if available)
$this->planId;                // Plan/price ID (if available)
$this->currentPeriodStart;    // Period start date (nullable)
$this->currentPeriodEnd;      // Period end date (nullable)
$this->metadata;              // Custom metadata array
$this->rawPayload;            // Original webhook payload (JSON string)
```

### Step 4: Using Laravel's Event System (Optional)

You can also use Laravel's native event listeners:

**Create a Listener:**

```bash
php artisan make:listener SendInvoicePaidNotification
```

**Register in EventServiceProvider:**

```php
use App\Events\InvoicePaymentSucceeded;
use App\Listeners\SendInvoicePaidNotification;

protected $listen = [
    InvoicePaymentSucceeded::class => [
        SendInvoicePaidNotification::class,
    ],
];
```

**Listener Example:**

```php
<?php

namespace App\Listeners;

use App\Events\InvoicePaymentSucceeded;
use Illuminate\Support\Facades\Mail;

class SendInvoicePaidNotification
{
    public function handle(InvoicePaymentSucceeded $event)
    {
        // Send email notification
        Mail::to($event->customerEmail)->send(
            new InvoicePaidMail($event->invoiceId, $event->amount)
        );
    }
}
```

### Available Event Names

#### Stripe Event Mapping

| Stripe Event | Simplified Name | Event Type |
|-------------|-----------------|------------|
| `checkout.session.completed` | `completed` | checkout |
| `checkout.session.expired` | `expired` | checkout |
| `customer.subscription.created` | `created` | subscription |
| `customer.subscription.updated` | `updated` | subscription |
| `customer.subscription.deleted` | `deleted` | subscription |
| `customer.subscription.trial_will_end` | `trial_ending` | subscription |
| `invoice.created` | `created` | invoice |
| `invoice.finalized` | `finalized` | invoice |
| `invoice.paid` | `payment_succeeded` | invoice |
| `invoice.payment_failed` | `payment_failed` | invoice |
| `invoice.updated` | `updated` | invoice |

### Real-World Example

Here's a complete example of handling invoice payments:

```php
<?php

namespace App\Events;

use Sayed\Payment\Events\InvoiceEvent;
use App\Models\Invoice;
use App\Models\User;
use App\Jobs\SendInvoiceReceiptJob;
use App\Jobs\GrantProductAccessJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class InvoicePaymentSucceeded extends InvoiceEvent
{
    public function getEventName(): string
    {
        return 'invoice.payment_succeeded';
    }

    public function handle(): void
    {
        DB::transaction(function () {
            // 1. Update invoice in database
            $invoice = Invoice::where('payment_invoice_id', $this->invoiceId)
                ->lockForUpdate()
                ->first();

            if (!$invoice) {
                Log::error('Invoice not found', ['invoice_id' => $this->invoiceId]);
                return;
            }

            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_provider' => $this->provider,
                'payment_data' => $this->metadata,
            ]);

            // 2. Get user
            $user = $invoice->user;

            // 3. Grant access to product/service
            if ($invoice->product_id) {
                GrantProductAccessJob::dispatch($user, $invoice->product_id);
            }

            // 4. Send receipt email
            SendInvoiceReceiptJob::dispatch($user, $invoice);

            // 5. Log for analytics
            Log::info('Invoice payment processed successfully', [
                'invoice_id' => $this->invoiceId,
                'user_id' => $user->id,
                'amount' => $this->amount,
                'provider' => $this->provider,
            ]);
        });
    }
}
```

