<?php declare(strict_types=1);

namespace Sayed\Payment\DTOs;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RefundDTO
{
    public function __construct(
        public readonly string $transactionId,
        public readonly float $amount,
        public readonly ?string $reason = null,
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
            'transaction_id' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'in:duplicate,fraudulent,requested_by_customer'],
            'metadata' => ['nullable', 'array'],
        ]);

        $validated = $validator->validate();

        return new self(
            transactionId: $validated['transaction_id'],
            amount: (float) $validated['amount'],
            reason: $validated['reason'] ?? null,
            metadata: $validated['metadata'] ?? [],
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'transaction_id' => $this->transactionId,
            'amount' => $this->amount,
            'reason' => $this->reason,
            'metadata' => $this->metadata,
        ], fn($value) => $value !== null);
    }

    /**
     * Get amount in cents
     */
    public function getAmountInCents(): int
    {
        return (int)($this->amount * 100);
    }
}
