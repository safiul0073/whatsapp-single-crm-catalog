<?php

use App\Modules\MetaAds\Http\Controllers\User\MetaAdsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:meta-ads.view'])->group(function (): void {
    Route::get('meta-ads', [MetaAdsController::class, 'index'])->name('meta-ads.index');
});
