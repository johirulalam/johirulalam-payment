<?php

namespace App\Examples;

use Illuminate\Http\Request;
use Sayed\Payment\Facades\Payment;
use Sayed\Payment\Factory\PaymentFactory;

class PaymentExamples
{
    public function stripeCheckout()
    {
        $adapter = Payment::createAdapter('stripe');
        
        $result = $adapter->checkout([
            'has_price_id' => false,
            'currency' => 'usd',
            'amount' => 5000,
            'product' => ['title' => 'Premium Membership'],
            'quantity' => 1,
            'is_allow_promotion_code' => true,
            'metadata' => ['user_id' => auth()->id()],
            'mode' => 'payment',
            'success_url' => route('payment.success'),
            'cancel_url' => route('payment.cancel'),
        ]);
        
        return redirect($result['paymentLinkUrl']);
    }

    public function paypalCheckout()
    {
        $adapter = PaymentFactory::createAdapter('paypal');
        
        $result = $adapter->checkout([
            'amount' => 3000,
            'currency' => 'USD',
            'success_url' => route('payment.success'),
            'cancel_url' => route('payment.cancel'),
        ]);
        
        return redirect($result['paymentLinkUrl']);
    }

    public function processRefund($provider, $transactionId, $amount)
    {
        try {
            $adapter = PaymentFactory::createAdapter($provider);
            $result = $adapter->refundPayment($transactionId, $amount);
            
            if ($result['success']) {
                return response()->json([
                    'message' => 'Refund processed successfully',
                    'refund_id' => $result['refund_id']
                ]);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
