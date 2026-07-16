<?php

use App\Modules\SupportTickets\Http\Controllers\User\TicketController;
use Illuminate\Support\Facades\Route;

Route::get('support-tickets', [TicketController::class, 'index'])->name('support-tickets.index');
Route::get('support-tickets/create', [TicketController::class, 'create'])->name('support-tickets.create');
Route::post('support-tickets', [TicketController::class, 'store'])->name('support-tickets.store');
Route::get('support-tickets/{ticket}', [TicketController::class, 'show'])->name('support-tickets.show');
Route::post('support-tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('support-tickets.reply');
Route::post('support-tickets/{ticket}/reply-ajax', [TicketController::class, 'replyAjax'])->name('support-tickets.reply-ajax');
Route::get('support-tickets/{ticket}/poll', [TicketController::class, 'poll'])->name('support-tickets.poll');
