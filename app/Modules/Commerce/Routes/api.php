<?php

use App\Modules\Commerce\Http\Controllers\Api\ProductApiController;
use Illuminate\Support\Facades\Route;

Route::get('commerce/filters', [ProductApiController::class, 'filters'])->name('commerce.api.filters');
Route::get('commerce/products', [ProductApiController::class, 'index'])->name('commerce.api.products.index');
Route::get('commerce/products/deals', [ProductApiController::class, 'deals'])->name('commerce.api.products.deals');
Route::get('commerce/products/{product}', [ProductApiController::class, 'show'])->name('commerce.api.products.show');

use App\Modules\Commerce\Http\Controllers\Api\OrderApiController;
Route::get('commerce/track/{trackingNumber}', [OrderApiController::class, 'trackOrder'])->name('commerce.api.orders.track');
