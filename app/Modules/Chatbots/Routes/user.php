<?php

use App\Modules\Chatbots\Http\Controllers\User\ChatbotController;
use App\Modules\Chatbots\Http\Controllers\User\ChatbotWidgetController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:chatbots.manage'])->group(function () {
    Route::get('chatbots', [ChatbotController::class, 'index'])->name('chatbots.index');
    Route::get('chatbots/create', [ChatbotController::class, 'create'])->name('chatbots.create');
    Route::post('chatbots', [ChatbotController::class, 'store'])->name('chatbots.store');
    Route::post('chatbots/persona/generate', [ChatbotController::class, 'generatePersona'])->name('chatbots.persona.generate');
    Route::get('chatbots/{chatbot}/config', [ChatbotController::class, 'config'])->name('chatbots.config');
    Route::put('chatbots/{chatbot}', [ChatbotController::class, 'update'])->name('chatbots.update');
    Route::patch('chatbots/{chatbot}/toggle', [ChatbotController::class, 'toggle'])->name('chatbots.toggle');
    Route::delete('chatbots/{chatbot}', [ChatbotController::class, 'destroy'])->name('chatbots.destroy');
    Route::post('chatbots/{chatbot}/test', [ChatbotController::class, 'test'])->name('chatbots.test');

    Route::get('chatbots/widgets', [ChatbotWidgetController::class, 'index'])->name('chatbots.widgets.index');
    Route::get('chatbots/widgets/create', [ChatbotWidgetController::class, 'create'])->name('chatbots.widgets.create');
    Route::post('chatbots/widgets', [ChatbotWidgetController::class, 'store'])->name('chatbots.widgets.store');
    Route::get('chatbots/widgets/{widget}/edit', [ChatbotWidgetController::class, 'edit'])->name('chatbots.widgets.edit');
    Route::put('chatbots/widgets/{widget}', [ChatbotWidgetController::class, 'update'])->name('chatbots.widgets.update');
    Route::delete('chatbots/widgets/{widget}', [ChatbotWidgetController::class, 'destroy'])->name('chatbots.widgets.destroy');
});
