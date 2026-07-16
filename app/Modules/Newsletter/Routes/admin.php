<?php

use App\Modules\Newsletter\Http\Controllers\Admin\SendNewsletterController;
use App\Modules\Newsletter\Http\Controllers\Admin\SubscribersController;
use Illuminate\Support\Facades\Route;

Route::get('subscribers/send', [SendNewsletterController::class, 'create'])->name('subscribers.send.create');
Route::post('subscribers/send', [SendNewsletterController::class, 'store'])->name('subscribers.send.store');
Route::post('subscribers/bulk-delete', [SubscribersController::class, 'bulkDelete'])->name('subscribers.bulk-delete');
Route::resource('subscribers', SubscribersController::class)
    ->parameters(['subscribers' => 'subscriber'])
    ->only(['index', 'destroy']);
Route::post('subscribers/{subscriber}/toggle-status', [SubscribersController::class, 'toggleStatus'])->name('subscribers.toggle-status');
