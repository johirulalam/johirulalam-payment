<?php declare(strict_types=1);

namespace Sayed\Payment\DTOs\Webhooks;

class SubscriptionEventDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $provider,
        public readonly string $eventType,
        public readonly string $status,
        public readonly ?string $customerId = null,
        public readonly ?string $customerEmail = null,
        public readonly ?string $subscriptionId = null,
        public readonly ?string $planId = null,
        public readonly ?float $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $interval = null,
        public readonly ?int $currentPeriodStart = null,
        public readonly ?int $currentPeriodEnd = null,
        public readonly ?int $canceledAt = null,
        public readonly ?int $trialStart = null,
        public readonly ?int $trialEnd = null,
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
            'customer_id' => $this->customerId,
            'customer_email' => $this->customerEmail,
            'subscription_id' => $this->subscriptionId,
            'plan_id' => $this->planId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'interval' => $this->interval,
            'current_period_start' => $this->currentPeriodStart,
            'current_period_end' => $this->currentPeriodEnd,
            'canceled_at' => $this->canceledAt,
            'trial_start' => $this->trialStart,
            'trial_end' => $this->trialEnd,
            'metadata' => $this->metadata,
            'timestamp' => $this->timestamp,
            'raw' => $this->raw,
        ];
    }
}
