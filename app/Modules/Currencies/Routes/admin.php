<?php

use App\Modules\Currencies\Http\Controllers\Admin\CurrenciesController;
use Illuminate\Support\Facades\Route;

Route::post('currencies/bulk-delete', [CurrenciesController::class, 'bulkDelete'])->name('currencies.bulk-delete');
Route::post('currencies/sync-rates', [CurrenciesController::class, 'syncRates'])->name('currencies.sync-rates');
Route::resource('currencies', CurrenciesController::class);
Route::post('currencies/{currency}/toggle-status', [CurrenciesController::class, 'toggleStatus'])->name('currencies.toggle-status');
Route::post('currencies/{currency}/set-default', [CurrenciesController::class, 'setDefault'])->name('currencies.set-default');
