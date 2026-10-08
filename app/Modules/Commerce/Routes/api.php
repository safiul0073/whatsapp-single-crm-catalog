<?php

use App\Modules\Commerce\Http\Controllers\Api\MenuApiController;
use App\Modules\Commerce\Http\Controllers\Api\OrderApiController;
use App\Modules\Commerce\Http\Controllers\Api\ProductApiController;
use App\Modules\Commerce\Http\Controllers\Api\ProductShowcaseController;
use App\Modules\Commerce\Http\Controllers\Api\StoreOrderController;
use App\Modules\Commerce\Http\Controllers\Api\WhatsAppCustomerAuthController;
use App\Modules\Commerce\Http\Middleware\ResolveStoreWorkspace;
use Illuminate\Support\Facades\Route;

Route::get('commerce/menu', [MenuApiController::class, 'index'])
    ->middleware('throttle:60,1')->name('commerce.api.menu');
Route::get('commerce/filters', [ProductApiController::class, 'filters'])->name('commerce.api.filters');
Route::get('commerce/products', [ProductApiController::class, 'index'])->name('commerce.api.products.index');
Route::get('commerce/products/deals', [ProductApiController::class, 'deals'])->name('commerce.api.products.deals');
Route::get('commerce/products/{product}', [ProductApiController::class, 'show'])->name('commerce.api.products.show');

Route::get('commerce/track/{trackingNumber}', [OrderApiController::class, 'trackOrder'])
    ->middleware('throttle:60,1')->name('commerce.api.orders.track');

Route::prefix('commerce/store')->middleware([ResolveStoreWorkspace::class, 'throttle:120,1'])->group(function (): void {
    Route::get('showcase/{slug}', [ProductShowcaseController::class, 'show']);
    Route::post('showcase/{slug}/estimate', [ProductShowcaseController::class, 'estimate']);
    Route::get('showcase/{slug}/stock-quote', [ProductShowcaseController::class, 'stockQuote']);
    $controller = StoreOrderController::class;
    Route::get('settings', [$controller, 'settings']);
    Route::post('orders/preview', [$controller, 'preview']);
    Route::post('orders', [$controller, 'store']);
    Route::get('orders', [$controller, 'history']);
    Route::get('orders/{reference}', [$controller, 'show']);
    Route::post('orders/{reference}/cancel', [$controller, 'cancel']);
    Route::post('orders/{reference}/evidence', [$controller, 'evidence']);
    $auth = WhatsAppCustomerAuthController::class;
    Route::get('auth/whatsapp/status', [$auth, 'status']);
    Route::post('auth/whatsapp/challenges', [$auth, 'challenge']);
    Route::post('auth/whatsapp/challenges/{challenge}/verify', [$auth, 'verify']);
    Route::post('auth/whatsapp/registrations', [$auth, 'register']);
});
