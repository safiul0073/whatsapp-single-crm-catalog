<?php

use App\Modules\Languages\Http\Controllers\Admin\LanguagesController;
use Illuminate\Support\Facades\Route;

Route::resource('languages', LanguagesController::class)->except(['show']);
Route::post('languages/{language}/toggle-status', [LanguagesController::class, 'toggleStatus'])->name('languages.toggle-status');
Route::post('languages/{language}/set-default', [LanguagesController::class, 'setDefault'])->name('languages.set-default');
Route::get('languages/{language}/translations', [LanguagesController::class, 'translations'])->name('languages.translations');
Route::put('languages/{language}/translations', [LanguagesController::class, 'updateTranslations'])->name('languages.translations.update');
