<?php

namespace Sayed\Payment\Registry;

use Exception;

class PaymentRegistry
{
    protected $adapters = [];

    public function registerProvider(string $providerName, string $adapterClass): void
    {
        if (isset($this->adapters[$providerName])) {
            throw new Exception("Provider {$providerName} is already registered.");
        }
        $this->adapters[$providerName] = $adapterClass;
    }

    public function getAdapter(string $providerName)
    {
        if (!isset($this->adapters[$providerName])) {
            throw new Exception("Provider {$providerName} is not registered.");
        }
        $adapterClass = $this->adapters[$providerName];
        return app($adapterClass);
    }

    public function getProviders(): array
    {
        return array_keys($this->adapters);
    }
}
