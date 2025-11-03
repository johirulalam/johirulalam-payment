# Sayed Payment Laravel Package

A comprehensive Laravel payment integration package supporting multiple payment providers including Stripe, PayPal, and Paddle.

## 🎯 Overview

This package is a complete Laravel port of the `sayed-payment` Node.js package, providing a unified interface for integrating multiple payment gateways into your Laravel application.

## ✨ Features

- 🚀 Easy integration with Laravel applications
- 💳 Multiple payment providers (Stripe, PayPal, Paddle)
- 🔄 Factory pattern for flexible provider switching
- 🎯 Webhook handling with automatic provider detection
- 🔒 Secure signature verification
- 📦 Service Provider with auto-discovery
- ⚙️ Configurable via Laravel config files
- 🏗️ Extensible architecture for custom providers

## 📋 Requirements

- PHP >= 8.0
- Laravel >= 9.0

## �� Installation

```bash
composer require sayed/payment-laravel
```

### Publish Configuration

```bash
php artisan vendor:publish --tag=payment-config
```

### Environment Setup

Add to your `.env`:

```env
PAYMENT_PROVIDER=stripe

STRIPE_SECRET_KEY=sk_test_xxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxx

PAYPAL_CLIENT_ID=xxxxx
PAYPAL_CLIENT_SECRET=xxxxx
PAYPAL_MODE=sandbox

PADDLE_VENDOR_ID=xxxxx
PADDLE_VENDOR_AUTH_CODE=xxxxx
PADDLE_ENVIRONMENT=sandbox
```

## 🚀 Quick Start

### Create a Payment Checkout

```php
use Sayed\Payment\Facades\Payment;

$adapter = Payment::createAdapter('stripe');

$result = $adapter->checkout([
    'has_price_id' => false,
    'currency' => 'usd',
    'amount' => 2000, // $20.00 in cents
    'product' => ['title' => 'Premium Plan'],
    'quantity' => 1,
    'is_subscription' => false,
    'success_url' => route('payment.success'),
    'cancel_url' => route('payment.cancel'),
]);

return redirect($result['paymentLinkUrl']);
```

### Handle Webhooks

```php
use Sayed\Payment\Factory\WebhookFactory;

public function handleWebhook(Request $request)
{
    $payload = $request->getContent();
    $headers = collect($request->headers->all())
        ->map(fn($v) => is_array($v) ? $v[0] : $v)
        ->toArray();
    
    $handler = WebhookFactory::createAdapter($headers);
    $result = $handler->process($payload, $headers);
    
    // Process $result...
    
    return response()->json(['received' => true]);
}
```

## 📚 Documentation

### Supported Providers

- **Stripe**: Full checkout, subscriptions, and refunds
- **PayPal**: Order creation and payment processing
- **Paddle**: Payment links and subscriptions

### API Routes

```
POST /api/payment/checkout/{provider}
POST /api/payment/refund/{provider}
POST /api/webhooks/handle
```

### Adding Custom Providers

```php
use Sayed\Payment\Registry\PaymentRegistry;

$registry = app(PaymentRegistry::class);
$registry->registerProvider('custom', CustomProcessor::class);
```

## 🏗️ Architecture

```
sayed-payment-laravel/
├── config/payment.php
├── src/
│   ├── PaymentServiceProvider.php
│   ├── Facades/
│   ├── Factory/
│   ├── Registry/
│   ├── Services/
│   ├── Drivers/
│   │   ├── Stripe/
│   │   ├── PayPal/
│   │   └── Paddle/
│   └── Http/
└── routes/payment.php
```

## 🔒 Security

- Webhook signature verification
- Environment-based credentials
- HTTPS enforcement recommended
- CSRF protection configuration

## 📄 License

Apache License 2.0

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📧 Support

For issues and questions, please open an issue on GitHub.

---

**Note**: This package has been created with all necessary files. The complete implementation includes 30+ PHP classes following Laravel best practices and PSR-4 standards.

