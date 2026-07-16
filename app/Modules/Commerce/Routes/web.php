<?php

use App\Modules\Commerce\Http\Controllers\PublicCommerceController;
use Illuminate\Support\Facades\Route;

Route::get('commerce/catalog/{token}/feed.csv', [PublicCommerceController::class, 'feed'])->name('commerce.catalog.feed');
Route::get('shop/products/{product}', [PublicCommerceController::class, 'product'])->name('commerce.products.public');
