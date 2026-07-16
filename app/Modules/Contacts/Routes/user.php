<?php

use App\Modules\Contacts\Http\Controllers\User\ContactController;
use App\Modules\Contacts\Http\Controllers\User\ContactGroupController;
use App\Modules\Contacts\Http\Controllers\User\ContactImportController;
use App\Modules\Contacts\Http\Controllers\User\ContactTagController;
use Illuminate\Support\Facades\Route;

Route::middleware(['permission:contacts.view|contacts.assigned_only'])->group(function () {
    Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::get('contacts/export', [ContactController::class, 'export'])->name('contacts.export');
});

Route::middleware(['can:contacts.manage'])->group(function () {
    Route::post('contacts', [ContactController::class, 'store'])->name('contacts.store');
    Route::put('contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
    Route::delete('contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');
    Route::post('contacts/bulk/tag', [ContactController::class, 'bulkTag'])->name('contacts.bulk.tag');
    Route::post('contacts/bulk/group', [ContactController::class, 'bulkGroup'])->name('contacts.bulk.group');
    Route::delete('contacts/bulk/delete', [ContactController::class, 'bulkDelete'])->name('contacts.bulk.delete');

    Route::get('groups', [ContactGroupController::class, 'index'])->name('groups.index');
    Route::post('groups', [ContactGroupController::class, 'store'])->name('groups.store');
    Route::put('groups/{group}', [ContactGroupController::class, 'update'])->name('groups.update');
    Route::delete('groups/{group}', [ContactGroupController::class, 'destroy'])->name('groups.destroy');
    Route::post('groups/{group}/duplicate', [ContactGroupController::class, 'duplicate'])->name('groups.duplicate');
    Route::get('groups/{group}/preview', [ContactGroupController::class, 'preview'])->name('groups.preview');

    Route::get('tags', [ContactTagController::class, 'index'])->name('tags.index');
    Route::post('tags', [ContactTagController::class, 'store'])->name('tags.store');
    Route::put('tags/{tag}', [ContactTagController::class, 'update'])->name('tags.update');
    Route::delete('tags/{tag}', [ContactTagController::class, 'destroy'])->name('tags.destroy');

    Route::post('imports/upload', [ContactImportController::class, 'upload'])->name('imports.upload');
    Route::post('imports/sheets', [ContactImportController::class, 'sheets'])->name('imports.sheets');
    Route::get('imports/history', [ContactImportController::class, 'history'])->name('imports.history');
    Route::post('imports/{import}/process', [ContactImportController::class, 'process'])->name('imports.process');
    Route::get('imports/{import}', [ContactImportController::class, 'show'])->name('imports.show');
});
