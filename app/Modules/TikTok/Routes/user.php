<?php

use App\Modules\TikTok\Http\Controllers\User\TikTokConnectController;
use App\Modules\TikTok\Http\Controllers\User\TikTokPostController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:tiktok.manage'])->group(function (): void {
    Route::get('channels/tiktok', [TikTokConnectController::class, 'index'])->name('tiktok.index');
    Route::post('channels/tiktok/connect', [TikTokConnectController::class, 'connect'])->name('tiktok.connect');
    Route::get('channels/tiktok/callback', [TikTokConnectController::class, 'callback'])->name('tiktok.callback');
    Route::delete('channels/tiktok/{channel}', [TikTokConnectController::class, 'disconnect'])->name('tiktok.disconnect');

    Route::get('tiktok/posts', [TikTokPostController::class, 'index'])->name('tiktok.posts.index');
    Route::get('tiktok/posts/create', [TikTokPostController::class, 'create'])->name('tiktok.posts.create');
    Route::post('tiktok/posts', [TikTokPostController::class, 'store'])->name('tiktok.posts.store');
    Route::post('tiktok/posts/{post}/refresh', [TikTokPostController::class, 'refresh'])->whereNumber('post')->name('tiktok.posts.refresh');
    Route::delete('tiktok/posts/{post}', [TikTokPostController::class, 'destroy'])->whereNumber('post')->name('tiktok.posts.destroy');
});
