<?php

use App\Modules\Email\Http\Controllers\User\EmailChannelSetupController;
use Illuminate\Support\Facades\Route;

Route::get('channels/email', [EmailChannelSetupController::class, 'index'])->name('email.index');
Route::post('channels/email', [EmailChannelSetupController::class, 'store'])->name('email.store');
Route::put('channels/email/{channel}', [EmailChannelSetupController::class, 'update'])->name('email.update');
Route::delete('channels/email/{channel}', [EmailChannelSetupController::class, 'destroy'])->name('email.destroy');
Route::post('channels/email/{channel}/test', [EmailChannelSetupController::class, 'test'])->name('email.test');
