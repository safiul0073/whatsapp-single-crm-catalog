<?php

use App\Modules\MarketingChannels\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('webhooks/channels/{provider}/{webhookCode}', [WebhookController::class, 'verify'])->name('webhooks.channels.account.verify');
Route::post('webhooks/channels/{provider}/{webhookCode}', [WebhookController::class, 'receive'])->name('webhooks.channels.account.receive');
Route::get('webhooks/channels/{provider}', [WebhookController::class, 'verify'])->name('webhooks.channels.verify');
Route::post('webhooks/channels/{provider}', [WebhookController::class, 'receive'])->name('webhooks.channels.receive');
