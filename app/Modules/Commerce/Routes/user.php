<?php

use App\Modules\Commerce\Http\Controllers\User\CommerceController;
use Illuminate\Support\Facades\Route;

Route::prefix('commerce')->name('commerce.')->group(function (): void {
    Route::get('/', [CommerceController::class, 'index'])->name('products.index');
    Route::get('products/create', [CommerceController::class, 'create'])->name('products.create');
    Route::post('products', [CommerceController::class, 'store'])->name('products.store');
    Route::get('products/{product}/edit', [CommerceController::class, 'edit'])->name('products.edit');
    Route::put('products/{product}', [CommerceController::class, 'update'])->name('products.update');
    Route::put('products/{product}/details', [CommerceController::class, 'updateDetails'])->name('products.details.update');
    Route::put('products/{product}/gallery', [CommerceController::class, 'updateGallery'])->name('products.gallery.update');
    Route::put('products/{product}/options', [CommerceController::class, 'updateOptions'])->name('products.options.update');
    Route::get('products/{product}/variants/preview', [CommerceController::class, 'previewVariants'])->name('products.variants.preview');
    Route::put('products/{product}/variants', [CommerceController::class, 'updateVariants'])->name('products.variants.update');
    Route::put('products/{product}/publish', [CommerceController::class, 'publish'])->name('products.publish');
    Route::post('products/media', [CommerceController::class, 'uploadMedia'])->name('products.media.upload');
    Route::delete('products/{product}', [CommerceController::class, 'destroy'])->name('products.destroy');
    Route::get('categories', [CommerceController::class, 'categories'])->name('categories.index');
    Route::post('categories', [CommerceController::class, 'storeCategory'])->name('categories.store');
    Route::put('categories/{category}', [CommerceController::class, 'updateCategory'])->name('categories.update');
    Route::delete('categories/{category}', [CommerceController::class, 'destroyCategory'])->name('categories.destroy');
    Route::get('brands', [CommerceController::class, 'brands'])->name('brands.index');
    Route::post('brands', [CommerceController::class, 'storeBrand'])->name('brands.store');
    Route::put('brands/{brand}', [CommerceController::class, 'updateBrand'])->name('brands.update');
    Route::delete('brands/{brand}', [CommerceController::class, 'destroyBrand'])->name('brands.destroy');
    Route::get('audiences', [CommerceController::class, 'audiences'])->name('audiences.index');
    Route::post('audiences', [CommerceController::class, 'storeAudience'])->name('audiences.store');
    Route::put('audiences/{audience}', [CommerceController::class, 'updateAudience'])->name('audiences.update');
    Route::delete('audiences/{audience}', [CommerceController::class, 'destroyAudience'])->name('audiences.destroy');
    Route::get('catalog', [CommerceController::class, 'catalog'])->name('catalog');
    Route::post('catalog', [CommerceController::class, 'storeCatalog'])->name('catalog.store');
    Route::post('catalog/{catalog}/token', [CommerceController::class, 'rotateFeedToken'])->name('catalog.token.rotate');
    Route::post('catalog/{catalog}/sync', [CommerceController::class, 'syncCatalog'])->name('catalog.sync');
    Route::post('catalog/{catalog}/commerce-settings', [CommerceController::class, 'updateCommerceSettings'])->name('catalog.commerce-settings');
    Route::get('orders', [CommerceController::class, 'orders'])->name('orders.index');
    Route::get('orders/{order}', [CommerceController::class, 'order'])->name('orders.show');
    Route::put('orders/{order}/quote', [CommerceController::class, 'quote'])->name('orders.quote');
    Route::put('orders/{order}/status', [CommerceController::class, 'transition'])->name('orders.transition');
    Route::post('conversations/{conversation}/catalog', [CommerceController::class, 'sendCatalog'])->name('conversations.catalog');
    Route::get('conversations/{conversation}/products', [CommerceController::class, 'conversationProducts'])->name('conversations.products');
    Route::post('conversations/{conversation}/product', [CommerceController::class, 'sendProduct'])->name('conversations.product');
    Route::post('conversations/{conversation}/product-list', [CommerceController::class, 'sendProductList'])->name('conversations.product-list');
    Route::post('conversations/{conversation}/product-video', [CommerceController::class, 'sendProductVideo'])->name('conversations.product-video');
});
