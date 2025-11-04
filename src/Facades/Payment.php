<?php

namespace Sayed\Payment\Facades;

use Illuminate\Support\Facades\Facade;
use Sayed\Payment\Factory\PaymentFactory;
use Sayed\Payment\Enums\PaymentMethod;

/**
 * @method static mixed driver(string|PaymentMethod|null $provider = null)
 * @method static mixed createAdapter(string|PaymentMethod $provider)
 * 
 * @see PaymentFactory
 */
class Payment extends Facade
{
    protected static function getFacadeAccessor()
    {
        return PaymentFactory::class;
    }
}
