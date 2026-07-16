<?php

use App\Modules\Settings\Http\Controllers\Admin\SettingController;
use Illuminate\Support\Facades\Route;

Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
