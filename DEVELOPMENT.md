# Development Guide

This guide explains how to extend the package by adding new payment providers.

## Adding a New Payment Provider

Follow these steps to add a new payment provider (e.g., Razorpay, Braintree, Square, etc.)

### Step 1: Create Provider Directory Structure

Create the necessary directories for your new provider:

```bash
src/Drivers/YourProvider/Payments
src/Drivers/YourProvider/Webhooks
```

Replace `YourProvider` with your payment provider name (e.g., `Razorpay`, `Square`, `Braintree`).

### Step 2: Create Payment Processor Class

Create `src/Drivers/YourProvider/Payments/YourProviderProcessor.php`:

```php
<?php

namespace Sayed\Payment\Drivers\YourProvider\Payments;

use Sayed\Payment\Services\Payments\PaymentProcessor;
use Sayed\Payment\DTOs\CheckoutDTO;
use Sayed\Payment\DTOs\RefundDTO;
use Exception;

class YourProviderProcessor extends PaymentProcessor
{
    /**
     * Create checkout session
     * 
     * @param array $payload
     * @return array
     * @throws Exception
     */
    public function checkout(array $payload): array
    {

    }

    /**
     * Refund payment
     * 
     * @param string $transactionId
     * @param float $amount
     * @return array
     * @throws Exception
     */
    public function refundPayment(string $transactionId, float $amount): array
    {
    }

}
```

### Step 3: Create Webhook Handler Class

Create `src/Drivers/YourProvider/Webhooks/YourProviderHandler.php`:

```php
<?php

namespace Sayed\Payment\Drivers\YourProvider\Webhooks;

use Exception;

class YourProviderHandler extends WebhookProcessor
{
    public function process(string $payload, array $headers): array
    {
    }

    public function validate(string $payload, array $headers): array
    {
    }

    public function transform($payload, string $eventType): CheckoutEventDTO|SubscriptionEventDTO|InvoiceEventDTO
    {
    }

    protected function transformCheckout(array $payload, string $eventType): CheckoutEventDTO
    {
    }

    protected function transformSubscription(array $payload, string $eventType): SubscriptionEventDTO
    {
    }

    protected function transformInvoice(array $payload, string $eventType): InvoiceEventDTO
    {
    }

    protected function verifySignature(string $payload, string $signature): bool
    {
    }
}
```

### Step 4: Register Provider in Configuration

Update `config/payment.php`:


### Step 5: Register in Payment Registry

Update `src/Registry/PaymentRegistry.php`:

```php
protected static array $drivers = [
    'yourprovider' => \Sayed\Payment\Drivers\YourProvider\Payments\YourProviderProcessor::class,
];
```

### Step 6: Register in Webhook Registry

Update `src/Registry/WebhookRegistry.php`:

```php
protected static array $handlers = [
    'yourprovider' => \Sayed\Payment\Drivers\YourProvider\Webhooks\YourProviderHandler::class,
];
```

### Step 7: Update Provider Identifier

Update `src/Services/Webhooks/ProviderIdentifier.php` to detect your provider's webhooks:

```php
public static function identify(array $headers): string
{
    // Add your provider detection
    if (isset($headers['x-yourprovider-signature'])) {
        return 'yourprovider';
    }

    throw new \Exception('Unable to identify payment provider from headers');
}
```

### Step 8: Add Environment Variables

Update `.env.example`:

```env
# Your Provider Configuration
YOURPROVIDER_API_KEY=
YOURPROVIDER_API_SECRET=
YOURPROVIDER_WEBHOOK_SECRET=
```


**Ready to add a new provider?** Start with Step 1! 🚀
