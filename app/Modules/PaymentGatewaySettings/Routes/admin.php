<?php

use App\Modules\PaymentGatewaySettings\Http\Controllers\Admin\PaymentGatewaySettingsController;
use Illuminate\Support\Facades\Route;

Route::get('payment-gateway-settings', [PaymentGatewaySettingsController::class, 'index'])->name('payment-gateway-settings.index');
Route::put('payment-gateway-settings', [PaymentGatewaySettingsController::class, 'update'])->name('payment-gateway-settings.update');
