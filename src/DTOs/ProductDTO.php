<?php

namespace Sayed\Payment\DTOs;

class ProductDTO
{
    public string $productId;
    public ?string $priceId;
    public string $name;
    public int $amount;
    public string $currency;
    public string $type;
    public ?string $description;
    public array $metadata;

    public function __construct(
        string $productId,
        string $name,
        int $amount,
        string $currency,
        string $type = 'one_time',
        ?string $priceId = null,
        ?string $description = null,
        array $metadata = []
    ) {
        $this->productId = $productId;
        $this->priceId = $priceId;
        $this->name = $name;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->type = $type;
        $this->description = $description;
        $this->metadata = $metadata;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            productId: $data['product_id'],
            name: $data['name'],
            amount: $data['amount'],
            currency: $data['currency'],
            type: $data['type'] ?? 'one_time',
            priceId: $data['price_id'] ?? null,
            description: $data['description'] ?? null,
            metadata: $data['metadata'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'price_id' => $this->priceId,
            'name' => $this->name,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'type' => $this->type,
            'description' => $this->description,
            'metadata' => $this->metadata,
        ];
    }
}
