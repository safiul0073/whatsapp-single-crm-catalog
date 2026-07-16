<?php

use App\Modules\WhatsAppCloud\Http\Controllers\Admin\WhatsAppSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('whatsapp/settings', [WhatsAppSettingsController::class, 'index'])->name('whatsapp-cloud.settings.index');
Route::put('whatsapp/settings', [WhatsAppSettingsController::class, 'update'])->name('whatsapp-cloud.settings.update');
