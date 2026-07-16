<?php

use App\Modules\PaymentGateways\Http\Controllers\Admin\PaymentsController;
use App\Modules\PaymentGateways\Http\Controllers\Admin\WebhookLogsController;
use Illuminate\Support\Facades\Route;

Route::get('payments', [PaymentsController::class, 'index'])->name('payments.index');
Route::get('payments/{payment}', [PaymentsController::class, 'show'])->name('payments.show');
Route::post('payments/{payment}/approve', [PaymentsController::class, 'approve'])->name('payments.approve');
Route::post('payments/{payment}/reject', [PaymentsController::class, 'reject'])->name('payments.reject');

Route::get('webhook-logs', [WebhookLogsController::class, 'index'])->name('webhook-logs.index');
Route::get('webhook-logs/{webhookLog}', [WebhookLogsController::class, 'show'])->name('webhook-logs.show');
