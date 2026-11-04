<?php

use App\Modules\MetaSocial\Http\Controllers\Admin\MetaAppSettingsController;
use App\Modules\MetaSocial\Http\Controllers\Admin\MetaSocialSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('meta-app/settings', [MetaAppSettingsController::class, 'index'])->name('meta-app.settings.index');
Route::put('meta-app/settings', [MetaAppSettingsController::class, 'update'])->name('meta-app.settings.update');
Route::post('meta-app/check', [MetaAppSettingsController::class, 'check'])->name('meta-app.settings.check');
Route::get('meta-social/settings', [MetaSocialSettingsController::class, 'index'])->name('meta-social.settings.index');
Route::put('meta-social/settings', [MetaSocialSettingsController::class, 'update'])->name('meta-social.settings.update');
