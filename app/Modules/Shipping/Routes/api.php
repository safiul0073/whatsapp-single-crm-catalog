<?php

use App\Modules\Shipping\Http\Controllers\Api\ShippingApiController;
use Illuminate\Support\Facades\Route;

Route::post('shipping/calculate-rates', [ShippingApiController::class, 'calculateRates'])->name('commerce.shipping.rates');
Route::post('shipping/quote', [ShippingApiController::class, 'quote'])->name('commerce.shipping.quote');
