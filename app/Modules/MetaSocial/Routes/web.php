<?php

use App\Modules\MetaSocial\Http\Controllers\MetaWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('webhooks/meta/{token}', [MetaWebhookController::class, 'verify'])->name('webhooks.meta.verify');
Route::post('webhooks/meta/{token}', [MetaWebhookController::class, 'receive'])->name('webhooks.meta.receive');
