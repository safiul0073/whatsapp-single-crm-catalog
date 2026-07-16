<?php

use App\Modules\WhatsAppCloud\Http\Controllers\ChannelSetupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:channels.manage'])->group(function () {
    Route::get('channel-setup', [ChannelSetupController::class, 'index'])->name('whatsapp-cloud.channel-setup');
    Route::post('channel-setup', [ChannelSetupController::class, 'store'])->name('whatsapp-cloud.channel-setup.store');
    Route::post('channel-setup/generic', [ChannelSetupController::class, 'storeGeneric'])->name('whatsapp-cloud.channel-setup.store-generic');
    Route::post('channel-setup/embedded', [ChannelSetupController::class, 'embedded'])->name('whatsapp-cloud.channel-setup.embedded');
    Route::post('channel-setup/sync', [ChannelSetupController::class, 'sync'])->name('whatsapp-cloud.channel-setup.sync');
    Route::post('channel-setup/{channel}/test', [ChannelSetupController::class, 'test'])->name('whatsapp-cloud.channel-setup.test-channel');
    Route::delete('channel-setup', [ChannelSetupController::class, 'disconnect'])->name('whatsapp-cloud.channel-setup.disconnect');
    Route::delete('channel-setup/{channel}', [ChannelSetupController::class, 'disconnect'])->name('whatsapp-cloud.channel-setup.disconnect-channel');
});
