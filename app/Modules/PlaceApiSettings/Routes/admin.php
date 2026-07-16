<?php

use App\Modules\PlaceApiSettings\Http\Controllers\Admin\PlaceApiSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('place-api-settings', [PlaceApiSettingsController::class, 'index'])->name('place-api-settings.index');
Route::put('place-api-settings', [PlaceApiSettingsController::class, 'update'])->name('place-api-settings.update');
