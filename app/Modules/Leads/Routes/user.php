<?php

use App\Modules\Leads\Http\Controllers\User\LeadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:leads.view'])->group(function () {
    Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
});

Route::middleware(['can:leads.manage'])->group(function () {
    Route::post('leads/generate', [LeadController::class, 'generate'])->name('leads.generate');
    Route::post('leads/bulk-convert', [LeadController::class, 'bulkConvert'])->name('leads.bulk-convert');
    Route::post('leads/bulk-delete', [LeadController::class, 'bulkDelete'])->name('leads.bulk-delete');
    Route::put('leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
    Route::delete('leads/{lead}', [LeadController::class, 'destroy'])->name('leads.destroy');
    Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');
    Route::post('leads/{lead}/send-message', [LeadController::class, 'sendMessage'])->name('leads.send-message');
});
