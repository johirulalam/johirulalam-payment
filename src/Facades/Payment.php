<?php

namespace Sayed\Payment\Facades;

use Illuminate\Support\Facades\Facade;
use Sayed\Payment\Factory\PaymentFactory;

class Payment extends Facade
{
    protected static function getFacadeAccessor()
    {
        return PaymentFactory::class;
    }
}
