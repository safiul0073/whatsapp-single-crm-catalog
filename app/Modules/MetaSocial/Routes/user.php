<?php

use App\Modules\MetaSocial\Http\Controllers\User\MetaBusinessController;
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

    Route::get('channels/meta-business', [MetaBusinessController::class, 'show'])->name('meta-social.business.show');
    Route::post('channels/meta-business/connect', [MetaBusinessController::class, 'connect'])->name('meta-social.business.connect');
    Route::post('channels/meta-business/refresh', [MetaBusinessController::class, 'refresh'])->name('meta-social.business.refresh');
    Route::post('channels/meta-business/activate', [MetaBusinessController::class, 'activate'])->name('meta-social.business.activate');
    Route::get('channels/meta-business/result', [MetaBusinessController::class, 'result'])->name('meta-social.business.result');
    Route::delete('channels/meta-business', [MetaBusinessController::class, 'disconnect'])->name('meta-social.business.disconnect');
});
