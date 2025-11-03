<?php declare(strict_types=1);

namespace Sayed\Payment\DTOs;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CheckoutDTO
{
    public function __construct(
        public readonly string $successUrl,
        public readonly bool $isSubscription = false,
        public readonly string $currency = 'usd',
        public readonly ?int $amount = null,
        public readonly ?array $products = null,
        public readonly bool $isAllowPromotionCode = false,
        public readonly array $metadata = [],
        public readonly string $interval = 'month',
        public readonly ?string $cancelUrl = null,
    ) {
    }

    /**
     * Create DTO from array with validation
     * @throws ValidationException
     */
    public static function fromArray(array $data): self
    {
        $validator = Validator::make($data, [
            'products' => ['nullable', 'array', 'min:1'],
            'products.*.id' => ['required_without:products.*.amount', 'string'],
            'products.*.title' => ['nullable', 'string'],
            'products.*.amount' => ['nullable', 'integer', 'min:1'],
            'products.*.quantity' => ['nullable', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
            'success_url' => ['required', 'url'],
            'cancel_url' => ['nullable', 'url'],
            'metadata' => ['nullable', 'array'],
            'is_subscription' => ['nullable', 'boolean'],
            'interval' => ['nullable', 'string', 'in:day,week,month,year'],
            'is_allow_promotion_code' => ['nullable', 'boolean'],
        ]);

        $validated = $validator->validate();

        return new self(
            successUrl: $validated['success_url'],
            isSubscription: $validated['is_subscription'] ?? false,
            currency: strtolower($validated['currency'] ?? 'usd'),
            amount: null,
            products: $validated['products'] ?? null,
            isAllowPromotionCode: $validated['is_allow_promotion_code'] ?? false,
            metadata: $validated['metadata'] ?? [],
            interval: $validated['interval'] ?? 'month',
            cancelUrl: $validated['cancel_url'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'amount' => $this->amount,
            'products' => $this->products,
            'is_allow_promotion_code' => $this->isAllowPromotionCode,
            'metadata' => $this->metadata,
            'is_subscription' => $this->isSubscription,
            'interval' => $this->interval,
            'success_url' => $this->successUrl,
            'cancel_url' => $this->cancelUrl,
        ];
    }
}
