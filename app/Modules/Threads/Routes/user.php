<?php

use App\Modules\Threads\Http\Controllers\User\ThreadsConnectController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:meta-social.manage'])->group(function (): void {
    Route::post('channels/threads/connect', [ThreadsConnectController::class, 'connect'])->name('threads.connect');
    Route::get('channels/threads/callback', [ThreadsConnectController::class, 'callback'])->name('threads.callback');
});
