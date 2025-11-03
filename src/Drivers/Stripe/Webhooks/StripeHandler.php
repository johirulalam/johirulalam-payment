<?php

namespace Sayed\Payment\Drivers\Stripe\Webhooks;

use Sayed\Payment\Services\Webhooks\WebhookProcessor;
use Sayed\Payment\DTOs\Webhooks\CheckoutEventDTO;
use Sayed\Payment\DTOs\Webhooks\SubscriptionEventDTO;
use Sayed\Payment\DTOs\Webhooks\InvoiceEventDTO;
use Stripe\Stripe;
use Stripe\Webhook;
use Exception;

class StripeHandler extends WebhookProcessor
{
    public function __construct()
    {
        $secretKey = config('payment.providers.stripe.secret_key');
        Stripe::setApiKey($secretKey);
    }

    public function process(string $payload, array $headers): array
    {
        try {
            $signature = $headers['stripe-signature'] ?? null;

            if (!$signature) {
                throw new Exception('Missing Stripe signature header');
            }

            $validationResult = $this->validate($payload, $headers);

            if (!$validationResult['isValid']) {
                throw new Exception('Invalid webhook signature');
            }

            $eventType = $validationResult['event']->type ?? '';
            $dto = $this->transform($validationResult['data'], $eventType);

            return $dto->toArray();
        } catch (Exception $e) {
            throw new Exception('Error processing webhook: ' . $e->getMessage());
        }
    }

    public function validate(string $payload, array $headers): array
    {
        try {
            $signature = $headers['stripe-signature'] ?? null;
            $webhookSecret = config('payment.providers.stripe.webhook_secret');

            $event = Webhook::constructEvent($payload, $signature, $webhookSecret);

            return [
                'isValid' => true,
                'data' => $event->data->object,
                'event' => $event,
            ];
        } catch (Exception $e) {
            return [
                'isValid' => false,
                'data' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function transform($payload, string $eventType): CheckoutEventDTO|SubscriptionEventDTO|InvoiceEventDTO
    {
        // Determine event category
        if (str_contains($eventType, 'checkout.session')) {
            return $this->transformCheckout($payload, $eventType);
        } elseif (str_contains($eventType, 'customer.subscription')) {
            return $this->transformSubscription($payload, $eventType);
        } elseif (str_contains($eventType, 'invoice')) {
            return $this->transformInvoice($payload, $eventType);
        }

        // Default to checkout for unknown events
        return $this->transformCheckout($payload, $eventType);
    }

    protected function transformCheckout(array $payload, string $eventType): CheckoutEventDTO
    {
        return new CheckoutEventDTO(
            id: $payload['id'] ?? '',
            provider: 'stripe',
            eventType: $eventType,
            status: $payload['status'] ?? 'unknown',
            amount: ($payload['amount_total'] ?? 0) / 100,
            currency: $payload['currency'] ?? 'usd',
            customerId: $payload['customer'] ?? null,
            customerEmail: $payload['customer_details']['email'] ?? $payload['customer_email'] ?? null,
            paymentMethod: $payload['payment_method_types'][0] ?? null,
            metadata: $payload['metadata'] ?? [],
            timestamp: $payload['created'] ?? time(),
            raw: $payload,
        );
    }

    protected function transformSubscription(array $payload, string $eventType): SubscriptionEventDTO
    {
        return new SubscriptionEventDTO(
            id: $payload['id'] ?? '',
            provider: 'stripe',
            eventType: $eventType,
            status: $payload['status'] ?? 'unknown',
            customerId: $payload['customer'] ?? null,
            customerEmail: null, // Stripe doesn't include email in subscription object
            subscriptionId: $payload['id'] ?? null,
            planId: $payload['items']['data'][0]['plan']['id'] ?? null,
            amount: ($payload['items']['data'][0]['plan']['amount'] ?? 0) / 100,
            currency: $payload['items']['data'][0]['plan']['currency'] ?? 'usd',
            interval: $payload['items']['data'][0]['plan']['interval'] ?? null,
            currentPeriodStart: $payload['current_period_start'] ?? null,
            currentPeriodEnd: $payload['current_period_end'] ?? null,
            canceledAt: $payload['canceled_at'] ?? null,
            trialStart: $payload['trial_start'] ?? null,
            trialEnd: $payload['trial_end'] ?? null,
            metadata: $payload['metadata'] ?? [],
            timestamp: $payload['created'] ?? time(),
            raw: $payload,
        );
    }

    protected function transformInvoice(array $payload, string $eventType): InvoiceEventDTO
    {
        return new InvoiceEventDTO(
            id: $payload['id'] ?? '',
            provider: 'stripe',
            eventType: $eventType,
            status: $payload['status'] ?? 'unknown',
            amount: ($payload['amount_total'] ?? $payload['total'] ?? 0) / 100,
            amountDue: ($payload['amount_due'] ?? 0) / 100,
            amountPaid: ($payload['amount_paid'] ?? 0) / 100,
            currency: $payload['currency'] ?? 'usd',
            invoiceNumber: $payload['number'] ?? null,
            customerId: $payload['customer'] ?? null,
            customerEmail: $payload['customer_email'] ?? null,
            subscriptionId: $payload['subscription'] ?? null,
            invoicePdf: $payload['invoice_pdf'] ?? null,
            hostedInvoiceUrl: $payload['hosted_invoice_url'] ?? null,
            dueDate: $payload['due_date'] ?? null,
            paidAt: $payload['status_transitions']['paid_at'] ?? null,
            paid: $payload['paid'] ?? false,
            lineItems: $payload['lines']['data'] ?? [],
            metadata: $payload['metadata'] ?? [],
            timestamp: $payload['created'] ?? time(),
            raw: $payload,
        );
    }
}
