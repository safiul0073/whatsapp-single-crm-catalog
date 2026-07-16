<?php

use App\Modules\SupportTickets\Http\Controllers\Admin\TicketController;
use Illuminate\Support\Facades\Route;

Route::post('support-tickets/bulk-delete', [TicketController::class, 'bulkDelete'])->name('support-tickets.bulk-delete');
Route::get('support-tickets', [TicketController::class, 'index'])->name('support-tickets.index');
Route::get('support-tickets/{ticket}', [TicketController::class, 'show'])->name('support-tickets.show');
Route::post('support-tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('support-tickets.reply');
Route::post('support-tickets/{ticket}/reply-ajax', [TicketController::class, 'replyAjax'])->name('support-tickets.reply-ajax');
Route::get('support-tickets/{ticket}/poll', [TicketController::class, 'poll'])->name('support-tickets.poll');
Route::post('support-tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->name('support-tickets.update-status');
Route::delete('support-tickets/{ticket}', [TicketController::class, 'destroy'])->name('support-tickets.destroy');
