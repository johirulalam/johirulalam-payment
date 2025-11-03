<?php declare(strict_types=1);

namespace Sayed\Payment\DTOs\Webhooks;

class InvoiceEventDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $provider,
        public readonly string $eventType,
        public readonly string $status,
        public readonly float $amount,
        public readonly float $amountDue,
        public readonly float $amountPaid,
        public readonly string $currency,
        public readonly ?string $invoiceNumber = null,
        public readonly ?string $customerId = null,
        public readonly ?string $customerEmail = null,
        public readonly ?string $subscriptionId = null,
        public readonly ?string $invoicePdf = null,
        public readonly ?string $hostedInvoiceUrl = null,
        public readonly ?int $dueDate = null,
        public readonly ?int $paidAt = null,
        public readonly ?bool $paid = false,
        public readonly ?array $lineItems = [],
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
            'amount_due' => $this->amountDue,
            'amount_paid' => $this->amountPaid,
            'currency' => $this->currency,
            'invoice_number' => $this->invoiceNumber,
            'customer_id' => $this->customerId,
            'customer_email' => $this->customerEmail,
            'subscription_id' => $this->subscriptionId,
            'invoice_pdf' => $this->invoicePdf,
            'hosted_invoice_url' => $this->hostedInvoiceUrl,
            'due_date' => $this->dueDate,
            'paid_at' => $this->paidAt,
            'paid' => $this->paid,
            'line_items' => $this->lineItems,
            'metadata' => $this->metadata,
            'timestamp' => $this->timestamp,
            'raw' => $this->raw,
        ];
    }
}
