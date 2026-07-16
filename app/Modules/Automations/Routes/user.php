<?php

use App\Modules\Automations\Http\Controllers\User\AutomationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:automations.manage'])->group(function () {
    Route::get('automations', [AutomationController::class, 'index'])->name('automations.index');
    Route::get('automations/create', [AutomationController::class, 'create'])->name('automations.create');
    Route::get('automations/builder', [AutomationController::class, 'create'])->name('automations.builder');
    Route::post('automations/generate', [AutomationController::class, 'generate'])->name('automations.generate');
    Route::post('automations/test-flow', [AutomationController::class, 'testFlow'])->name('automations.test-flow');
    Route::post('automations', [AutomationController::class, 'store'])->name('automations.store');
    Route::get('automations/{automation}/edit', [AutomationController::class, 'edit'])->name('automations.edit');
    Route::put('automations/{automation}', [AutomationController::class, 'update'])->name('automations.update');
    Route::patch('automations/{automation}/toggle', [AutomationController::class, 'toggle'])->name('automations.toggle');
    Route::delete('automations/{automation}', [AutomationController::class, 'destroy'])->name('automations.destroy');
});
