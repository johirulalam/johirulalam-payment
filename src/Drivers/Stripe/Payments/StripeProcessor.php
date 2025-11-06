<?php

namespace Sayed\Payment\Drivers\Stripe\Payments;

use Sayed\Payment\Services\Payments\PaymentProcessor;
use Sayed\Payment\DTOs\CheckoutDTO;
use Sayed\Payment\DTOs\RefundDTO;
use Sayed\Payment\DTOs\InvoicePaymentDTO;
use Sayed\Payment\DTOs\ProductDTO;
use Sayed\Payment\DTOs\RecurringProductDTO;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\InvoiceItem;
use Stripe\Invoice;
use Stripe\PaymentIntent;
use Stripe\Product;
use Stripe\Price;
use Exception;
use Stripe\StripeClient;

class StripeProcessor extends PaymentProcessor
{
    protected $stripe;

    public function __construct()
    {
        $secretKey = config('payment.providers.stripe.secret_key');
        Stripe::setApiKey($secretKey);
        $this->stripe = new StripeClient($secretKey);
    }

    public function checkout(array $payload): array
    {
        try {
            // Validate and create DTO
            $dto = CheckoutDTO::fromArray($payload);

            $lineItems = [];
            if ($dto->products) {
                foreach ($dto->products as $product) {
                    // If product has an id (like Stripe Price ID), use it directly
                    if (!empty($product['id'])) {
                        $lineItems[] = [
                            'price' => $product['id'],
                            'quantity' => $product['quantity'] ?? 1,
                        ];
                    } else {
                        // Otherwise, create price_data with title and amount
                        $priceData = [
                            'currency' => $dto->currency,
                            'product_data' => [
                                'name' => $product['title'],
                            ],
                            'unit_amount' => $product['amount'],
                        ];
                        
                        // Add recurring interval for subscription
                        if ($dto->isSubscription) {
                            $priceData['recurring'] = [
                                'interval' => $dto->interval,
                            ];
                        }
                        
                        $lineItems[] = [
                            'price_data' => $priceData,
                            'quantity' => $product['quantity'] ?? 1,
                        ];
                    }
                }
            }

            $session = Session::create([
                'line_items' => $lineItems,
                'allow_promotion_codes' => $dto->isAllowPromotionCode,
                'metadata' => $dto->metadata,
                'mode' => $dto->isSubscription ? 'subscription' : 'payment',
                'success_url' => $dto->successUrl,
                'cancel_url' => $dto->cancelUrl,
            ]);

            return [
                'paymentLinkUrl' => $session->url,
                'session_id' => $session->id,
            ];
        } catch (Exception $e) {
            throw new Exception('Payment processing failed: ' . $e->getMessage());
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

            $refund = \Stripe\Refund::create([
                'charge' => $dto->transactionId,
                'amount' => $dto->getAmountInCents(),
            ]);

            return [
                'success' => true,
                'refund_id' => $refund->id,
                'status' => $refund->status,
            ];
        } catch (Exception $e) {
            throw new Exception('Refund failed: ' . $e->getMessage());
        }
    }

    /**
     * Create and pay invoice with payment method
     * 
     * @param array $payload
     * @return array
     * @throws Exception
     */
    public function payWithInvoice(array $payload)
    {
        try {

            // Validate and create DTO
            $dto = InvoicePaymentDTO::fromArray($payload);


            $invoice = Invoice::create([
                'customer' => $dto->customerId,
                'currency' => $dto->currency,
                'metadata' => $dto->metadata,
            ]);

            // Create invoice items - either from provided items or default single item
            foreach ($dto->items as $item) {
                $invoiceItemData = [
                    'customer' => $dto->customerId,
                    'invoice' => $invoice->id,
                    'description' => $dto->description,
                ];

                // Use price_id if available, otherwise use amount
                if (!empty($item['id'])) {
                    $invoiceItemData['price'] = $item['id'];
                    if (!empty($item['quantity'])) {
                        $invoiceItemData['quantity'] = $item['quantity'];
                    }
                } else {
                    $invoiceItemData['amount'] = $item['amount'];
                }

                InvoiceItem::create($invoiceItemData);
            }

            $paymentData = $dto->paymentMethodId
                ? [
                    'off_session' => true,
                    'payment_method' => $dto->paymentMethodId,
                ]
                : [];

            return $this->stripe->invoices->pay($invoice->id, $paymentData);

        } catch (Exception $e) {
            throw new Exception('Invoice payment failed: ' . $e->getMessage());
        }
    }

    /**
     * Create customer if not exists
     * 
     * @param string $email
     * @param string|null $name
     * @param array $metadata
     * @return array
     * @throws Exception
     */
    public function createCustomer(string $email, ?string $name = null, array $metadata = []): array
    {
        try {
            $customerData = [
                'email' => $email,
                'metadata' => $metadata,
            ];

            if ($name) {
                $customerData['name'] = $name;
            }

            $customer = Customer::create($customerData);

            return [
                'customer_id' => $customer->id,
                'email' => $customer->email,
                'name' => $customer->name,
            ];
        } catch (Exception $e) {
            throw new Exception('Customer creation failed: ' . $e->getMessage());
        }
    }

    /**
     * Attach payment method to customer
     * 
     * @param string $paymentMethodId
     * @param string $customerId
     * @return array
     * @throws Exception
     */
    public function attachPaymentMethod(string $paymentMethodId, string $customerId): array
    {
        try {
            $paymentMethod = \Stripe\PaymentMethod::retrieve($paymentMethodId);
            $paymentMethod->attach(['customer' => $customerId]);

            // Set as default payment method
            Customer::update($customerId, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId,
                ],
            ]);

            return [
                'success' => true,
                'payment_method_id' => $paymentMethod->id,
                'type' => $paymentMethod->type,
                'customer_id' => $customerId,
            ];
        } catch (Exception $e) {
            throw new Exception('Payment method attachment failed: ' . $e->getMessage());
        }
    }

    /**
     * Get invoice details
     * 
     * @param string $invoiceId
     * @return array
     * @throws Exception
     */
    public function getInvoice(string $invoiceId): array
    {
        try {
            $invoice = Invoice::retrieve($invoiceId);

            return [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->number,
                'status' => $invoice->status,
                'amount_due' => $invoice->amount_due / 100,
                'amount_paid' => $invoice->amount_paid / 100,
                'amount_remaining' => $invoice->amount_remaining / 100,
                'currency' => $invoice->currency,
                'customer_id' => $invoice->customer,
                'invoice_pdf' => $invoice->invoice_pdf,
                'hosted_invoice_url' => $invoice->hosted_invoice_url,
                'created' => $invoice->created,
                'due_date' => $invoice->due_date,
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to retrieve invoice: ' . $e->getMessage());
        }
    }

    /**
     * Create a product with one-time payment price
     */
    public function createProduct(array $data): ProductDTO
    {
        try {
            // Create product
            $product = Product::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'metadata' => $data['metadata'] ?? [],
            ]);

            // Create one-time price
            $price = Price::create([
                'product' => $product->id,
                'unit_amount' => $data['amount'], // in cents
                'currency' => $data['currency'] ?? 'usd',
                'metadata' => $data['price_metadata'] ?? [],
            ]);

            return new ProductDTO(
                productId: $product->id,
                name: $product->name,
                amount: $price->unit_amount,
                currency: $price->currency,
                type: 'one_time',
                priceId: $price->id,
                description: $product->description,
                metadata: $product->metadata->toArray()
            );
        } catch (Exception $e) {
            throw new Exception('Failed to create product: ' . $e->getMessage());
        }
    }

    /**
     * Create a product with recurring (subscription) price
     */
    public function createRecurringProduct(array $data): RecurringProductDTO
    {
        try {
            // Create product
            $product = Product::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'metadata' => $data['metadata'] ?? [],
            ]);

            // Create recurring price
            $price = Price::create([
                'product' => $product->id,
                'unit_amount' => $data['amount'], // in cents
                'currency' => $data['currency'] ?? 'usd',
                'recurring' => [
                    'interval' => $data['interval'] ?? 'month', // day, week, month, year
                    'interval_count' => $data['interval_count'] ?? 1,
                ],
                'metadata' => $data['price_metadata'] ?? [],
            ]);

            return new RecurringProductDTO(
                productId: $product->id,
                name: $product->name,
                amount: $price->unit_amount,
                currency: $price->currency,
                interval: $price->recurring->interval,
                intervalCount: $price->recurring->interval_count,
                type: 'recurring',
                priceId: $price->id,
                planId: null,
                description: $product->description,
                trialDays: null,
                metadata: $product->metadata->toArray()
            );
        } catch (Exception $e) {
            throw new Exception('Failed to create recurring product: ' . $e->getMessage());
        }
    }

    /**
     * List all products
     */
    public function listProducts(int $limit = 10): array
    {
        try {
            $products = Product::all(['limit' => $limit]);

            return array_map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'active' => $product->active,
                    'created' => $product->created,
                ];
            }, $products->data);
        } catch (Exception $e) {
            throw new Exception('Failed to list products: ' . $e->getMessage());
        }
    }

    /**
     * Get product with prices
     */
    public function getProduct(string $productId): array
    {
        try {
            $product = Product::retrieve($productId);
            $prices = Price::all(['product' => $productId]);

            return [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'active' => $product->active,
                'prices' => array_map(function ($price) {
                    $priceData = [
                        'id' => $price->id,
                        'amount' => $price->unit_amount,
                        'currency' => $price->currency,
                        'type' => $price->type,
                    ];

                    if ($price->type === 'recurring') {
                        $priceData['interval'] = $price->recurring->interval;
                        $priceData['interval_count'] = $price->recurring->interval_count;
                    }

                    return $priceData;
                }, $prices->data),
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to get product: ' . $e->getMessage());
        }
    }
}
