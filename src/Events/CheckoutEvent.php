<?php

namespace Sayed\Payment\Events;

abstract class CheckoutEvent
{
    public function __construct(
        public readonly string $provider,
        public readonly string $transactionId,
        public readonly string $status,
        public readonly int $amount,
        public readonly string $currency,
        public readonly ?string $customerId = null,
        public readonly ?string $customerEmail = null,
        public readonly ?array $metadata = null,
        public readonly ?string $rawPayload = null,
    ) {
    }

    /**
     * Get the event name for logging/tracking
     */
    abstract public function getEventName(): string;

    /**
     * Handle the event - users can override this method
     */
    public function handle(): void
    {
        // Default implementation - users can override
    }
}