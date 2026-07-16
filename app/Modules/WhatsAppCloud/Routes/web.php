<?php

use App\Modules\WhatsAppCloud\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('webhooks/whatsapp/{account}', [WebhookController::class, 'verify'])->name('webhooks.whatsapp.verify');
Route::post('webhooks/whatsapp/{account}', [WebhookController::class, 'receive'])->name('webhooks.whatsapp.receive');
