<?php

use App\Modules\Commerce\Http\Controllers\PublicCommerceController;
use Illuminate\Support\Facades\Route;

Route::get('commerce/catalog/{token}/feed.csv', [PublicCommerceController::class, 'feed'])->name('commerce.catalog.feed');
Route::get('shop', [PublicCommerceController::class, 'directory'])->name('commerce.shops.directory');
Route::get('shop/products', [PublicCommerceController::class, 'products'])->name('commerce.products.shop');
Route::get('shop/products/{product}', [PublicCommerceController::class, 'legacyProduct'])->name('commerce.products.legacy');
Route::get('products/{product}', [PublicCommerceController::class, 'directProduct'])->name('commerce.products.direct');
Route::get('shop/{workspace:slug}', [PublicCommerceController::class, 'index'])->name('commerce.products.index');
Route::get('shop/{workspace:slug}/products/{product:slug}', [PublicCommerceController::class, 'product'])->name('commerce.products.public');
