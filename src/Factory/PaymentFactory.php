<?php

namespace Sayed\Payment\Factory;

use Sayed\Payment\Registry\PaymentRegistry;

class PaymentFactory
{
    public static function createAdapter(string $provider)
    {
        $registry = app(PaymentRegistry::class);
        return $registry->getAdapter($provider);
    }
}
