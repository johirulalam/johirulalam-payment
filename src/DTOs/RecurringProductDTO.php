<?php

namespace Sayed\Payment\DTOs;

class RecurringProductDTO
{
    public string $productId;
    public ?string $priceId;
    public ?string $planId;
    public string $name;
    public int $amount;
    public string $currency;
    public string $type;
    public string $interval;
    public int $intervalCount;
    public ?string $description;
    public ?int $trialDays;
    public array $metadata;

    public function __construct(
        string $productId,
        string $name,
        int $amount,
        string $currency,
        string $interval,
        int $intervalCount = 1,
        string $type = 'recurring',
        ?string $priceId = null,
        ?string $planId = null,
        ?string $description = null,
        ?int $trialDays = null,
        array $metadata = []
    ) {
        $this->productId = $productId;
        $this->priceId = $priceId;
        $this->planId = $planId;
        $this->name = $name;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->type = $type;
        $this->interval = $interval;
        $this->intervalCount = $intervalCount;
        $this->description = $description;
        $this->trialDays = $trialDays;
        $this->metadata = $metadata;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            productId: $data['product_id'] ?? $data['plan_id'],
            name: $data['name'],
            amount: $data['amount'],
            currency: $data['currency'],
            interval: $data['interval'] ?? 'month',
            intervalCount: $data['interval_count'] ?? 1,
            type: $data['type'] ?? 'recurring',
            priceId: $data['price_id'] ?? null,
            planId: $data['plan_id'] ?? null,
            description: $data['description'] ?? null,
            trialDays: $data['trial_days'] ?? null,
            metadata: $data['metadata'] ?? []
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'product_id' => $this->productId,
            'price_id' => $this->priceId,
            'plan_id' => $this->planId,
            'name' => $this->name,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'type' => $this->type,
            'interval' => $this->interval,
            'interval_count' => $this->intervalCount,
            'description' => $this->description,
            'trial_days' => $this->trialDays,
            'metadata' => $this->metadata,
        ], fn($value) => $value !== null);
    }
}
