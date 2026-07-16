<?php

use App\Modules\KnowledgeBases\Http\Controllers\User\KnowledgeBaseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:chatbots.manage'])->group(function () {
    Route::get('knowledge-bases', [KnowledgeBaseController::class, 'index'])->name('knowledge-bases.index');
    Route::post('knowledge-bases', [KnowledgeBaseController::class, 'store'])->name('knowledge-bases.store');
    Route::get('knowledge-bases/{knowledgeBase}', [KnowledgeBaseController::class, 'show'])->name('knowledge-bases.show');
    Route::put('knowledge-bases/{knowledgeBase}', [KnowledgeBaseController::class, 'update'])->name('knowledge-bases.update');
    Route::delete('knowledge-bases/{knowledgeBase}', [KnowledgeBaseController::class, 'destroy'])->name('knowledge-bases.destroy');

    Route::post('knowledge-bases/{knowledgeBase}/sources', [KnowledgeBaseController::class, 'storeSource'])->name('knowledge-bases.sources.store');
    Route::post('knowledge-base-sources/{source}/reindex', [KnowledgeBaseController::class, 'reindexSource'])->name('knowledge-bases.sources.reindex');
    Route::delete('knowledge-base-sources/{source}', [KnowledgeBaseController::class, 'destroySource'])->name('knowledge-bases.sources.destroy');
});
