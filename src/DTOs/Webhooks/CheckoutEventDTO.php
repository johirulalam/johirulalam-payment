<?php declare(strict_types=1);

namespace Sayed\Payment\DTOs\Webhooks;

class CheckoutEventDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $provider,
        public readonly string $eventType,
        public readonly string $status,
        public readonly float $amount,
        public readonly string $currency,
        public readonly ?string $customerId = null,
        public readonly ?string $customerEmail = null,
        public readonly ?string $paymentMethod = null,
        public readonly ?array $metadata = [],
        public readonly ?int $timestamp = null,
        public readonly ?array $raw = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'event_type' => $this->eventType,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'customer_id' => $this->customerId,
            'customer_email' => $this->customerEmail,
            'payment_method' => $this->paymentMethod,
            'metadata' => $this->metadata,
            'timestamp' => $this->timestamp,
            'raw' => $this->raw,
        ];
    }
}
