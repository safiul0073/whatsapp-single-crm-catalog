<?php

use App\Modules\Commerce\Storefront\Http\Controllers\AuthController;
use App\Modules\Commerce\Storefront\Http\Controllers\CartController;
use App\Modules\Commerce\Storefront\Http\Controllers\CatalogController;
use App\Modules\Commerce\Storefront\Http\Controllers\CheckoutController;
use App\Modules\Commerce\Storefront\Http\Controllers\CmsController;
use App\Modules\Commerce\Storefront\Http\Controllers\DomainController;
use App\Modules\Commerce\Storefront\Http\Controllers\EngagementController;
use App\Modules\Commerce\Storefront\Http\Controllers\OrderController;
use App\Modules\Commerce\Storefront\Http\Controllers\PackController;
use App\Modules\Commerce\Storefront\Http\Controllers\ProfileController;
use App\Modules\Commerce\Storefront\Http\Middleware\AuthenticateCustomer;
use App\Modules\Commerce\Storefront\Http\Middleware\AuthenticatePlatform;
use App\Modules\Commerce\Storefront\Http\Middleware\OptionalCustomer;
use App\Modules\Commerce\Storefront\Http\Middleware\ResolveStore;
use Illuminate\Support\Facades\Route;

$storefrontRoutes = function (): void {
    Route::get('pack-templates', [PackController::class, 'templates']);
    Route::get('catalog/menu', [CatalogController::class, 'menu']);
    Route::get('catalog/categories', [CatalogController::class, 'categories']);
    Route::get('catalog/categories/{slug}', [CatalogController::class, 'category']);
    Route::get('catalog/brands', [CatalogController::class, 'brands']);
    Route::get('catalog/products', [CatalogController::class, 'index']);
    Route::get('catalog/products/filters', [CatalogController::class, 'filters']);
    Route::get('catalog/products/{slug}', [CatalogController::class, 'show']);
    Route::get('catalog/search/suggest', [CatalogController::class, 'suggest']);
    Route::get('catalog/collections/{key}', [CatalogController::class, 'collection']);
    Route::get('catalog/sitemap', [CatalogController::class, 'sitemap']);
    Route::get('cms/settings', [CmsController::class, 'settings']);
    Route::get('cms/menus/{slot}', [CmsController::class, 'menu']);
    Route::get('cms/pages/{slug}', [CmsController::class, 'page']);
    Route::get('auth/status', [AuthController::class, 'status']);
    Route::middleware('throttle:storefront-auth')->group(function (): void {
        Route::post('auth/whatsapp/challenges', [AuthController::class, 'challenge']);
        Route::post('auth/whatsapp/challenges/{id}/resend', [AuthController::class, 'resend']);
        Route::post('auth/whatsapp/challenges/{id}/verify', [AuthController::class, 'verify']);
        Route::post('auth/whatsapp/register', [AuthController::class, 'register']);
        Route::post('auth/password/login', [AuthController::class, 'passwordLogin']);
        Route::get('orders/track', [OrderController::class, 'track']);
        Route::post('contact', [EngagementController::class, 'contact']);
        Route::post('newsletter', [EngagementController::class, 'newsletter']);
    });
    Route::middleware(OptionalCustomer::class)->group(function (): void {
        Route::post('cart', [CartController::class, 'create']);
        Route::get('cart', [CartController::class, 'show']);
        Route::post('cart/groups/preview', [PackController::class, 'preview']);
        Route::post('cart/groups', [PackController::class, 'store']);
        Route::put('cart/groups/{id}', [PackController::class, 'update']);
        Route::delete('cart/groups/{id}', [PackController::class, 'destroy']);
        Route::post('cart/items', [CartController::class, 'add']);
        Route::patch('cart/items/{id}', [CartController::class, 'update']);
        Route::post('cart/items/{id}/confirm-retail', [CartController::class, 'confirmRetail']);
        Route::delete('cart/items/{id}', [CartController::class, 'delete']);
        Route::delete('cart/clear', [CartController::class, 'clear']);
        Route::post('cart/coupon', [CartController::class, 'coupon']);
        Route::delete('cart/coupon', [CartController::class, 'removeCoupon']);
    });
    Route::get('blogs', [EngagementController::class, 'blogs']);
    Route::get('blogs/{slug}', [EngagementController::class, 'blog']);
    Route::get('faqs', [EngagementController::class, 'faqs']);
    Route::get('catalog/products/{slug}/reviews', [EngagementController::class, 'reviews']);
    Route::middleware(AuthenticateCustomer::class)->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::put('auth/password', [AuthController::class, 'password']);
        Route::get('me', [ProfileController::class, 'show']);
        Route::put('me', [ProfileController::class, 'update']);
        Route::get('me/addresses', [ProfileController::class, 'addresses']);
        Route::post('me/addresses', [ProfileController::class, 'saveAddress']);
        Route::put('me/addresses/{id}', [ProfileController::class, 'saveAddress']);
        Route::delete('me/addresses/{id}', [ProfileController::class, 'deleteAddress']);
        Route::post('me/addresses/{id}/default', [ProfileController::class, 'defaultAddress']);
        Route::post('cart/merge', [CartController::class, 'merge']);
        Route::get('checkout/payment-methods', [CheckoutController::class, 'methods']);
        Route::get('checkout/shipping-methods', [CheckoutController::class, 'shippingMethods']);
        Route::post('checkout/preview', [CheckoutController::class, 'preview']);
        Route::post('checkout/orders', [CheckoutController::class, 'store']);
        Route::get('me/orders', [OrderController::class, 'index']);
        Route::get('me/orders/{ref}', [OrderController::class, 'show']);
        Route::post('me/orders/{ref}/cancel', [OrderController::class, 'cancel']);
        Route::get('me/orders/{ref}/invoice', [OrderController::class, 'invoice']);
        Route::post('orders/{ref}/evidence', [OrderController::class, 'evidence']);
        Route::get('me/wishlist', [EngagementController::class, 'wishlist']);
        Route::post('me/wishlist/{id}', [EngagementController::class, 'saveWishlist']);
        Route::delete('me/wishlist/{id}', [EngagementController::class, 'deleteWishlist']);
        Route::post('catalog/products/{slug}/reviews', [EngagementController::class, 'review']);
    });
};

Route::middleware([AuthenticatePlatform::class, 'throttle:storefront'])->group(function () use ($storefrontRoutes): void {
    Route::get('internal/stores/resolve', [DomainController::class, 'resolve']);
    Route::get('internal/domains/allowed', [DomainController::class, 'allowed']);
    Route::prefix('api/storefront/v1')->name('storefront.')->middleware(ResolveStore::class)->group($storefrontRoutes);
});

Route::prefix('api/v1/mobile/storefront')
    ->name('mobile.storefront.')
    ->middleware(['throttle:storefront', ResolveStore::class])
    ->group($storefrontRoutes);
