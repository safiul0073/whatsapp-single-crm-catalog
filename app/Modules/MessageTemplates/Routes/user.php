<?php

use App\Modules\MessageTemplates\Http\Controllers\User\MessageTemplateController;
use Illuminate\Support\Facades\Route;

Route::group([], function (): void {
    Route::get('templates', [MessageTemplateController::class, 'index'])->name('message-templates.index');
    Route::get('templates/create', [MessageTemplateController::class, 'create'])->name('message-templates.create');
    Route::post('templates', [MessageTemplateController::class, 'store'])->name('message-templates.store');
    Route::post('templates/generate', [MessageTemplateController::class, 'generate'])->name('message-templates.generate');
    Route::get('templates/{template}/edit', [MessageTemplateController::class, 'edit'])->name('message-templates.edit');
    Route::put('templates/{template}', [MessageTemplateController::class, 'update'])->name('message-templates.update');
    Route::delete('templates/{template}', [MessageTemplateController::class, 'destroy'])->name('message-templates.destroy');
    Route::post('templates/{template}/submit', [MessageTemplateController::class, 'submit'])->name('message-templates.submit');
    Route::post('templates/sync', [MessageTemplateController::class, 'sync'])->name('message-templates.sync');
});
