<?php

namespace Sayed\Payment\Drivers\PayPal\Payments;

use Sayed\Payment\Services\Payments\PaymentProcessor;
use Sayed\Payment\DTOs\CheckoutDTO;
use Sayed\Payment\DTOs\RefundDTO;
use GuzzleHttp\Client;
use Exception;

class PayPalProcessor extends PaymentProcessor
{
    protected $client;
    protected $clientId;
    protected $clientSecret;
    protected $mode;
    protected $baseUrl;

    public function __construct()
    {
        $this->clientId = config('payment.providers.paypal.client_id');
        $this->clientSecret = config('payment.providers.paypal.client_secret');
        $this->mode = config('payment.providers.paypal.mode', 'sandbox');
        $this->baseUrl = $this->mode === 'live' 
            ? 'https://api-m.paypal.com' 
            : 'https://api-m.sandbox.paypal.com';
        
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
        ]);
    }

    protected function getAccessToken(): string
    {
        try {
            $response = $this->client->post('/v1/oauth2/token', [
                'auth' => [$this->clientId, $this->clientSecret],
                'form_params' => [
                    'grant_type' => 'client_credentials',
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['access_token'];
        } catch (Exception $e) {
            throw new Exception('Failed to get PayPal access token: ' . $e->getMessage());
        }
    }

    public function checkout(array $payload): array
    {
        try {
            // Validate and create DTO
            $dto = CheckoutDTO::fromArray($payload);

            $accessToken = $this->getAccessToken();

            // Calculate total amount from products
            $totalAmount = 0;
            $purchaseUnits = [];

            if ($dto->products && is_array($dto->products)) {
                foreach ($dto->products as $product) {
                    // Get amount either from product amount or by using id (though PayPal doesn't support price IDs like Stripe)
                    $amount = $product['amount'] ?? 0;
                    $quantity = $product['quantity'] ?? 1;
                    $totalAmount += $amount * $quantity;
                }
                
                $purchaseUnits[] = [
                    'amount' => [
                        'currency_code' => strtoupper($dto->currency),
                        'value' => number_format($totalAmount / 100, 2, '.', ''),
                    ],
                ];
            }

            $response = $this->client->post('/v2/checkout/orders', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'intent' => 'CAPTURE',
                    'purchase_units' => $purchaseUnits,
                    'application_context' => [
                        'return_url' => $dto->successUrl,
                        'cancel_url' => $dto->cancelUrl,
                    ],
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            
            $approvalLink = null;
            foreach ($data['links'] as $link) {
                if ($link['rel'] === 'approve') {
                    $approvalLink = $link['href'];
                    break;
                }
            }

            return [
                'paymentLinkUrl' => $approvalLink,
                'order_id' => $data['id'],
            ];
        } catch (Exception $e) {
            throw new Exception('PayPal checkout failed: ' . $e->getMessage());
        }
    }

    public function refundPayment(string $transactionId, float $amount): array
    {
        try {
            // Validate using DTO
            $dto = RefundDTO::fromArray([
                'transaction_id' => $transactionId,
                'amount' => $amount,
            ]);

            $accessToken = $this->getAccessToken();

            $response = $this->client->post("/v2/payments/captures/{$dto->transactionId}/refund", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'amount' => [
                        'value' => number_format($dto->amount, 2, '.', ''),
                        'currency_code' => 'USD',
                    ],
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            return [
                'success' => true,
                'refund_id' => $data['id'],
                'status' => $data['status'],
            ];
        } catch (Exception $e) {
            throw new Exception('PayPal refund failed: ' . $e->getMessage());
        }
    }
}
