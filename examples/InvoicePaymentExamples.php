<?php

require_once __DIR__ . '/vendor/autoload.php';

use Sayed\Payment\Facades\Payment;

/**
 * Example: Stripe Invoice Payment with Payment Method ID
 * 
 * This demonstrates how to:
 * 1. Create a customer
 * 2. Attach a payment method to the customer
 * 3. Create an invoice with items
 * 4. Pay the invoice using the payment method
 */

echo "=== Stripe Invoice Payment Example ===\n\n";

try {
    // Step 1: Create a customer
    echo "1. Creating customer...\n";
    $customer = Payment::driver('stripe')->createCustomer(
        email: 'customer@example.com',
        name: 'John Doe',
        metadata: ['user_id' => '123']
    );
    echo "   Customer created: {$customer['customer_id']}\n\n";

    // Step 2: Attach payment method to customer
    echo "2. Attaching payment method...\n";
    // Payment method ID would come from Stripe.js on frontend
    $paymentMethodId = 'pm_card_visa'; // Test payment method
    
    $attachResult = Payment::driver('stripe')->attachPaymentMethod(
        paymentMethodId: $paymentMethodId,
        customerId: $customer['customer_id']
    );
    echo "   Payment method attached: {$attachResult['payment_method_id']}\n\n";

    // Step 3: Create and pay invoice
    echo "3. Creating and paying invoice...\n";
    $invoiceResult = Payment::driver('stripe')->payWithInvoice([
        'customer_id' => $customer['customer_id'],
        'payment_method_id' => $paymentMethodId,
        'currency' => 'usd',
        'description' => 'Monthly subscription invoice',
        'items' => [
            [
                'description' => 'Pro Plan - Monthly',
                'amount' => 2999, // $29.99 in cents
                'quantity' => 1,
            ],
            [
                'description' => 'Additional User Seat',
                'amount' => 999, // $9.99 in cents
                'quantity' => 2,
            ],
        ],
        'metadata' => [
            'order_id' => 'ORD-12345',
            'subscription_id' => 'SUB-67890',
        ],
    ]);

    echo "   ✓ Invoice created and paid!\n";
    echo "   Invoice ID: {$invoiceResult['invoice_id']}\n";
    echo "   Invoice Number: {$invoiceResult['invoice_number']}\n";
    echo "   Status: {$invoiceResult['status']}\n";
    echo "   Amount Paid: \${$invoiceResult['amount_paid']}\n";
    echo "   Currency: {$invoiceResult['currency']}\n";
    echo "   PDF: {$invoiceResult['invoice_pdf']}\n";
    echo "   Hosted URL: {$invoiceResult['hosted_invoice_url']}\n\n";

    // Step 4: Get invoice details
    echo "4. Retrieving invoice details...\n";
    $invoice = Payment::driver('stripe')->getInvoice($invoiceResult['invoice_id']);
    echo "   Invoice Status: {$invoice['status']}\n";
    echo "   Amount Due: \${$invoice['amount_due']}\n";
    echo "   Amount Remaining: \${$invoice['amount_remaining']}\n\n";

    echo "=== Success! ===\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

/**
 * Additional Usage Examples
 */

// Example 1: Simple single-item invoice
function singleItemInvoice($customerId, $paymentMethodId)
{
    return Payment::driver('stripe')->payWithInvoice([
        'customer_id' => $customerId,
        'payment_method_id' => $paymentMethodId,
        'currency' => 'usd',
        'items' => [
            [
                'description' => 'One-time service fee',
                'amount' => 5000, // $50.00
                'quantity' => 1,
            ],
        ],
    ]);
}

// Example 2: Invoice with due date (send now, charge later)
function deferredPaymentInvoice($customerId, $paymentMethodId)
{
    return Payment::driver('stripe')->payWithInvoice([
        'customer_id' => $customerId,
        'payment_method_id' => $paymentMethodId,
        'currency' => 'usd',
        'days_until_due' => 30, // Due in 30 days
        'auto_advance' => false, // Don't auto-charge
        'items' => [
            [
                'description' => 'Consulting services',
                'amount' => 15000, // $150.00
                'quantity' => 10, // 10 hours
            ],
        ],
    ]);
}

// Example 3: Multiple items invoice
function multipleItemsInvoice($customerId, $paymentMethodId)
{
    return Payment::driver('stripe')->payWithInvoice([
        'customer_id' => $customerId,
        'payment_method_id' => $paymentMethodId,
        'currency' => 'usd',
        'description' => 'Q4 2024 Services',
        'items' => [
            [
                'description' => 'Website Development',
                'amount' => 50000, // $500.00
                'quantity' => 1,
            ],
            [
                'description' => 'Logo Design',
                'amount' => 20000, // $200.00
                'quantity' => 1,
            ],
            [
                'description' => 'SEO Optimization',
                'amount' => 30000, // $300.00
                'quantity' => 1,
            ],
        ],
        'metadata' => [
            'project_id' => 'PRJ-2024-Q4',
        ],
    ]);
}

// Example 4: Create customer with payment method in one flow
function createCustomerAndPayInvoice($email, $name, $paymentMethodId)
{
    try {
        // Create customer
        $customer = Payment::driver('stripe')->createCustomer($email, $name);
        
        // Attach payment method
        Payment::driver('stripe')->attachPaymentMethod(
            $paymentMethodId,
            $customer['customer_id']
        );
        
        // Create and pay invoice
        return Payment::driver('stripe')->payWithInvoice([
            'customer_id' => $customer['customer_id'],
            'payment_method_id' => $paymentMethodId,
            'currency' => 'usd',
            'items' => [
                [
                    'description' => 'Initial setup fee',
                    'amount' => 10000, // $100.00
                    'quantity' => 1,
                ],
            ],
        ]);
    } catch (Exception $e) {
        throw new Exception("Failed to process payment: " . $e->getMessage());
    }
}
