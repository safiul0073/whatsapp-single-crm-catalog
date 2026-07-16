<?php

use App\Modules\Faqs\Http\Controllers\Admin\FaqsController;
use Illuminate\Support\Facades\Route;

Route::post('faqs/bulk-delete', [FaqsController::class, 'bulkDelete'])->name('faqs.bulk-delete');
Route::resource('faqs', FaqsController::class)
    ->parameters(['faqs' => 'faq'])
    ->except(['show']);
Route::post('faqs/{faq}/toggle-status', [FaqsController::class, 'toggleStatus'])->name('faqs.toggle-status');
