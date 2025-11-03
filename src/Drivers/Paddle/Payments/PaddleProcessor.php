<?php

namespace Sayed\Payment\Drivers\Paddle\Payments;

use Sayed\Payment\Services\Payments\PaymentProcessor;
use Sayed\Payment\DTOs\CheckoutDTO;
use Sayed\Payment\DTOs\RefundDTO;
use GuzzleHttp\Client;
use Exception;

class PaddleProcessor extends PaymentProcessor
{
    protected $client;
    protected $vendorId;
    protected $vendorAuthCode;
    protected $apiUrl;
    protected $publicKey;

    public function __construct()
    {
        $environment = config('payment.providers.paddle.environment', 'sandbox');
        
        $this->vendorId = config('payment.providers.paddle.vendor_id');
        $this->vendorAuthCode = config('payment.providers.paddle.vendor_auth_code');
        $this->publicKey = config('payment.providers.paddle.public_key');
        
        $this->apiUrl = $environment === 'live'
            ? 'https://vendors.paddle.com/api'
            : 'https://sandbox-vendors.paddle.com/api';

        $this->client = new Client([
            'base_uri' => $this->apiUrl,
        ]);
    }

    public function checkout(array $payload): array
    {
        try {
            // Validate and create DTO
            $dto = CheckoutDTO::fromArray($payload);

            $currency = strtoupper($dto->currency);
            
            // Get product title from first product
            $productTitle = 'Default Product';
            if ($dto->products && is_array($dto->products) && isset($dto->products[0]['title'])) {
                $productTitle = $dto->products[0]['title'];
            }
            
            // Calculate total amount from products
            $amount = 0;
            if ($dto->products && is_array($dto->products)) {
                foreach ($dto->products as $product) {
                    $productAmount = $product['amount'] ?? 0;
                    $quantity = $product['quantity'] ?? 1;
                    $amount += $productAmount * $quantity;
                }
            }
            
            $prices = ["{$currency}:" . number_format($amount / 100, 2, '.', '')];

            $requestBody = [
                'vendor_id' => $this->vendorId,
                'vendor_auth_code' => $this->vendorAuthCode,
                'title' => $productTitle,
                'prices' => $prices,
            ];

            if (!empty($payload['webhook_url'])) {
                $requestBody['webhook_url'] = $payload['webhook_url'];
            }
            if ($dto->successUrl) {
                $requestBody['return_url'] = $dto->successUrl;
            }

            $response = $this->client->post('/2.0/product/generate_pay_link', [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestBody,
            ]);

            $data = json_decode($response->getBody(), true);

            if (!$data['success']) {
                throw new Exception('Paddle payment link generation failed');
            }

            return [
                'paymentLinkUrl' => $data['response']['url'],
            ];
        } catch (Exception $e) {
            throw new Exception('Paddle checkout failed: ' . $e->getMessage());
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

            $response = $this->client->post('/2.0/payment/refund', [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'vendor_id' => $this->vendorId,
                    'vendor_auth_code' => $this->vendorAuthCode,
                    'order_id' => $dto->transactionId,
                    'amount' => $dto->amount,
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            return [
                'success' => $data['success'] ?? false,
                'refund_id' => $data['response']['refund_request_id'] ?? null,
            ];
        } catch (Exception $e) {
            throw new Exception('Paddle refund failed: ' . $e->getMessage());
        }
    }
}
