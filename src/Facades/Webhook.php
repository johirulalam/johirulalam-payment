<?php

namespace Sayed\Payment\Facades;

use Illuminate\Support\Facades\Facade;
use Sayed\Payment\Factory\WebhookFactory;

class Webhook extends Facade
{
    protected static function getFacadeAccessor()
    {
        return WebhookFactory::class;
    }
}
