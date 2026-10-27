<?php

use App\Modules\TikTok\Http\Controllers\Admin\TikTokSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('tiktok/settings', [TikTokSettingsController::class, 'index'])->name('tiktok.settings.index');
Route::put('tiktok/settings', [TikTokSettingsController::class, 'update'])->name('tiktok.settings.update');
