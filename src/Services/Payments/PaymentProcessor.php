<?php

namespace Sayed\Payment\Services\Payments;

abstract class PaymentProcessor
{
    abstract public function checkout(array $payload): array;
    abstract public function refundPayment(string $transactionId, float $amount): array;
}
