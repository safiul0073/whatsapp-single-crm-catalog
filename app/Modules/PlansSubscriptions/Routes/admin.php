<?php

use App\Modules\PlansSubscriptions\Http\Controllers\Admin\PlanController;
use App\Modules\PlansSubscriptions\Http\Controllers\Admin\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::resource('plans', PlanController::class)->except(['show']);
Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
Route::get('subscriptions/{subscription}', [SubscriptionController::class, 'show'])->name('subscriptions.show');
Route::post('subscriptions/{subscription}/plan', [SubscriptionController::class, 'changePlan'])->name('subscriptions.change-plan');
Route::post('subscriptions/{subscription}/extend', [SubscriptionController::class, 'extend'])->name('subscriptions.extend');
Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
Route::post('subscriptions/{subscription}/resume', [SubscriptionController::class, 'resume'])->name('subscriptions.resume');
Route::post('subscriptions/{subscription}/reset-usage', [SubscriptionController::class, 'resetUsage'])->name('subscriptions.reset-usage');
