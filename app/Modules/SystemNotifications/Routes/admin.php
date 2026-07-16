<?php

use App\Modules\SystemNotifications\Http\Controllers\Admin\SystemNotificationController;
use Illuminate\Support\Facades\Route;

Route::get('system-notifications', [SystemNotificationController::class, 'index'])->name('system-notifications.index');
Route::get('system-notifications/unread-count', [SystemNotificationController::class, 'unreadCount'])->name('system-notifications.unread-count');
Route::get('system-notifications/recent', [SystemNotificationController::class, 'recent'])->name('system-notifications.recent');
Route::post('system-notifications/{notification}/mark-read', [SystemNotificationController::class, 'markRead'])->name('system-notifications.mark-read');
Route::post('system-notifications/mark-all-read', [SystemNotificationController::class, 'markAllRead'])->name('system-notifications.mark-all-read');
Route::post('system-notifications/send', [SystemNotificationController::class, 'send'])->name('system-notifications.send');
