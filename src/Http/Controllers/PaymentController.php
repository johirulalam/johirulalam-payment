<?php

namespace Sayed\Payment\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Sayed\Payment\Factory\PaymentFactory;
use Exception;

class PaymentController extends Controller
{
    public function checkout(Request $request, string $provider)
    {
        try {
            $adapter = PaymentFactory::createAdapter($provider);
            $result = $adapter->checkout($request->all());

            return response()->json([
                'success' => true,
                'provider' => $provider,
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function refund(Request $request, string $provider)
    {
        try {
            $transactionId = $request->input('transaction_id');
            $amount = $request->input('amount');

            if (!$transactionId || !$amount) {
                throw new Exception('Transaction ID and amount are required');
            }

            $adapter = PaymentFactory::createAdapter($provider);
            $result = $adapter->refundPayment($transactionId, $amount);

            return response()->json([
                'success' => true,
                'provider' => $provider,
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
