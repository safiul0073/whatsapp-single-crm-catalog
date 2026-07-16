<?php

use App\Modules\MetaSocial\Http\Controllers\Admin\MetaSocialSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('meta-social/settings', [MetaSocialSettingsController::class, 'index'])->name('meta-social.settings.index');
Route::put('meta-social/settings', [MetaSocialSettingsController::class, 'update'])->name('meta-social.settings.update');
