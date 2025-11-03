<?php

namespace Sayed\Payment;

use Illuminate\Support\ServiceProvider;
use Sayed\Payment\Registry\PaymentRegistry;
use Sayed\Payment\Registry\WebhookRegistry;
use Sayed\Payment\Drivers\Stripe\Payments\StripeProcessor;
use Sayed\Payment\Drivers\PayPal\Payments\PayPalProcessor;
use Sayed\Payment\Drivers\Paddle\Payments\PaddleProcessor;
use Sayed\Payment\Drivers\Stripe\Webhooks\StripeHandler;
use Sayed\Payment\Drivers\PayPal\Webhooks\PayPalHandler;
use Sayed\Payment\Drivers\Paddle\Webhooks\PaddleHandler;

class PaymentServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/payment.php', 'payment'
        );

        $this->app->singleton(PaymentRegistry::class, function ($app) {
            return new PaymentRegistry();
        });

        $this->app->singleton(WebhookRegistry::class, function ($app) {
            return new WebhookRegistry();
        });

        $this->registerPaymentProviders();
        $this->registerWebhookProviders();
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/payment.php' => config_path('payment.php'),
        ], 'payment-config');

        $this->loadRoutesFrom(__DIR__.'/../routes/payment.php');
    }

    protected function registerPaymentProviders()
    {
        $registry = $this->app->make(PaymentRegistry::class);
        
        $registry->registerProvider('stripe', StripeProcessor::class);
        $registry->registerProvider('paypal', PayPalProcessor::class);
        $registry->registerProvider('paddle', PaddleProcessor::class);
    }

    protected function registerWebhookProviders()
    {
        $registry = $this->app->make(WebhookRegistry::class);
        
        $registry->registerProvider('stripe', StripeHandler::class);
        $registry->registerProvider('paypal', PayPalHandler::class);
        $registry->registerProvider('paddle', PaddleHandler::class);
    }
}
