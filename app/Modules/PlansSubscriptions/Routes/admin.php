<?php

use App\Modules\PlansSubscriptions\Http\Controllers\Admin\PlanController;
use App\Modules\PlansSubscriptions\Http\Controllers\Admin\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::resource('plans', PlanController::class)->except(['show']);
Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
