<?php

use App\Modules\Frontend\Http\Controllers\Admin\FrontendMenusController;
use App\Modules\Frontend\Http\Controllers\Admin\FrontendPagesController;
use App\Modules\Frontend\Http\Controllers\Admin\FrontendSectionsController;
use App\Modules\Frontend\Http\Controllers\Admin\FrontendThemesController;
use Illuminate\Support\Facades\Route;

Route::get('frontend-themes', [FrontendThemesController::class, 'index'])->name('frontend-themes.index');
Route::put('frontend-themes', [FrontendThemesController::class, 'update'])->name('frontend-themes.update');
Route::post('frontend-menus/{frontendMenu}/publish', [FrontendMenusController::class, 'publish'])->name('frontend-menus.publish');
Route::resource('frontend-menus', FrontendMenusController::class)->parameters(['frontend-menus' => 'frontendMenu'])->except(['show']);
Route::resource('frontend-sections', FrontendSectionsController::class)->parameters(['frontend-sections' => 'frontendSection'])->except(['show']);
Route::get('frontend-pages/{frontendPage}/publish', [FrontendPagesController::class, 'publish'])->name('frontend-pages.publish');
Route::resource('frontend-pages', FrontendPagesController::class)->parameters(['frontend-pages' => 'frontendPage'])->except(['show']);
