<?php

use App\Modules\MetaSocial\Http\Controllers\User\MessengerOffersController;
use App\Modules\MetaSocial\Http\Controllers\User\MetaBusinessController;
use App\Modules\MetaSocial\Http\Controllers\User\MetaPagePostController;
use App\Modules\MetaSocial\Http\Controllers\User\MetaSocialSetupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:meta-social.manage'])->group(function (): void {
    Route::get('channels/social', [MetaSocialSetupController::class, 'index'])->name('meta-social.setup');
    Route::post('channels/social/{provider}', [MetaSocialSetupController::class, 'embedded'])
        ->whereIn('provider', ['messenger', 'instagram'])
        ->name('meta-social.setup.embedded');
    Route::post('channels/social/{provider}/select', [MetaSocialSetupController::class, 'select'])
        ->whereIn('provider', ['messenger', 'instagram'])
        ->name('meta-social.setup.select');
    Route::delete('channels/social/selection', [MetaSocialSetupController::class, 'cancelSelection'])->name('meta-social.setup.selection.cancel');
    Route::delete('channels/social/{channel}', [MetaSocialSetupController::class, 'disconnect'])->name('meta-social.setup.disconnect');
    Route::put('channels/social/{channel}/offers', [MetaSocialSetupController::class, 'offers'])->name('meta-social.setup.offers');
    Route::get('meta/messenger-offers', [MessengerOffersController::class, 'index'])->name('meta-social.offers.index');

    Route::get('channels/meta-business', [MetaBusinessController::class, 'show'])->name('meta-social.business.show');
    Route::post('channels/meta-business/connect', [MetaBusinessController::class, 'connect'])->name('meta-social.business.connect');
    Route::post('channels/meta-business/refresh', [MetaBusinessController::class, 'refresh'])->name('meta-social.business.refresh');
    Route::post('channels/meta-business/activate', [MetaBusinessController::class, 'activate'])->name('meta-social.business.activate');
    Route::get('channels/meta-business/result', [MetaBusinessController::class, 'result'])->name('meta-social.business.result');
    Route::delete('channels/meta-business', [MetaBusinessController::class, 'disconnect'])->name('meta-social.business.disconnect');
});

Route::middleware(['can:meta-social.publish'])->prefix('meta/posts')->name('meta-social.posts.')->group(function (): void {
    Route::get('/', [MetaPagePostController::class, 'index'])->name('index');
    Route::get('create', [MetaPagePostController::class, 'create'])->name('create');
    Route::post('/', [MetaPagePostController::class, 'store'])->name('store');
    Route::post('{post}/retry', [MetaPagePostController::class, 'retry'])->whereNumber('post')->name('retry');
    Route::delete('{post}', [MetaPagePostController::class, 'destroy'])->whereNumber('post')->name('destroy');
});
