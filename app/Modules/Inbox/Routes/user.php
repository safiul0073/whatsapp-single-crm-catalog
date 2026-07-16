<?php

use App\Modules\Inbox\Http\Controllers\User\InboxController;
use Illuminate\Support\Facades\Route;

Route::middleware(['permission:inbox.view|inbox.assigned_only'])->group(function () {
    Route::get('inbox', [InboxController::class, 'index'])->name('inbox.index');
    Route::get('inbox/conversations', [InboxController::class, 'conversations'])->name('inbox.conversations');
    Route::get('inbox/conversations/{conversation}', [InboxController::class, 'show'])->name('inbox.conversations.show');
    Route::post('inbox/contacts/{contact}/conversation', [InboxController::class, 'contactConversation'])->name('inbox.contacts.conversation');
});

Route::middleware(['can:inbox.reply'])->group(function () {
    Route::post('inbox/conversations/{conversation}/messages', [InboxController::class, 'storeMessage'])->name('inbox.conversations.messages.store');
    Route::post('inbox/conversations/{conversation}/ai-reply', [InboxController::class, 'aiReply'])->name('inbox.conversations.ai-reply');
    Route::post('inbox/conversations/{conversation}/automation', [InboxController::class, 'automation'])->name('inbox.conversations.automation');
});
