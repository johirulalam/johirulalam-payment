<?php

namespace Sayed\Payment\Factory;

use Sayed\Payment\Registry\WebhookRegistry;

class WebhookFactory
{
    public static function createAdapter(array $headers)
    {
        $registry = app(WebhookRegistry::class);
        return $registry->getProvider($headers);
    }
}
