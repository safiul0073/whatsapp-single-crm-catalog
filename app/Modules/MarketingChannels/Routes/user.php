<?php

use App\Modules\MarketingChannels\Http\Controllers\User\ChannelHubController;
use App\Modules\MarketingChannels\Http\Controllers\User\ChannelReadinessController;
use Illuminate\Support\Facades\Route;

Route::get('channels', [ChannelHubController::class, 'index'])->name('channels.index');
Route::post('channels/{channel}/readiness', [ChannelReadinessController::class, 'check'])->name('channels.readiness');
