<?php

use App\Modules\AiSettings\Http\Controllers\Admin\AiSettingsController;
use App\Modules\AiSettings\Http\Controllers\Admin\AiUsageController;
use Illuminate\Support\Facades\Route;

Route::get('ai-usage', [AiUsageController::class, 'index'])->name('ai-usage.index');
Route::get('ai-settings', [AiSettingsController::class, 'index'])->name('ai-settings.index');
Route::get('ai-settings/vector-database', [AiSettingsController::class, 'vectorDatabase'])->name('ai-settings.vector-database.index');
Route::put('ai-settings', [AiSettingsController::class, 'update'])->name('ai-settings.update');
Route::match(['post', 'put'], 'ai-settings/vector-database/test', [AiSettingsController::class, 'testVectorDatabase'])->name('ai-settings.vector-database.test');
