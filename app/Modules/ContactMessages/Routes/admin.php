<?php

use App\Modules\ContactMessages\Http\Controllers\Admin\ContactMessagesController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:contact-messages.view')->group(function () {
    Route::get('contact-messages', [ContactMessagesController::class, 'index'])->name('contact-messages.index');
    Route::get('contact-messages/{contactMessage}', [ContactMessagesController::class, 'show'])->name('contact-messages.show');
});

Route::post('contact-messages/{contactMessage}/status', [ContactMessagesController::class, 'updateStatus'])
    ->middleware('permission:contact-messages.manage')
    ->name('contact-messages.update-status');

Route::post('contact-messages/{contactMessage}/reply', [ContactMessagesController::class, 'reply'])
    ->middleware('permission:contact-messages.manage')
    ->name('contact-messages.reply');

Route::post('contact-messages/{contactMessage}/subscribe-newsletter', [ContactMessagesController::class, 'subscribeNewsletter'])
    ->middleware('permission:contact-messages.manage')
    ->name('contact-messages.subscribe-newsletter');

Route::delete('contact-messages/{contactMessage}', [ContactMessagesController::class, 'destroy'])
    ->middleware('permission:contact-messages.delete')
    ->name('contact-messages.destroy');

Route::post('contact-messages/bulk-delete', [ContactMessagesController::class, 'bulkDelete'])
    ->middleware('permission:contact-messages.delete')
    ->name('contact-messages.bulk-delete');
