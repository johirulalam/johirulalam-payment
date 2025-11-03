<?php

namespace Sayed\Payment\Services\Webhooks;

use Sayed\Payment\DTOs\Webhooks\CheckoutEventDTO;
use Sayed\Payment\DTOs\Webhooks\SubscriptionEventDTO;
use Sayed\Payment\DTOs\Webhooks\InvoiceEventDTO;

abstract class WebhookProcessor
{
    abstract public function process(string $payload, array $headers): array;
    abstract public function validate(string $payload, array $headers): array;
    abstract public function transform($payload, string $eventType): CheckoutEventDTO|SubscriptionEventDTO|InvoiceEventDTO;
}
