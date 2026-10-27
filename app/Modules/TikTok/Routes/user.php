<?php

use App\Modules\TikTok\Http\Controllers\User\TikTokConnectController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:tiktok.manage'])->group(function (): void {
    Route::get('channels/tiktok', [TikTokConnectController::class, 'index'])->name('tiktok.index');
    Route::post('channels/tiktok/connect', [TikTokConnectController::class, 'connect'])->name('tiktok.connect');
    Route::get('channels/tiktok/callback', [TikTokConnectController::class, 'callback'])->name('tiktok.callback');
    Route::delete('channels/tiktok/{channel}', [TikTokConnectController::class, 'disconnect'])->name('tiktok.disconnect');
});
