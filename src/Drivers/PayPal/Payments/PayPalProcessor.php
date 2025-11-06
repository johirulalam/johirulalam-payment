<?php

namespace Sayed\Payment\Drivers\PayPal\Payments;

use Sayed\Payment\Services\Payments\PaymentProcessor;
use Sayed\Payment\DTOs\CheckoutDTO;
use Sayed\Payment\DTOs\RefundDTO;
use Sayed\Payment\DTOs\ProductDTO;
use Sayed\Payment\DTOs\RecurringProductDTO;
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

    /**
     * Create a product (one-time payment)
     */
    public function createProduct(array $data): ProductDTO
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = $this->client->post('/v1/catalogs/products', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'name' => $data['name'],
                    'description' => $data['description'] ?? '',
                    'type' => 'DIGITAL', // or PHYSICAL
                    'category' => $data['category'] ?? 'SOFTWARE',
                ],
            ]);

            $product = json_decode($response->getBody(), true);

            return new ProductDTO(
                productId: $product['id'],
                name: $product['name'],
                amount: $data['amount'] ?? 0,
                currency: $data['currency'] ?? 'USD',
                type: 'one_time',
                priceId: null,
                description: $product['description'] ?? null,
                metadata: []
            );
        } catch (Exception $e) {
            throw new Exception('Failed to create PayPal product: ' . $e->getMessage());
        }
    }

    /**
     * Create a recurring product (subscription plan)
     */
    public function createRecurringProduct(array $data): RecurringProductDTO
    {
        try {
            $accessToken = $this->getAccessToken();

            // First create a product
            $productResponse = $this->client->post('/v1/catalogs/products', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'name' => $data['name'],
                    'description' => $data['description'] ?? '',
                    'type' => 'SERVICE',
                    'category' => 'SOFTWARE',
                ],
            ]);

            $product = json_decode($productResponse->getBody(), true);

            // Then create a billing plan
            $planResponse = $this->client->post('/v1/billing/plans', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'product_id' => $product['id'],
                    'name' => $data['name'],
                    'description' => $data['description'] ?? '',
                    'billing_cycles' => [
                        [
                            'frequency' => [
                                'interval_unit' => strtoupper($data['interval'] ?? 'MONTH'),
                                'interval_count' => $data['interval_count'] ?? 1,
                            ],
                            'tenure_type' => 'REGULAR',
                            'sequence' => 1,
                            'total_cycles' => 0, // Infinite
                            'pricing_scheme' => [
                                'fixed_price' => [
                                    'value' => number_format($data['amount'] / 100, 2, '.', ''),
                                    'currency_code' => strtoupper($data['currency'] ?? 'USD'),
                                ],
                            ],
                        ],
                    ],
                    'payment_preferences' => [
                        'auto_bill_outstanding' => true,
                        'setup_fee' => [
                            'value' => '0',
                            'currency_code' => strtoupper($data['currency'] ?? 'USD'),
                        ],
                        'setup_fee_failure_action' => 'CONTINUE',
                        'payment_failure_threshold' => 3,
                    ],
                ],
            ]);

            $plan = json_decode($planResponse->getBody(), true);

            return new RecurringProductDTO(
                productId: $product['id'],
                name: $plan['name'],
                amount: $data['amount'],
                currency: strtoupper($data['currency'] ?? 'USD'),
                interval: strtolower($data['interval'] ?? 'month'),
                intervalCount: $data['interval_count'] ?? 1,
                type: 'recurring',
                priceId: null,
                planId: $plan['id'],
                description: $plan['description'] ?? null,
                trialDays: null,
                metadata: []
            );
        } catch (Exception $e) {
            throw new Exception('Failed to create PayPal subscription plan: ' . $e->getMessage());
        }
    }

    /**
     * List all products
     */
    public function listProducts(int $limit = 10): array
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = $this->client->get('/v1/catalogs/products', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'query' => [
                    'page_size' => $limit,
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            return array_map(function ($product) {
                return [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'description' => $product['description'] ?? '',
                    'type' => $product['type'] ?? 'DIGITAL',
                ];
            }, $data['products'] ?? []);
        } catch (Exception $e) {
            throw new Exception('Failed to list PayPal products: ' . $e->getMessage());
        }
    }

    /**
     * Get product details
     */
    public function getProduct(string $productId): array
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = $this->client->get("/v1/catalogs/products/{$productId}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);

            $product = json_decode($response->getBody(), true);

            return [
                'id' => $product['id'],
                'name' => $product['name'],
                'description' => $product['description'] ?? '',
                'type' => $product['type'] ?? 'DIGITAL',
                'category' => $product['category'] ?? '',
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to get PayPal product: ' . $e->getMessage());
        }
    }
}
