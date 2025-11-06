<?php

namespace Sayed\Payment\Drivers\Paddle\Payments;

use Sayed\Payment\Services\Payments\PaymentProcessor;
use Sayed\Payment\DTOs\CheckoutDTO;
use Sayed\Payment\DTOs\RefundDTO;
use Sayed\Payment\DTOs\ProductDTO;
use Sayed\Payment\DTOs\RecurringProductDTO;
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

    /**
     * Create a product (one-time payment)
     */
    public function createProduct(array $data): ProductDTO
    {
        try {
            $response = $this->client->post('/2.0/product/create', [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'vendor_id' => $this->vendorId,
                    'vendor_auth_code' => $this->vendorAuthCode,
                    'name' => $data['name'],
                    'base_price' => number_format($data['amount'] / 100, 2, '.', ''),
                    'currency' => strtoupper($data['currency'] ?? 'USD'),
                    'description' => $data['description'] ?? '',
                ],
            ]);

            $result = json_decode($response->getBody(), true);

            if (!$result['success']) {
                throw new Exception('Paddle product creation failed');
            }

            return new ProductDTO(
                productId: $result['response']['product_id'],
                name: $data['name'],
                amount: $data['amount'],
                currency: strtoupper($data['currency'] ?? 'USD'),
                type: 'one_time',
                priceId: null,
                description: $data['description'] ?? null,
                metadata: []
            );
        } catch (Exception $e) {
            throw new Exception('Failed to create Paddle product: ' . $e->getMessage());
        }
    }

    /**
     * Create a recurring product (subscription plan)
     */
    public function createRecurringProduct(array $data): RecurringProductDTO
    {
        try {
            $response = $this->client->post('/2.1/subscription/plans_create', [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'vendor_id' => $this->vendorId,
                    'vendor_auth_code' => $this->vendorAuthCode,
                    'plan_name' => $data['name'],
                    'plan_trial_days' => $data['trial_days'] ?? 0,
                    'plan_type' => 'month', // month, year, day, week
                    'plan_length' => $data['interval_count'] ?? 1,
                    'initial_price' => [
                        strtoupper($data['currency'] ?? 'USD') => number_format($data['amount'] / 100, 2, '.', ''),
                    ],
                    'recurring_price' => [
                        strtoupper($data['currency'] ?? 'USD') => number_format($data['amount'] / 100, 2, '.', ''),
                    ],
                ],
            ]);

            $result = json_decode($response->getBody(), true);

            if (!$result['success']) {
                throw new Exception('Paddle subscription plan creation failed');
            }

            return new RecurringProductDTO(
                productId: $result['response']['product_id'],
                name: $data['name'],
                amount: $data['amount'],
                currency: strtoupper($data['currency'] ?? 'USD'),
                interval: $data['interval'] ?? 'month',
                intervalCount: $data['interval_count'] ?? 1,
                type: 'recurring',
                priceId: null,
                planId: $result['response']['product_id'],
                description: null,
                trialDays: $data['trial_days'] ?? null,
                metadata: []
            );
        } catch (Exception $e) {
            throw new Exception('Failed to create Paddle subscription plan: ' . $e->getMessage());
        }
    }

    /**
     * List all subscription plans
     */
    public function listProducts(int $limit = 10): array
    {
        try {
            $response = $this->client->post('/2.1/subscription/plans', [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'vendor_id' => $this->vendorId,
                    'vendor_auth_code' => $this->vendorAuthCode,
                ],
            ]);

            $result = json_decode($response->getBody(), true);

            if (!$result['success']) {
                return [];
            }

            $plans = array_slice($result['response'] ?? [], 0, $limit);

            return array_map(function ($plan) {
                return [
                    'id' => $plan['id'],
                    'name' => $plan['name'],
                    'billing_type' => $plan['billing_type'],
                    'billing_period' => $plan['billing_period'],
                    'initial_price' => $plan['initial_price'] ?? [],
                    'recurring_price' => $plan['recurring_price'] ?? [],
                ];
            }, $plans);
        } catch (Exception $e) {
            throw new Exception('Failed to list Paddle products: ' . $e->getMessage());
        }
    }

    /**
     * Get product/plan details
     */
    public function getProduct(string $planId): array
    {
        try {
            // Paddle doesn't have a direct get plan endpoint, so we list and filter
            $response = $this->client->post('/2.1/subscription/plans', [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'vendor_id' => $this->vendorId,
                    'vendor_auth_code' => $this->vendorAuthCode,
                    'plan' => $planId,
                ],
            ]);

            $result = json_decode($response->getBody(), true);

            if (!$result['success'] || empty($result['response'])) {
                throw new Exception('Plan not found');
            }

            $plan = $result['response'][0];

            return [
                'id' => $plan['id'],
                'name' => $plan['name'],
                'billing_type' => $plan['billing_type'],
                'billing_period' => $plan['billing_period'],
                'initial_price' => $plan['initial_price'] ?? [],
                'recurring_price' => $plan['recurring_price'] ?? [],
                'trial_days' => $plan['trial_days'] ?? 0,
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to get Paddle product: ' . $e->getMessage());
        }
    }
}
