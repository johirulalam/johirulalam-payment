<?php

namespace Sayed\Payment\Services\Events;

use Sayed\Payment\Events\InvoiceEvent;
use Sayed\Payment\Events\CheckoutEvent;
use Sayed\Payment\Events\SubscriptionEvent;
use Sayed\Payment\DTOs\Webhooks\InvoiceEventDTO;
use Sayed\Payment\DTOs\Webhooks\CheckoutEventDTO;
use Sayed\Payment\DTOs\Webhooks\SubscriptionEventDTO;
use Illuminate\Support\Facades\Event;

class EventDispatcher
{
    protected array $listeners = [];

    /**
     * Register event listeners from config
     */
    public function __construct()
    {
        $this->listeners = config('payment.events', []);
    }

    /**
     * Dispatch invoice event
     */
    public function dispatchInvoice(InvoiceEventDTO $dto, string $provider, string $eventType, ?string $rawPayload = null): void
    {
        $eventClass = $this->getEventClass('invoice', $eventType);
        
        if (!$eventClass || !class_exists($eventClass)) {
            return;
        }

        $event = new $eventClass(
            provider: $provider,
            invoiceId: $dto->id,
            status: $dto->status,
            amount: (int)($dto->amount * 100), // Convert to cents
            currency: $dto->currency,
            customerId: $dto->customerId,
            subscriptionId: $dto->subscriptionId,
            metadata: $dto->metadata,
            rawPayload: $rawPayload,
        );

        $this->dispatch($event);
    }

    /**
     * Dispatch checkout event
     */
    public function dispatchCheckout(CheckoutEventDTO $dto, string $provider, string $eventType, ?string $rawPayload = null): void
    {
        $eventClass = $this->getEventClass('checkout', $eventType);
        
        if (!$eventClass || !class_exists($eventClass)) {
            return;
        }

        $event = new $eventClass(
            provider: $provider,
            transactionId: $dto->id,
            status: $dto->status,
            amount: (int)($dto->amount * 100), // Convert to cents
            currency: $dto->currency,
            customerId: $dto->customerId,
            customerEmail: $dto->customerEmail,
            metadata: $dto->metadata,
            rawPayload: $rawPayload,
        );

        $this->dispatch($event);
    }

    /**
     * Dispatch subscription event
     */
    public function dispatchSubscription(SubscriptionEventDTO $dto, string $provider, string $eventType, ?string $rawPayload = null): void
    {
        $eventClass = $this->getEventClass('subscription', $eventType);
        
        if (!$eventClass || !class_exists($eventClass)) {
            return;
        }

        $event = new $eventClass(
            provider: $provider,
            subscriptionId: $dto->subscriptionId ?? $dto->id,
            status: $dto->status,
            amount: $dto->amount ? (int)($dto->amount * 100) : null, // Convert to cents
            currency: $dto->currency,
            customerId: $dto->customerId,
            customerEmail: $dto->customerEmail,
            planId: $dto->planId,
            currentPeriodStart: $dto->currentPeriodStart ? date('Y-m-d H:i:s', $dto->currentPeriodStart) : null,
            currentPeriodEnd: $dto->currentPeriodEnd ? date('Y-m-d H:i:s', $dto->currentPeriodEnd) : null,
            metadata: $dto->metadata,
            rawPayload: $rawPayload,
        );

        $this->dispatch($event);
    }

    /**
     * Get event class from config
     */
    protected function getEventClass(string $eventType, string $eventName): ?string
    {
        return $this->listeners[$eventType][$eventName] ?? null;
    }

    /**
     * Dispatch the event
     */
    protected function dispatch($event): void
    {
        if (method_exists($event, 'handle')) {
            $event->handle();
        }

        // If Laravel's event dispatcher is available, use it
        if (class_exists(Event::class)) {
            Event::dispatch($event);
        }
    }
}
