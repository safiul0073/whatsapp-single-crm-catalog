<?php

use App\Modules\SocialWidgets\Http\Controllers\User\SocialWidgetController;
use App\Modules\SocialWidgets\Http\Controllers\User\SocialWidgetEditorController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:meta-social.manage'])->group(function (): void {
    Route::get('widgets', [SocialWidgetController::class, 'library'])->name('social-widgets.library');

    Route::prefix('channels/{provider}/widgets')->whereIn('provider', ['instagram'])->group(function (): void {
        Route::get('/', [SocialWidgetController::class, 'index'])->name('social-widgets.index');
        Route::get('layouts', [SocialWidgetController::class, 'layouts'])->name('social-widgets.layouts');
        Route::post('/', [SocialWidgetController::class, 'store'])->name('social-widgets.store');
    });

    Route::get('social-widgets/{widget}/edit', [SocialWidgetEditorController::class, 'edit'])->name('social-widgets.edit');
    Route::put('social-widgets/{widget}', [SocialWidgetEditorController::class, 'update'])->name('social-widgets.update');
    Route::post('social-widgets/{widget}/publish', [SocialWidgetEditorController::class, 'publish'])->name('social-widgets.publish');
    Route::post('social-widgets/{widget}/source', [SocialWidgetEditorController::class, 'source'])->name('social-widgets.source');
    Route::delete('social-widgets/{widget}', [SocialWidgetController::class, 'destroy'])->name('social-widgets.destroy');
});
