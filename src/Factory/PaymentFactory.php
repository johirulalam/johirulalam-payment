<?php

namespace Sayed\Payment\Factory;

use Sayed\Payment\Registry\PaymentRegistry;
use Sayed\Payment\Enums\PaymentMethod;

class PaymentFactory
{
    public static function createAdapter(string|PaymentMethod $provider)
    {
        $providerValue = $provider instanceof PaymentMethod ? $provider->value : $provider;
        
        $registry = app(PaymentRegistry::class);
        return $registry->getAdapter($providerValue);
    }

    /**
     * Create adapter using enum
     */
    public static function driver(string|PaymentMethod|null $provider = null)
    {
        $providerValue = match (true) {
            $provider instanceof PaymentMethod => $provider->value,
            is_string($provider) => $provider,
            default => config('payment.default', 'stripe'),
        };

        return self::createAdapter($providerValue);
    }
}
