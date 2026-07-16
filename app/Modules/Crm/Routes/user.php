<?php

use App\Modules\Crm\Http\Controllers\User\CrmController;
use Illuminate\Support\Facades\Route;

Route::get('crm', [CrmController::class, 'index'])->name('crm.index');
Route::get('inbox/conversations/{conversation}/crm', [CrmController::class, 'sidebar'])->name('inbox.conversations.crm');

Route::prefix('crm')->name('crm.')->group(function (): void {
    Route::post('leads', [CrmController::class, 'storeLead'])->name('leads.store');
    Route::patch('leads/{lead}/stage', [CrmController::class, 'moveLead'])->name('leads.stage');
    Route::patch('leads/{lead}/assign', [CrmController::class, 'assignLead'])->name('leads.assign');
    Route::post('leads/{lead}/notes', [CrmController::class, 'addNote'])->name('leads.notes.store');
    Route::post('leads/{lead}/won', [CrmController::class, 'markWon'])->name('leads.won');
    Route::post('leads/{lead}/lost', [CrmController::class, 'markLost'])->name('leads.lost');
    Route::post('tasks', [CrmController::class, 'storeTask'])->name('tasks.store');
    Route::post('tasks/{task}/complete', [CrmController::class, 'completeTask'])->name('tasks.complete');
    Route::post('tasks/{task}/cancel', [CrmController::class, 'cancelTask'])->name('tasks.cancel');
    Route::post('pipelines', [CrmController::class, 'storePipeline'])->name('pipelines.store');
    Route::put('pipelines/{pipeline}', [CrmController::class, 'updatePipeline'])->name('pipelines.update');
    Route::delete('pipelines/{pipeline}', [CrmController::class, 'destroyPipeline'])->name('pipelines.destroy');
    Route::post('pipelines/{pipeline}/stages', [CrmController::class, 'storeStage'])->name('stages.store');
    Route::put('stages/{stage}', [CrmController::class, 'updateStage'])->name('stages.update');
    Route::delete('stages/{stage}', [CrmController::class, 'destroyStage'])->name('stages.destroy');
});
