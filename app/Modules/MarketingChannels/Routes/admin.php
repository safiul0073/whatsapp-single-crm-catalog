<?php

use App\Modules\MarketingChannels\Http\Controllers\Admin\ChannelAccountController;
use Illuminate\Support\Facades\Route;

Route::get('channels', [ChannelAccountController::class, 'index'])->name('marketing-channels.index');
