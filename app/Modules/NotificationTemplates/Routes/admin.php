<?php

use App\Modules\NotificationTemplates\Http\Controllers\Admin\NotificationLogsController;
use App\Modules\NotificationTemplates\Http\Controllers\Admin\NotificationTemplatesController;
use App\Modules\NotificationTemplates\Http\Controllers\Admin\SendNotificationController;
use Illuminate\Support\Facades\Route;

Route::get('notification-send', [SendNotificationController::class, 'create'])->name('notification-send.create');
Route::post('notification-send', [SendNotificationController::class, 'store'])->name('notification-send.store');

Route::resource('notification-templates', NotificationTemplatesController::class)->only(['index', 'edit', 'update']);
Route::post('notification-templates/{notification_template}/toggle-status', [NotificationTemplatesController::class, 'toggleStatus'])->name('notification-templates.toggle-status');
Route::post('notification-templates/{notification_template}/preview', [NotificationTemplatesController::class, 'preview'])->name('notification-templates.preview');

Route::get('notification-logs', [NotificationLogsController::class, 'index'])->name('notification-logs.index');
Route::get('notification-logs/{notificationLog}', [NotificationLogsController::class, 'show'])->name('notification-logs.show');
