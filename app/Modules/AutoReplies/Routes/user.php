<?php

use App\Modules\AutoReplies\Http\Controllers\User\AutoReplyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:automations.manage'])->group(function () {
    Route::get('auto-replies', [AutoReplyController::class, 'index'])->name('auto-replies.index');
    Route::get('auto-replies/create', [AutoReplyController::class, 'create'])->name('auto-replies.create');
    Route::post('auto-replies', [AutoReplyController::class, 'store'])->name('auto-replies.store');
    Route::get('auto-replies/{autoReply}/edit', [AutoReplyController::class, 'edit'])->name('auto-replies.edit');
    Route::put('auto-replies/{autoReply}', [AutoReplyController::class, 'update'])->name('auto-replies.update');
    Route::patch('auto-replies/{autoReply}/toggle', [AutoReplyController::class, 'toggle'])->name('auto-replies.toggle');
    Route::delete('auto-replies/{autoReply}', [AutoReplyController::class, 'destroy'])->name('auto-replies.destroy');
});
