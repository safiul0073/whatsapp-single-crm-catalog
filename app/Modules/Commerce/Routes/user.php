<?php

use App\Modules\Commerce\Http\Controllers\User\CommerceController;
use App\Modules\Commerce\Http\Controllers\User\OrderManagementController;
use App\Modules\Commerce\Http\Controllers\User\PosController;
use App\Modules\Commerce\Storefront\Http\Controllers\AdminController;
use App\Modules\Commerce\Storefront\Http\Controllers\PackTemplateAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('commerce')->name('commerce.')->group(function (): void {
    Route::middleware('permission:commerce.manage')->group(function (): void {
        Route::get('pack-templates', [PackTemplateAdminController::class, 'index'])->name('pack-templates.index');
        Route::post('pack-templates', [PackTemplateAdminController::class, 'save'])->name('pack-templates.store');
        Route::put('pack-templates/{id}', [PackTemplateAdminController::class, 'save'])->name('pack-templates.update');
        Route::delete('pack-templates/{id}', [PackTemplateAdminController::class, 'destroy'])->name('pack-templates.destroy');
    });
    Route::prefix('pos')->name('pos.')->group(function (): void {
        Route::middleware('permission:commerce.manage')->group(function (): void {
            Route::get('/', [PosController::class, 'index'])->name('index');
            Route::get('products', [PosController::class, 'products'])->name('products');
            Route::post('preview', [PosController::class, 'preview'])->name('preview');
            Route::post('checkout', [PosController::class, 'checkout'])->name('checkout');
            Route::post('orders/{order}/payments', [PosController::class, 'payment'])->name('payment');
            Route::post('orders/{order}/pickup', [PosController::class, 'pickup'])->name('pickup');
        });
        Route::get('orders/{order}/receipt', [PosController::class, 'receipt'])->middleware('permission:commerce.view')->name('receipt');
        Route::get('customers/{contact}/balance', [PosController::class, 'customerBalance'])->middleware('permission:commerce.view')->name('customer-balance');
    });

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
    Route::post('products/{product}/catalog', [CommerceController::class, 'syncProductCatalog'])->name('products.catalog.sync');
    Route::put('products/{product}/publish', [CommerceController::class, 'publish'])->name('products.publish');
    Route::post('products/media', [CommerceController::class, 'uploadMedia'])->name('products.media.upload');
    Route::delete('products/bulk', [CommerceController::class, 'bulkDestroyProducts'])->name('products.bulk-destroy');
    Route::delete('products/{product}', [CommerceController::class, 'destroy'])->name('products.destroy');
    Route::get('categories', [CommerceController::class, 'categories'])->name('categories.index');
    Route::post('categories', [CommerceController::class, 'storeCategory'])->name('categories.store');
    Route::put('categories/{category}', [CommerceController::class, 'updateCategory'])->name('categories.update');
    Route::delete('categories/bulk', [CommerceController::class, 'bulkDestroyCategories'])->name('categories.bulk-destroy');
    Route::delete('categories/{category}', [CommerceController::class, 'destroyCategory'])->name('categories.destroy');
    Route::get('brands', [CommerceController::class, 'brands'])->name('brands.index');
    Route::post('brands', [CommerceController::class, 'storeBrand'])->name('brands.store');
    Route::put('brands/{brand}', [CommerceController::class, 'updateBrand'])->name('brands.update');
    Route::delete('brands/bulk', [CommerceController::class, 'bulkDestroyBrands'])->name('brands.bulk-destroy');
    Route::delete('brands/{brand}', [CommerceController::class, 'destroyBrand'])->name('brands.destroy');
    Route::get('audiences', [CommerceController::class, 'audiences'])->name('audiences.index');
    Route::post('audiences', [CommerceController::class, 'storeAudience'])->name('audiences.store');
    Route::put('audiences/{audience}', [CommerceController::class, 'updateAudience'])->name('audiences.update');
    Route::delete('audiences/bulk', [CommerceController::class, 'bulkDestroyAudiences'])->name('audiences.bulk-destroy');
    Route::delete('audiences/{audience}', [CommerceController::class, 'destroyAudience'])->name('audiences.destroy');
    Route::get('variants', [CommerceController::class, 'variants'])->name('variants.index');
    Route::post('variants', [CommerceController::class, 'storeVariantPreset'])->name('variants.store');
    Route::put('variants/{preset}', [CommerceController::class, 'updateVariantPreset'])->name('variants.update');
    Route::delete('variants/bulk', [CommerceController::class, 'bulkDestroyVariantPresets'])->name('variants.bulk-destroy');
    Route::delete('variants/{preset}', [CommerceController::class, 'destroyVariantPreset'])->name('variants.destroy');
    Route::get('catalog', [CommerceController::class, 'catalog'])->name('catalog');
    Route::post('catalog', [CommerceController::class, 'storeCatalog'])->name('catalog.store');
    Route::post('catalog/{catalog}/token', [CommerceController::class, 'rotateFeedToken'])->name('catalog.token.rotate');
    Route::post('catalog/{catalog}/sync', [CommerceController::class, 'syncCatalog'])->name('catalog.sync');
    Route::post('catalog/{catalog}/verify-access', [CommerceController::class, 'verifyCatalogAccess'])->name('catalog.verify-access');
    Route::post('catalog/{catalog}/commerce-settings', [CommerceController::class, 'updateCommerceSettings'])->name('catalog.commerce-settings');
    Route::get('orders', [CommerceController::class, 'orders'])->name('orders.index');
    Route::get('customers', [CommerceController::class, 'customers'])->name('customers.index');
    Route::get('customers/{customer}', [CommerceController::class, 'customer'])->name('customers.show');
    Route::middleware('permission:commerce.manage')->group(function (): void {
        $controller = OrderManagementController::class;
        Route::get('orders/create', [$controller, 'create'])->name('orders.create');
        Route::post('orders/preview', [$controller, 'preview'])->name('orders.preview');
        Route::post('orders', [$controller, 'store'])->name('orders.store');
        Route::get('order-settings', [$controller, 'settings'])->name('orders.settings');
        Route::put('order-settings', [$controller, 'updateSettings'])->name('orders.settings.update');
        Route::put('order-settings/customer-auth', [$controller, 'updateAuthSettings'])->name('orders.settings.customer-auth');
        Route::get('payment-services', [$controller, 'paymentServices'])->name('payment-services.index');
        Route::put('payment-services', [$controller, 'updatePaymentServices'])->name('payment-services.update');
        Route::get('orders/{order}/complete', [$controller, 'create'])->name('orders.complete.form');
        Route::post('orders/{order}/complete', [$controller, 'complete'])->name('orders.complete');
        Route::post('orders/{order}/boxes', [$controller, 'retailBox'])->name('orders.boxes.store');
        Route::put('orders/{order}/boxes/{box}/packed', [$controller, 'packed'])->name('orders.boxes.packed');
    });
    Route::get('orders/{order}/packing-slip', [OrderManagementController::class, 'slip'])->middleware('permission:commerce.view')->name('orders.packing-slip');
    Route::get('orders/{order}/receipt', [OrderManagementController::class, 'receipt'])->middleware('permission:commerce.manage')->name('orders.receipt');
    Route::get('orders/{order}', [CommerceController::class, 'order'])->name('orders.show');
    Route::put('orders/{order}/customer', [CommerceController::class, 'updateCustomer'])->name('orders.customer.update');
    Route::put('orders/{order}/quote', [CommerceController::class, 'quote'])->name('orders.quote');
    Route::put('orders/{order}/status', [CommerceController::class, 'transition'])->name('orders.transition');
    Route::post('conversations/{conversation}/catalog', [CommerceController::class, 'sendCatalog'])->name('conversations.catalog');
    Route::get('conversations/{conversation}/products', [CommerceController::class, 'conversationProducts'])->name('conversations.products');
    Route::post('conversations/{conversation}/product', [CommerceController::class, 'sendProduct'])->name('conversations.product');
    Route::post('conversations/{conversation}/product-list', [CommerceController::class, 'sendProductList'])->name('conversations.product-list');
    Route::post('conversations/{conversation}/product-video', [CommerceController::class, 'sendProductVideo'])->name('conversations.product-video');
});

Route::prefix('commerce/storefront')->name('commerce.storefront.')->middleware('permission:commerce.manage')->group(function (): void {
    $c = AdminController::class;
    Route::get('/', [$c, 'index'])->name('index');
    Route::put('settings', [$c, 'settings'])->name('settings');
    Route::post('pages', [$c, 'savePage'])->name('pages.store');
    Route::get('pages/{id}', [$c, 'editor'])->name('pages.edit');
    Route::put('pages/{id}', [$c, 'savePage'])->name('pages.update');
    Route::delete('pages/{id}', [$c, 'deletePage'])->name('pages.destroy');
    Route::post('pages/{id}/publish', [$c, 'publish'])->name('pages.publish');
    Route::post('pages/{id}/preview', [$c, 'preview'])->name('pages.preview');
    Route::post('sliders', [$c, 'slider'])->name('sliders.store');
    Route::put('sliders/{id}', [$c, 'slider'])->name('sliders.update');
    Route::put('menus', [$c, 'menu'])->name('menus');
    Route::post('domains', [$c, 'addDomain'])->name('domains.store');
    Route::post('domains/{id}/verify', [$c, 'verifyDomain'])->name('domains.verify');
    Route::post('domains/{id}/primary', [$c, 'primaryDomain'])->name('domains.primary');
    Route::delete('domains/{id}', [$c, 'deleteDomain'])->name('domains.delete');
    Route::post('coupons', [$c, 'coupon'])->name('coupons.store');
    Route::put('coupons/{id}', [$c, 'coupon'])->name('coupons.update');
    Route::post('coupons/{id}/toggle', [$c, 'toggleCoupon'])->name('coupons.toggle');
    Route::delete('coupons/{id}', [$c, 'deleteCoupon'])->name('coupons.delete');
    Route::put('reviews/{id}', [$c, 'review'])->name('reviews.update');
});

Route::prefix('commerce/storefront')->name('commerce.storefront.')->middleware('permission:commerce.manage')->group(function (): void {
    $c = AdminController::class;
    Route::post('contents', [$c, 'content'])->name('contents.store');
    Route::put('contents/{id}', [$c, 'content'])->name('contents.update');
    Route::put('domains/{id}/handle', [$c, 'renameDomain'])->name('domains.rename');
    Route::post('credentials/generate', [$c, 'generateCredentials'])->name('credentials.generate');
    Route::put('credentials', [$c, 'updateCredentials'])->name('credentials.update');
});
