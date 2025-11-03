<?php

namespace Sayed\Payment\Services\Webhooks;

use Exception;

class ProviderIdentifier
{
    public function identify(array $headers): string
    {
        $headers = array_change_key_case($headers, CASE_LOWER);

        if (isset($headers['stripe-signature'])) {
            return 'stripe';
        }

        if (isset($headers['paypal-transmission-id']) || 
            isset($headers['paypal-transmission-sig'])) {
            return 'paypal';
        }

        if (isset($headers['paddle-signature'])) {
            return 'paddle';
        }

        throw new Exception('Unable to identify payment provider from headers.');
    }
}
