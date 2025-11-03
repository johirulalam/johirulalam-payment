<?php

namespace Sayed\Payment\Events;

abstract class SubscriptionEvent
{
    public function __construct(
        public readonly string $provider,
        public readonly string $subscriptionId,
        public readonly string $status,
        public readonly ?int $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $customerId = null,
        public readonly ?string $customerEmail = null,
        public readonly ?string $planId = null,
        public readonly ?string $currentPeriodStart = null,
        public readonly ?string $currentPeriodEnd = null,
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
