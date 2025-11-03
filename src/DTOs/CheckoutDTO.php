<?php declare(strict_types=1);

namespace Sayed\Payment\DTOs;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CheckoutDTO
{
    public function __construct(
        public readonly string $successUrl,
        public readonly string $mode,
        public readonly string $currency = 'usd',
        public readonly ?int $amount = null,
        public readonly ?array $products = null,
        public readonly int $quantity = 1,
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


            'product' => ['nullable', 'array'],
            'product.title' => ['nullable', 'string'],

            'amount' => ['nullable', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'success_url' => ['required', 'url'],
            'cancel_url' => ['nullable', 'url'],
            'metadata' => ['nullable', 'array'],
            'mode' => ['required', 'string', 'in:payment,subscription'],
            'interval' => ['nullable', 'string', 'in:day,week,month,year'],

            'is_allow_promotion_code' => ['nullable', 'boolean'], 



            
            
        ]);

        $validated = $validator->validate();


        return new self(
            successUrl: $validated['success_url'],
            mode: $validated['mode'],
            currency: strtolower($validated['currency'] ?? 'usd'),
            amount: $validated['amount'] ?? null,
            products: $validated['product'] ?? null,
            quantity: $validated['quantity'] ?? 1,
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
            'quantity' => $this->quantity,
            'is_allow_promotion_code' => $this->isAllowPromotionCode,
            'metadata' => $this->metadata,
            'mode' => $this->mode,
            'interval' => $this->interval,
            'success_url' => $this->successUrl,
            'cancel_url' => $this->cancelUrl,
        ];
    }
}
