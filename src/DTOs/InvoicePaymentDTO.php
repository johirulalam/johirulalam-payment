<?php declare(strict_types=1);

namespace Sayed\Payment\DTOs;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InvoicePaymentDTO
{
    public function __construct(
        public readonly string $customerId,
        public readonly string $paymentMethodId,
        public readonly array $items,
        public readonly string $currency,
        public readonly ?string $description = null,
        public readonly ?int $daysUntilDue = null,
        public readonly bool $autoAdvance = true,
        public readonly array $metadata = [],
    ) {
    }

    /**
     * Create DTO from array with validation
     * @throws ValidationException
     */
    public static function fromArray(array $data): self
    {
        $validator = Validator::make($data, [
            'customer_id' => ['required', 'string'],
            'payment_method_id' => ['required', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.amount' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.id' => ['nullable', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'description' => ['nullable', 'string'],
            'days_until_due' => ['nullable', 'integer', 'min:1'],
            'auto_advance' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);

        $validated = $validator->validate();

        return new self(
            customerId: $validated['customer_id'],
            paymentMethodId: $validated['payment_method_id'],
            items: $validated['items'],
            currency: strtolower($validated['currency'] ?? 'usd'),
            description: $validated['description'] ?? null,
            daysUntilDue: $validated['days_until_due'] ?? null,
            autoAdvance: $validated['auto_advance'] ?? true,
            metadata: $validated['metadata'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'customer_id' => $this->customerId,
            'payment_method_id' => $this->paymentMethodId,
            'items' => $this->items,
            'currency' => $this->currency,
            'description' => $this->description,
            'days_until_due' => $this->daysUntilDue,
            'auto_advance' => $this->autoAdvance,
            'metadata' => $this->metadata,
        ];
    }
}
