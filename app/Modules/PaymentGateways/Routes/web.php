<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/payments/{gateway}', [WebhookController::class, 'handle'])
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('webhooks.payments.legacy');

Route::post('webhooks/{gateway}', [WebhookController::class, 'handle'])
    ->whereIn('gateway', ['stripe', 'paypal', 'razorpay', 'sslcommerz', 'paystack', 'flutterwave', 'manual', 'log'])
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('webhooks.payments');
