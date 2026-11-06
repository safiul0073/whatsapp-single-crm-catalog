<?php

use App\Modules\MetaAds\Http\Controllers\User\MetaAdManagerController;
use App\Modules\MetaAds\Http\Controllers\User\MetaAdsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:meta-ads.view'])->group(function (): void {
    Route::get('meta-ads', [MetaAdsController::class, 'index'])->name('meta-ads.index');
});

Route::middleware(['can:meta-ads.manage'])->prefix('meta-ads/ads')->name('meta-ads.ads.')->group(function (): void {
    Route::get('/', [MetaAdManagerController::class, 'index'])->name('index');
    Route::get('create', [MetaAdManagerController::class, 'create'])->name('create');
    Route::post('/', [MetaAdManagerController::class, 'store'])->name('store');
    Route::get('page-posts', [MetaAdManagerController::class, 'pagePosts'])->name('page-posts');
    Route::get('partnership-content', [MetaAdManagerController::class, 'partnershipContent'])->name('partnership-content');
    Route::post('creator-permission', [MetaAdManagerController::class, 'creatorPermission'])->name('creator-permission');
    Route::post('{ad}/start', [MetaAdManagerController::class, 'start'])->whereNumber('ad')->name('start');
    Route::post('{ad}/pause', [MetaAdManagerController::class, 'pause'])->whereNumber('ad')->name('pause');
    Route::delete('{ad}', [MetaAdManagerController::class, 'destroy'])->whereNumber('ad')->name('destroy');
});
