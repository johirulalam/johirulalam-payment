<?php

namespace Sayed\Payment\Drivers\Stripe\Payments;

use Sayed\Payment\Services\Payments\PaymentProcessor;
use Sayed\Payment\DTOs\CheckoutDTO;
use Sayed\Payment\DTOs\RefundDTO;
use Sayed\Payment\DTOs\InvoicePaymentDTO;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\InvoiceItem;
use Stripe\Invoice;
use Stripe\PaymentIntent;
use Exception;

class StripeProcessor extends PaymentProcessor
{
    protected $stripe;

    public function __construct()
    {
        $secretKey = config('payment.providers.stripe.secret_key');
        Stripe::setApiKey($secretKey);
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
    public function payWithInvoice(array $payload): array
    {
        try {
            // Validate and create DTO
            $dto = InvoicePaymentDTO::fromArray($payload);

            // Create invoice items
            $invoiceItemIds = [];
            foreach ($dto->items as $item) {
                $invoiceItem = InvoiceItem::create([
                    'customer' => $dto->customerId,
                    'amount' => $item['amount'],
                    'currency' => $dto->currency,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'] ?? 1,
                ]);
                $invoiceItemIds[] = $invoiceItem->id;
            }

            // Create invoice
            $invoiceData = [
                'customer' => $dto->customerId,
                'auto_advance' => $dto->autoAdvance,
                'collection_method' => 'charge_automatically',
                'metadata' => $dto->metadata,
            ];

            if ($dto->description) {
                $invoiceData['description'] = $dto->description;
            }

            if ($dto->daysUntilDue) {
                $invoiceData['days_until_due'] = $dto->daysUntilDue;
            }

            $invoice = Invoice::create($invoiceData);

            // Finalize invoice to make it payable
            $invoice->finalizeInvoice();

            // Pay invoice with payment method
            $paymentIntent = $invoice->pay([
                'payment_method' => $dto->paymentMethodId,
            ]);

            return [
                'success' => true,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->number,
                'invoice_pdf' => $invoice->invoice_pdf,
                'hosted_invoice_url' => $invoice->hosted_invoice_url,
                'payment_intent_id' => $invoice->payment_intent,
                'status' => $invoice->status,
                'amount_paid' => $invoice->amount_paid / 100,
                'amount_due' => $invoice->amount_due / 100,
                'currency' => $invoice->currency,
            ];
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
}
