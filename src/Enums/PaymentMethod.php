<?php

namespace Sayed\Payment\Enums;

enum PaymentMethod: string
{
    case STRIPE = 'stripe';
    case PAYPAL = 'paypal';
    case PADDLE = 'paddle';

    /**
     * Get all available payment methods as array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get display name for the payment method
     */
    public function displayName(): string
    {
        return match ($this) {
            self::STRIPE => 'Stripe',
            self::PAYPAL => 'PayPal',
            self::PADDLE => 'Paddle',
        };
    }
}
