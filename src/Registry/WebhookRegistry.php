<?php

namespace Sayed\Payment\Registry;

use Exception;
use Sayed\Payment\Services\Webhooks\ProviderIdentifier;

class WebhookRegistry
{
    protected $providers = [];

    public function registerProvider(string $providerName, string $handlerClass): void
    {
        if (isset($this->providers[$providerName])) {
            throw new Exception("Webhook provider {$providerName} is already registered.");
        }
        $this->providers[$providerName] = $handlerClass;
    }

    public function getProvider(array $headers)
    {
        $identifier = new ProviderIdentifier();
        $providerName = $identifier->identify($headers);

        if (!isset($this->providers[$providerName])) {
            throw new Exception("Webhook provider {$providerName} is not registered.");
        }

        $handlerClass = $this->providers[$providerName];
        return app($handlerClass);
    }

    public function getProviders(): array
    {
        return array_keys($this->providers);
    }
}
