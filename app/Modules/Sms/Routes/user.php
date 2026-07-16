<?php

use App\Modules\Sms\Http\Controllers\User\SmsChannelSetupController;
use Illuminate\Support\Facades\Route;

Route::get('channels/sms', [SmsChannelSetupController::class, 'index'])->name('sms.index');
Route::post('channels/sms', [SmsChannelSetupController::class, 'store'])->name('sms.store');
Route::put('channels/sms/{channel}', [SmsChannelSetupController::class, 'update'])->name('sms.update');
Route::delete('channels/sms/{channel}', [SmsChannelSetupController::class, 'destroy'])->name('sms.destroy');
Route::post('channels/sms/{channel}/test', [SmsChannelSetupController::class, 'test'])->name('sms.test');
