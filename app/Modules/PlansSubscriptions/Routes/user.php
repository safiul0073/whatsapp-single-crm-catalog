<?php

use App\Modules\PlansSubscriptions\Http\Controllers\User\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:subscription.manage'])->group(function () {
    Route::get('subscription', [SubscriptionController::class, 'show'])->name('subscription.show');
    Route::post('subscription/checkout', [SubscriptionController::class, 'initiateCheckout'])->name('subscription.checkout.initiate');
    Route::get('subscription/checkout/{payment:uuid}', [SubscriptionController::class, 'checkoutPage'])->name('subscription.checkout.page');
    Route::post('subscription/checkout/{payment:uuid}/pay', [SubscriptionController::class, 'pay'])->name('subscription.checkout.pay');
    Route::get('subscription/payment/return', [SubscriptionController::class, 'paymentReturn'])->name('subscription.payment.return');
    Route::get('subscription/payment/cancel', [SubscriptionController::class, 'paymentCancel'])->name('subscription.payment.cancel');
});
