<?php

namespace Sayed\Payment\Drivers\PayPal\Webhooks;

use Sayed\Payment\Services\Webhooks\WebhookProcessor;
use Sayed\Payment\DTOs\Webhooks\CheckoutEventDTO;
use Sayed\Payment\DTOs\Webhooks\SubscriptionEventDTO;
use Sayed\Payment\DTOs\Webhooks\InvoiceEventDTO;
use Exception;

class PayPalHandler extends WebhookProcessor
{
    public function process(string $payload, array $headers): array
    {
        try {
            $validationResult = $this->validate($payload, $headers);

            if (!$validationResult['isValid']) {
                throw new Exception('Invalid PayPal webhook');
            }

            $data = json_decode($payload, true);
            $eventType = $data['event_type'] ?? '';
            $dto = $this->transform($data, $eventType);

            return $dto->toArray();
        } catch (Exception $e) {
            throw new Exception('Error processing PayPal webhook: ' . $e->getMessage());
        }
    }

    public function validate(string $payload, array $headers): array
    {
        try {
            $transmissionId = $headers['paypal-transmission-id'] ?? null;
            $transmissionSig = $headers['paypal-transmission-sig'] ?? null;

            if (!$transmissionId || !$transmissionSig) {
                return [
                    'isValid' => false,
                    'data' => null,
                    'error' => 'Missing PayPal webhook headers',
                ];
            }

            return [
                'isValid' => true,
                'data' => json_decode($payload, true),
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
        $resource = $payload['resource'] ?? [];

        // Determine event category
        if (str_contains($eventType, 'CHECKOUT.ORDER') || str_contains($eventType, 'PAYMENT.CAPTURE')) {
            return $this->transformCheckout($payload, $eventType, $resource);
        } elseif (str_contains($eventType, 'BILLING.SUBSCRIPTION')) {
            return $this->transformSubscription($payload, $eventType, $resource);
        } elseif (str_contains($eventType, 'INVOICING.INVOICE')) {
            return $this->transformInvoice($payload, $eventType, $resource);
        }

        // Default to checkout
        return $this->transformCheckout($payload, $eventType, $resource);
    }

    protected function transformCheckout(array $payload, string $eventType, array $resource): CheckoutEventDTO
    {
        $amount = 0;
        $currency = 'USD';
        
        if (isset($resource['amount'])) {
            $amount = (float) ($resource['amount']['value'] ?? 0);
            $currency = $resource['amount']['currency_code'] ?? 'USD';
        } elseif (isset($resource['purchase_units'][0]['amount'])) {
            $amount = (float) ($resource['purchase_units'][0]['amount']['value'] ?? 0);
            $currency = $resource['purchase_units'][0]['amount']['currency_code'] ?? 'USD';
        }

        return new CheckoutEventDTO(
            id: $resource['id'] ?? $payload['id'] ?? '',
            provider: 'paypal',
            eventType: $eventType,
            status: $resource['status'] ?? 'unknown',
            amount: $amount,
            currency: strtolower($currency),
            customerId: $resource['payer']['payer_id'] ?? null,
            customerEmail: $resource['payer']['email_address'] ?? null,
            paymentMethod: 'paypal',
            metadata: $resource['custom'] ?? [],
            timestamp: strtotime($payload['create_time'] ?? 'now'),
            raw: $payload,
        );
    }

    protected function transformSubscription(array $payload, string $eventType, array $resource): SubscriptionEventDTO
    {
        $amount = 0;
        $currency = 'USD';
        
        if (isset($resource['billing_info']['last_payment']['amount'])) {
            $amount = (float) ($resource['billing_info']['last_payment']['amount']['value'] ?? 0);
            $currency = $resource['billing_info']['last_payment']['amount']['currency_code'] ?? 'USD';
        }

        return new SubscriptionEventDTO(
            id: $resource['id'] ?? $payload['id'] ?? '',
            provider: 'paypal',
            eventType: $eventType,
            status: $resource['status'] ?? 'unknown',
            customerId: $resource['subscriber']['payer_id'] ?? null,
            customerEmail: $resource['subscriber']['email_address'] ?? null,
            subscriptionId: $resource['id'] ?? null,
            planId: $resource['plan_id'] ?? null,
            amount: $amount,
            currency: strtolower($currency),
            interval: null, // PayPal doesn't provide this in webhook
            currentPeriodStart: isset($resource['billing_info']['next_billing_time']) ? strtotime($resource['billing_info']['next_billing_time']) - (30 * 24 * 60 * 60) : null,
            currentPeriodEnd: isset($resource['billing_info']['next_billing_time']) ? strtotime($resource['billing_info']['next_billing_time']) : null,
            canceledAt: isset($resource['status_update_time']) && $resource['status'] === 'CANCELLED' ? strtotime($resource['status_update_time']) : null,
            trialStart: null,
            trialEnd: null,
            metadata: [],
            timestamp: strtotime($payload['create_time'] ?? 'now'),
            raw: $payload,
        );
    }

    protected function transformInvoice(array $payload, string $eventType, array $resource): InvoiceEventDTO
    {
        $amount = (float) ($resource['amount']['value'] ?? 0);
        $currency = $resource['amount']['currency_code'] ?? 'USD';

        return new InvoiceEventDTO(
            id: $resource['id'] ?? $payload['id'] ?? '',
            provider: 'paypal',
            eventType: $eventType,
            status: $resource['status'] ?? 'unknown',
            amount: $amount,
            amountDue: (float) ($resource['due_amount']['value'] ?? $amount),
            amountPaid: (float) ($resource['paid_amount']['value'] ?? 0),
            currency: strtolower($currency),
            invoiceNumber: $resource['number'] ?? null,
            customerId: $resource['primary_recipients'][0]['billing_info']['email_address'] ?? null,
            customerEmail: $resource['primary_recipients'][0]['billing_info']['email_address'] ?? null,
            subscriptionId: null,
            invoicePdf: $resource['links'][0]['href'] ?? null,
            hostedInvoiceUrl: $resource['links'][1]['href'] ?? null,
            dueDate: isset($resource['due_date']) ? strtotime($resource['due_date']) : null,
            paidAt: isset($resource['payments']['paid'][0]['payment_date']) ? strtotime($resource['payments']['paid'][0]['payment_date']) : null,
            paid: $resource['status'] === 'PAID',
            lineItems: $resource['items'] ?? [],
            metadata: [],
            timestamp: strtotime($payload['create_time'] ?? 'now'),
            raw: $payload,
        );
    }
}
