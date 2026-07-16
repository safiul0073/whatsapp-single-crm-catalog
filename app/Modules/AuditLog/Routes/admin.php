<?php

use App\Modules\AuditLog\Http\Controllers\Admin\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');
