<?php

use App\Modules\MetaSocial\Http\Controllers\User\MetaSocialSetupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:meta-social.manage'])->group(function (): void {
    Route::get('channels/social', [MetaSocialSetupController::class, 'index'])->name('meta-social.setup');
    Route::post('channels/social/{provider}', [MetaSocialSetupController::class, 'embedded'])
        ->whereIn('provider', ['messenger', 'instagram'])
        ->name('meta-social.setup.embedded');
    Route::delete('channels/social/{channel}', [MetaSocialSetupController::class, 'disconnect'])->name('meta-social.setup.disconnect');
});
