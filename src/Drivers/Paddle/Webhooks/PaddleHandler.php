<?php

namespace Sayed\Payment\Drivers\Paddle\Webhooks;

use Sayed\Payment\Services\Webhooks\WebhookProcessor;
use Sayed\Payment\Services\Events\EventDispatcher;
use Sayed\Payment\DTOs\Webhooks\CheckoutEventDTO;
use Sayed\Payment\DTOs\Webhooks\SubscriptionEventDTO;
use Sayed\Payment\DTOs\Webhooks\InvoiceEventDTO;
use Exception;

class PaddleHandler extends WebhookProcessor
{
    public function process(string $payload, array $headers): array
    {
        try {
            $validationResult = $this->validate($payload, $headers);

            if (!$validationResult['isValid']) {
                throw new Exception('Invalid Paddle webhook');
            }

            $data = json_decode($payload, true);
            $eventType = $data['event_type'] ?? '';
            $dto = $this->transform($data, $eventType);

            // Dispatch event to user-defined listeners
            $this->dispatchEvent($dto, $eventType, $payload);

            return $dto->toArray();
        } catch (Exception $e) {
            throw new Exception('Error processing Paddle webhook: ' . $e->getMessage());
        }
    }

    /**
     * Dispatch event to user-defined listeners
     */
    protected function dispatchEvent($dto, string $eventType, string $rawPayload): void
    {
        $dispatcher = new EventDispatcher();
        $eventName = $this->getEventName($eventType);

        if ($dto instanceof InvoiceEventDTO) {
            $dispatcher->dispatchInvoice($dto, 'paddle', $eventName, $rawPayload);
        } elseif ($dto instanceof CheckoutEventDTO) {
            $dispatcher->dispatchCheckout($dto, 'paddle', $eventName, $rawPayload);
        } elseif ($dto instanceof SubscriptionEventDTO) {
            $dispatcher->dispatchSubscription($dto, 'paddle', $eventName, $rawPayload);
        }
    }

    /**
     * Get simplified event name from Paddle event type
     */
    protected function getEventName(string $eventType): string
    {
        // Convert Paddle event types to simplified names
        $eventMap = [
            'transaction.completed' => 'completed',
            'transaction.paid' => 'completed',
            'transaction.canceled' => 'expired',
            'subscription.created' => 'created',
            'subscription.updated' => 'updated',
            'subscription.canceled' => 'deleted',
            'subscription.paused' => 'deleted',
            'subscription.past_due' => 'payment_failed',
            'subscription.trialing' => 'trial_ending',
        ];

        return $eventMap[$eventType] ?? $eventType;
    }

    public function validate(string $payload, array $headers): array
    {
        try {
            $signature = $headers['paddle-signature'] ?? null;

            if (!$signature) {
                return [
                    'isValid' => false,
                    'data' => null,
                    'error' => 'Missing Paddle signature header',
                ];
            }

            $publicKey = config('payment.providers.paddle.public_key');
            
            $signatureParts = [];
            foreach (explode(';', $signature) as $part) {
                $keyValue = explode('=', $part, 2);
                if (count($keyValue) === 2) {
                    $signatureParts[$keyValue[0]] = $keyValue[1];
                }
            }

            $ts = $signatureParts['ts'] ?? null;
            $h1 = $signatureParts['h1'] ?? null;

            if (!$ts || !$h1) {
                return [
                    'isValid' => false,
                    'data' => null,
                    'error' => 'Invalid signature format',
                ];
            }

            $signedPayload = $ts . ':' . $payload;

            $isValid = openssl_verify(
                $signedPayload,
                base64_decode($h1),
                $publicKey,
                OPENSSL_ALGO_SHA256
            );

            return [
                'isValid' => $isValid === 1,
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
        $data = $payload['data'] ?? [];

        // Determine event category
        if (str_contains($eventType, 'transaction.') || str_contains($eventType, 'order.')) {
            return $this->transformCheckout($payload, $eventType, $data);
        } elseif (str_contains($eventType, 'subscription.')) {
            return $this->transformSubscription($payload, $eventType, $data);
        } elseif (str_contains($eventType, 'invoice.')) {
            return $this->transformInvoice($payload, $eventType, $data);
        }

        // Default to checkout
        return $this->transformCheckout($payload, $eventType, $data);
    }

    protected function transformCheckout(array $payload, string $eventType, array $data): CheckoutEventDTO
    {
        $amount = 0;
        $currency = 'USD';
        
        if (isset($data['details']['totals']['total'])) {
            $amount = (float) $data['details']['totals']['total'];
            $currency = $data['details']['totals']['currency_code'] ?? 'USD';
        } elseif (isset($data['amount'])) {
            $amount = (float) $data['amount'];
            $currency = $data['currency_code'] ?? 'USD';
        }

        return new CheckoutEventDTO(
            id: $data['id'] ?? $payload['id'] ?? '',
            provider: 'paddle',
            eventType: $eventType,
            status: $data['status'] ?? 'unknown',
            amount: $amount,
            currency: strtolower($currency),
            customerId: $data['customer_id'] ?? null,
            customerEmail: $data['customer']['email'] ?? null,
            paymentMethod: 'paddle',
            metadata: $data['custom_data'] ?? [],
            timestamp: isset($payload['occurred_at']) ? strtotime($payload['occurred_at']) : time(),
            raw: $payload,
        );
    }

    protected function transformSubscription(array $payload, string $eventType, array $data): SubscriptionEventDTO
    {
        $amount = 0;
        $currency = 'USD';
        
        if (isset($data['items'][0]['price']['unit_price']['amount'])) {
            $amount = (float) $data['items'][0]['price']['unit_price']['amount'];
            $currency = $data['items'][0]['price']['unit_price']['currency_code'] ?? 'USD';
        }

        return new SubscriptionEventDTO(
            id: $data['id'] ?? $payload['id'] ?? '',
            provider: 'paddle',
            eventType: $eventType,
            status: $data['status'] ?? 'unknown',
            customerId: $data['customer_id'] ?? null,
            customerEmail: null,
            subscriptionId: $data['id'] ?? null,
            planId: $data['items'][0]['price']['id'] ?? null,
            amount: $amount,
            currency: strtolower($currency),
            interval: $data['items'][0]['price']['billing_cycle']['interval'] ?? null,
            currentPeriodStart: isset($data['current_billing_period']['starts_at']) ? strtotime($data['current_billing_period']['starts_at']) : null,
            currentPeriodEnd: isset($data['current_billing_period']['ends_at']) ? strtotime($data['current_billing_period']['ends_at']) : null,
            canceledAt: isset($data['canceled_at']) ? strtotime($data['canceled_at']) : null,
            trialStart: isset($data['started_at']) ? strtotime($data['started_at']) : null,
            trialEnd: isset($data['first_billed_at']) ? strtotime($data['first_billed_at']) : null,
            metadata: $data['custom_data'] ?? [],
            timestamp: isset($payload['occurred_at']) ? strtotime($payload['occurred_at']) : time(),
            raw: $payload,
        );
    }

    protected function transformInvoice(array $payload, string $eventType, array $data): InvoiceEventDTO
    {
        $amount = (float) ($data['totals']['total'] ?? 0);
        $currency = $data['currency_code'] ?? 'USD';

        return new InvoiceEventDTO(
            id: $data['id'] ?? $payload['id'] ?? '',
            provider: 'paddle',
            eventType: $eventType,
            status: $data['status'] ?? 'unknown',
            amount: $amount,
            amountDue: (float) ($data['totals']['balance'] ?? $amount),
            amountPaid: (float) ($data['totals']['credit'] ?? 0),
            currency: strtolower($currency),
            invoiceNumber: $data['number'] ?? null,
            customerId: $data['customer_id'] ?? null,
            customerEmail: null,
            subscriptionId: $data['subscription_id'] ?? null,
            invoicePdf: $data['invoice_pdf'] ?? null,
            hostedInvoiceUrl: $data['invoice_url'] ?? null,
            dueDate: isset($data['due_at']) ? strtotime($data['due_at']) : null,
            paidAt: isset($data['paid_at']) ? strtotime($data['paid_at']) : null,
            paid: $data['status'] === 'paid',
            lineItems: $data['items'] ?? [],
            metadata: $data['custom_data'] ?? [],
            timestamp: isset($payload['occurred_at']) ? strtotime($payload['occurred_at']) : time(),
            raw: $payload,
        );
    }
}
