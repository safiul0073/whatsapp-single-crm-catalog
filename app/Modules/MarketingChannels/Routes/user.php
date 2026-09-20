<?php

use App\Modules\MarketingChannels\Http\Controllers\User\ChannelHubController;
use Illuminate\Support\Facades\Route;

Route::get('channels', [ChannelHubController::class, 'index'])->name('channels.index');
