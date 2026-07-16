<?php

use App\Modules\Telegram\Http\Controllers\User\TelegramChannelSetupController;
use Illuminate\Support\Facades\Route;

Route::get('channels/telegram', [TelegramChannelSetupController::class, 'index'])->name('telegram.index');
Route::post('channels/telegram', [TelegramChannelSetupController::class, 'store'])->name('telegram.store');
Route::put('channels/telegram/{channel}', [TelegramChannelSetupController::class, 'update'])->name('telegram.update');
Route::delete('channels/telegram/{channel}', [TelegramChannelSetupController::class, 'destroy'])->name('telegram.destroy');
Route::post('channels/telegram/{channel}/test', [TelegramChannelSetupController::class, 'test'])->name('telegram.test');
Route::post('channels/telegram/{channel}/set-webhook', [TelegramChannelSetupController::class, 'setWebhook'])->name('telegram.set-webhook');
Route::post('contacts/{contact}/telegram-opt-in-link', [TelegramChannelSetupController::class, 'optInLink'])->name('telegram.contacts.opt-in-link');
Route::post('contacts/{contact}/telegram-invite', [TelegramChannelSetupController::class, 'sendInvite'])->name('telegram.contacts.invite');
