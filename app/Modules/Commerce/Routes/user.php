<?php

use App\Modules\Commerce\Http\Controllers\User\CommerceController;
use App\Modules\Commerce\Http\Controllers\User\OrderManagementController;
use App\Modules\Commerce\Http\Controllers\User\PosController;
use App\Modules\Commerce\Storefront\Http\Controllers\AdminController;
use App\Modules\Commerce\Storefront\Http\Controllers\PackTemplateAdminController;
use App\Modules\Commerce\Storefront\Http\Controllers\ReferralAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('commerce')->name('commerce.')->group(function (): void {
    Route::middleware('permission:commerce.pack-templates.manage')->group(function (): void {
        Route::get('pack-templates', [PackTemplateAdminController::class, 'index'])->name('pack-templates.index');
        Route::get('pack-templates/create', [PackTemplateAdminController::class, 'create'])->name('pack-templates.create');
        Route::post('pack-templates', [PackTemplateAdminController::class, 'save'])->name('pack-templates.store');
        Route::get('pack-templates/{id}/edit', [PackTemplateAdminController::class, 'edit'])->name('pack-templates.edit');
        Route::put('pack-templates/{id}', [PackTemplateAdminController::class, 'save'])->name('pack-templates.update');
        Route::delete('pack-templates/{id}', [PackTemplateAdminController::class, 'destroy'])->name('pack-templates.destroy');
    });
    Route::prefix('pos')->name('pos.')->group(function (): void {
        Route::middleware('permission:commerce.pos.use')->group(function (): void {
            Route::get('/', [PosController::class, 'index'])->name('index');
            Route::get('products', [PosController::class, 'products'])->name('products');
            Route::post('preview', [PosController::class, 'preview'])->name('preview');
            Route::post('checkout', [PosController::class, 'checkout'])->name('checkout');
            Route::post('orders/{order}/payments', [PosController::class, 'payment'])->name('payment');
            Route::post('orders/{order}/pickup', [PosController::class, 'pickup'])->name('pickup');
        });
        Route::get('orders/{order}/receipt', [PosController::class, 'receipt'])->middleware('permission:commerce.orders.view')->name('receipt');
        Route::get('customers/{contact}/balance', [PosController::class, 'customerBalance'])->middleware('permission:commerce.customers.view')->name('customer-balance');
    });

    Route::get('/', [CommerceController::class, 'index'])->middleware('permission:commerce.products.view')->name('products.index');
    Route::get('products/create', [CommerceController::class, 'create'])->middleware('permission:commerce.products.manage')->name('products.create');
    Route::post('products', [CommerceController::class, 'store'])->middleware('permission:commerce.products.manage')->name('products.store');
    Route::get('products/{product}/edit', [CommerceController::class, 'edit'])->middleware('permission:commerce.products.manage')->name('products.edit');
    Route::put('products/{product}', [CommerceController::class, 'update'])->middleware('permission:commerce.products.manage')->name('products.update');
    Route::put('products/{product}/details', [CommerceController::class, 'updateDetails'])->middleware('permission:commerce.products.manage')->name('products.details.update');
    Route::put('products/{product}/gallery', [CommerceController::class, 'updateGallery'])->middleware('permission:commerce.products.manage')->name('products.gallery.update');
    Route::put('products/{product}/options', [CommerceController::class, 'updateOptions'])->middleware('permission:commerce.products.manage')->name('products.options.update');
    Route::get('products/{product}/variants/preview', [CommerceController::class, 'previewVariants'])->middleware('permission:commerce.products.manage')->name('products.variants.preview');
    Route::put('products/{product}/variants', [CommerceController::class, 'updateVariants'])->middleware('permission:commerce.products.manage')->name('products.variants.update');
    Route::post('products/{product}/catalog', [CommerceController::class, 'syncProductCatalog'])->middleware('permission:commerce.catalog.manage')->name('products.catalog.sync');
    Route::put('products/{product}/publish', [CommerceController::class, 'publish'])->middleware('permission:commerce.products.manage')->name('products.publish');
    Route::post('products/media', [CommerceController::class, 'uploadMedia'])->middleware('permission:commerce.products.manage')->name('products.media.upload');
    Route::delete('products/bulk', [CommerceController::class, 'bulkDestroyProducts'])->middleware('permission:commerce.products.manage')->name('products.bulk-destroy');
    Route::delete('products/{product}', [CommerceController::class, 'destroy'])->middleware('permission:commerce.products.manage')->name('products.destroy');
    Route::get('categories', [CommerceController::class, 'categories'])->middleware('permission:commerce.products.view')->name('categories.index');
    Route::post('categories', [CommerceController::class, 'storeCategory'])->middleware('permission:commerce.products.manage')->name('categories.store');
    Route::put('categories/{category}', [CommerceController::class, 'updateCategory'])->middleware('permission:commerce.products.manage')->name('categories.update');
    Route::delete('categories/bulk', [CommerceController::class, 'bulkDestroyCategories'])->middleware('permission:commerce.products.manage')->name('categories.bulk-destroy');
    Route::delete('categories/{category}', [CommerceController::class, 'destroyCategory'])->middleware('permission:commerce.products.manage')->name('categories.destroy');
    Route::get('brands', [CommerceController::class, 'brands'])->middleware('permission:commerce.products.view')->name('brands.index');
    Route::post('brands', [CommerceController::class, 'storeBrand'])->middleware('permission:commerce.products.manage')->name('brands.store');
    Route::put('brands/{brand}', [CommerceController::class, 'updateBrand'])->middleware('permission:commerce.products.manage')->name('brands.update');
    Route::delete('brands/bulk', [CommerceController::class, 'bulkDestroyBrands'])->middleware('permission:commerce.products.manage')->name('brands.bulk-destroy');
    Route::delete('brands/{brand}', [CommerceController::class, 'destroyBrand'])->middleware('permission:commerce.products.manage')->name('brands.destroy');
    Route::get('audiences', [CommerceController::class, 'audiences'])->middleware('permission:commerce.products.view')->name('audiences.index');
    Route::post('audiences', [CommerceController::class, 'storeAudience'])->middleware('permission:commerce.products.manage')->name('audiences.store');
    Route::put('audiences/{audience}', [CommerceController::class, 'updateAudience'])->middleware('permission:commerce.products.manage')->name('audiences.update');
    Route::delete('audiences/bulk', [CommerceController::class, 'bulkDestroyAudiences'])->middleware('permission:commerce.products.manage')->name('audiences.bulk-destroy');
    Route::delete('audiences/{audience}', [CommerceController::class, 'destroyAudience'])->middleware('permission:commerce.products.manage')->name('audiences.destroy');
    Route::get('variants', [CommerceController::class, 'variants'])->middleware('permission:commerce.products.view')->name('variants.index');
    Route::post('variants', [CommerceController::class, 'storeVariantPreset'])->middleware('permission:commerce.products.manage')->name('variants.store');
    Route::put('variants/{preset}', [CommerceController::class, 'updateVariantPreset'])->middleware('permission:commerce.products.manage')->name('variants.update');
    Route::delete('variants/bulk', [CommerceController::class, 'bulkDestroyVariantPresets'])->middleware('permission:commerce.products.manage')->name('variants.bulk-destroy');
    Route::delete('variants/{preset}', [CommerceController::class, 'destroyVariantPreset'])->middleware('permission:commerce.products.manage')->name('variants.destroy');
    Route::get('catalog', [CommerceController::class, 'catalog'])->middleware('permission:commerce.catalog.manage')->name('catalog');
    Route::post('catalog', [CommerceController::class, 'storeCatalog'])->middleware('permission:commerce.catalog.manage')->name('catalog.store');
    Route::post('catalog/{catalog}/token', [CommerceController::class, 'rotateFeedToken'])->middleware('permission:commerce.catalog.manage')->name('catalog.token.rotate');
    Route::post('catalog/{catalog}/sync', [CommerceController::class, 'syncCatalog'])->middleware('permission:commerce.catalog.manage')->name('catalog.sync');
    Route::post('catalog/{catalog}/verify-access', [CommerceController::class, 'verifyCatalogAccess'])->middleware('permission:commerce.catalog.manage')->name('catalog.verify-access');
    Route::post('catalog/{catalog}/commerce-settings', [CommerceController::class, 'updateCommerceSettings'])->middleware('permission:commerce.catalog.manage')->name('catalog.commerce-settings');
    Route::get('orders', [CommerceController::class, 'orders'])->middleware('permission:commerce.orders.view')->name('orders.index');
    Route::get('customers', [CommerceController::class, 'customers'])->middleware('permission:commerce.customers.view')->name('customers.index');
    Route::get('customers/{customer}', [CommerceController::class, 'customer'])->middleware('permission:commerce.customers.view')->name('customers.show');
    $controller = OrderManagementController::class;
    Route::middleware('permission:commerce.orders.create')->group(function () use ($controller): void {
        Route::get('orders/create', [$controller, 'create'])->name('orders.create');
        Route::post('orders/preview', [$controller, 'preview'])->name('orders.preview');
        Route::post('orders', [$controller, 'store'])->name('orders.store');
        Route::get('orders/{order}/complete', [$controller, 'create'])->name('orders.complete.form');
        Route::post('orders/{order}/complete', [$controller, 'complete'])->name('orders.complete');
    });
    Route::middleware('permission:commerce.orders.settings')->group(function () use ($controller): void {
        Route::get('order-settings', [$controller, 'settings'])->name('orders.settings');
        Route::put('order-settings', [$controller, 'updateSettings'])->name('orders.settings.update');
        Route::put('order-settings/customer-auth', [$controller, 'updateAuthSettings'])->name('orders.settings.customer-auth');
        Route::get('payment-services', [$controller, 'paymentServices'])->name('payment-services.index');
        Route::put('payment-services', [$controller, 'updatePaymentServices'])->name('payment-services.update');
    });
    Route::middleware('permission:commerce.orders.update')->group(function () use ($controller): void {
        Route::post('orders/{order}/boxes', [$controller, 'retailBox'])->name('orders.boxes.store');
        Route::put('orders/{order}/boxes/{box}/packed', [$controller, 'packed'])->name('orders.boxes.packed');
    });
    Route::get('orders/{order}/packing-slip', [OrderManagementController::class, 'slip'])->middleware('permission:commerce.orders.view')->name('orders.packing-slip');
    Route::get('orders/{order}/receipt', [OrderManagementController::class, 'receipt'])->middleware('permission:commerce.orders.payments.view')->name('orders.receipt');
    Route::get('orders/{order}', [CommerceController::class, 'order'])->middleware('permission:commerce.orders.view')->name('orders.show');
    Route::put('orders/{order}/customer', [CommerceController::class, 'updateCustomer'])->middleware('permission:commerce.orders.customer.edit')->name('orders.customer.update');
    Route::put('orders/{order}/quote', [CommerceController::class, 'quote'])->middleware('permission:commerce.orders.update')->name('orders.quote');
    Route::put('orders/{order}/status', [CommerceController::class, 'transition'])->middleware('permission:commerce.orders.update')->name('orders.transition');
    Route::put('orders/{order}/shipment', [CommerceController::class, 'updateShipment'])->middleware('permission:commerce.orders.update')->name('orders.shipment');
    Route::post('conversations/{conversation}/catalog', [CommerceController::class, 'sendCatalog'])->middleware('permission:commerce.catalog.manage')->name('conversations.catalog');
    Route::get('conversations/{conversation}/products', [CommerceController::class, 'conversationProducts'])->middleware('permission:commerce.products.view')->name('conversations.products');
    Route::post('conversations/{conversation}/product', [CommerceController::class, 'sendProduct'])->middleware('permission:commerce.catalog.manage')->name('conversations.product');
    Route::post('conversations/{conversation}/product-list', [CommerceController::class, 'sendProductList'])->middleware('permission:commerce.catalog.manage')->name('conversations.product-list');
    Route::post('conversations/{conversation}/product-video', [CommerceController::class, 'sendProductVideo'])->middleware('permission:commerce.catalog.manage')->name('conversations.product-video');
});

Route::prefix('commerce/storefront')->name('commerce.storefront.')->group(function (): void {
    $c = AdminController::class;
    Route::get('/', [$c, 'index'])->middleware('permission:commerce.storefront.settings|commerce.storefront.pages|commerce.storefront.marketing|commerce.storefront.domains|commerce.storefront.reviews|commerce.storefront.credentials')->name('index');
    Route::get('live', [$c, 'liveStudio'])->middleware('permission:commerce.storefront.settings|commerce.storefront.marketing')->name('live');
    Route::put('settings', [$c, 'settings'])->middleware('permission:commerce.storefront.settings')->name('settings');
    Route::post('upload-app', [$c, 'uploadApp'])->middleware('permission:commerce.storefront.settings')->name('upload-app');
    Route::put('chat-channels', [$c, 'chatChannels'])->middleware('permission:commerce.storefront.settings')->name('chat-channels');
    Route::post('pages', [$c, 'savePage'])->middleware('permission:commerce.storefront.pages')->name('pages.store');
    Route::get('pages/{id}', [$c, 'editor'])->middleware('permission:commerce.storefront.pages')->name('pages.edit');
    Route::put('pages/{id}', [$c, 'savePage'])->middleware('permission:commerce.storefront.pages')->name('pages.update');
    Route::delete('pages/{id}', [$c, 'deletePage'])->middleware('permission:commerce.storefront.pages')->name('pages.destroy');
    Route::post('pages/{id}/publish', [$c, 'publish'])->middleware('permission:commerce.storefront.pages')->name('pages.publish');
    Route::post('pages/{id}/preview', [$c, 'preview'])->middleware('permission:commerce.storefront.pages')->name('pages.preview');
    Route::post('sliders', [$c, 'slider'])->middleware('permission:commerce.storefront.marketing')->name('sliders.store');
    Route::put('sliders/{id}', [$c, 'slider'])->middleware('permission:commerce.storefront.marketing')->name('sliders.update');
    Route::put('menus', [$c, 'menu'])->middleware('permission:commerce.storefront.marketing')->name('menus');
    Route::post('domains', [$c, 'addDomain'])->middleware('permission:commerce.storefront.domains')->name('domains.store');
    Route::post('domains/{id}/verify', [$c, 'verifyDomain'])->middleware('permission:commerce.storefront.domains')->name('domains.verify');
    Route::post('domains/{id}/primary', [$c, 'primaryDomain'])->middleware('permission:commerce.storefront.domains')->name('domains.primary');
    Route::delete('domains/{id}', [$c, 'deleteDomain'])->middleware('permission:commerce.storefront.domains')->name('domains.delete');
    Route::post('coupons', [$c, 'coupon'])->middleware('permission:commerce.storefront.marketing')->name('coupons.store');
    Route::put('coupons/{id}', [$c, 'coupon'])->middleware('permission:commerce.storefront.marketing')->name('coupons.update');
    Route::post('coupons/{id}/toggle', [$c, 'toggleCoupon'])->middleware('permission:commerce.storefront.marketing')->name('coupons.toggle');
    Route::delete('coupons/{id}', [$c, 'deleteCoupon'])->middleware('permission:commerce.storefront.marketing')->name('coupons.delete');
    Route::put('reviews/{id}', [$c, 'review'])->middleware('permission:commerce.storefront.reviews')->name('reviews.update');
});

Route::prefix('commerce/storefront')->name('commerce.storefront.')->group(function (): void {
    $c = AdminController::class;
    Route::post('contents', [$c, 'content'])->middleware('permission:commerce.storefront.pages')->name('contents.store');
    Route::put('contents/{id}', [$c, 'content'])->middleware('permission:commerce.storefront.pages')->name('contents.update');
    Route::delete('contents/{id}', [$c, 'deleteContent'])->middleware('permission:commerce.storefront.pages')->name('contents.destroy');
    Route::put('domains/{id}/handle', [$c, 'renameDomain'])->middleware('permission:commerce.storefront.domains')->name('domains.rename');
    Route::post('credentials/generate', [$c, 'generateCredentials'])->middleware('permission:commerce.storefront.credentials')->name('credentials.generate');
    Route::put('credentials', [$c, 'updateCredentials'])->middleware('permission:commerce.storefront.credentials')->name('credentials.update');
    Route::put('live-settings', [$c, 'liveSettings'])->middleware('permission:commerce.storefront.settings')->name('live.settings');
    Route::post('live-settings/verify-facebook', [$c, 'verifyFacebookLive'])->middleware('permission:commerce.storefront.settings')->name('live.verify-facebook');
    Route::post('lives', [$c, 'storeLive'])->middleware('permission:commerce.storefront.marketing')->name('lives.store');
    Route::post('lives/{id}/toggle-slider', [$c, 'toggleLiveSlider'])->middleware('permission:commerce.storefront.marketing')->name('lives.toggle-slider');
    Route::post('lives/{id}/stop', [$c, 'stopLive'])->middleware('permission:commerce.storefront.marketing')->name('lives.stop');
    Route::delete('lives/{id}', [$c, 'deleteLive'])->middleware('permission:commerce.storefront.marketing')->name('lives.destroy');
});

Route::prefix('commerce/referrals')->name('commerce.referrals.')->group(function (): void {
    $c = ReferralAdminController::class;
    Route::middleware('permission:commerce.referrals.view')->group(function () use ($c): void {
        Route::get('/', [$c, 'index'])->name('index');
        Route::get('export', [$c, 'export'])->name('export');
        Route::get('customers', [$c, 'customers'])->name('customers');
    });
    Route::middleware('permission:commerce.referrals.manage')->group(function () use ($c): void {
        Route::put('settings', [$c, 'saveSettings'])->name('settings');
        Route::post('slabs', [$c, 'saveSlab'])->name('slabs.store');
        Route::put('slabs/{id}', [$c, 'saveSlab'])->name('slabs.update');
        Route::delete('slabs/{id}', [$c, 'deleteSlab'])->name('slabs.destroy');
        Route::post('rules', [$c, 'saveRule'])->name('rules.store');
        Route::put('rules/{id}', [$c, 'saveRule'])->name('rules.update');
        Route::delete('rules/{id}', [$c, 'deleteRule'])->name('rules.destroy');
        Route::post('wallets/adjust', [$c, 'adjustWallet'])->name('wallets.adjust');
        Route::put('cashins/{id}', [$c, 'updateCashin'])->name('cashins.update');
    });
});
