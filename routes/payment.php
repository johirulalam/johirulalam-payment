<?php

use Illuminate\Support\Facades\Route;
use Sayed\Payment\Http\Controllers\PaymentController;
use Sayed\Payment\Http\Controllers\WebhookController;

Route::prefix('api/payment')->group(function () {
    Route::post('/checkout/{provider}', [PaymentController::class, 'checkout'])
        ->name('payment.checkout');
    
    Route::post('/refund/{provider}', [PaymentController::class, 'refund'])
        ->name('payment.refund');
});

Route::prefix('api/payments/webhooks')->group(function () {
    Route::post('/handle', [WebhookController::class, 'handle'])
        ->name('payment.webhook.handle');
});
