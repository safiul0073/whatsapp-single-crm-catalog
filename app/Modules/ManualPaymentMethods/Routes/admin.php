<?php

use App\Modules\ManualPaymentMethods\Http\Controllers\Admin\ManualPaymentMethodsController;
use Illuminate\Support\Facades\Route;

Route::post('manual-payment-methods', [ManualPaymentMethodsController::class, 'store'])->name('manual-payment-methods.store');
Route::delete('manual-payment-methods/{manualPaymentMethod}', [ManualPaymentMethodsController::class, 'destroy'])->name('manual-payment-methods.destroy');
