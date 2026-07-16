<?php

use App\Modules\Staffs\Http\Controllers\Admin\RolesController;
use App\Modules\Staffs\Http\Controllers\Admin\StaffsController;
use Illuminate\Support\Facades\Route;

Route::resource('staffs', StaffsController::class);
Route::post('staffs/bulk-delete', [StaffsController::class, 'bulkDelete'])->name('staffs.bulk-delete');
Route::post('staffs/bulk-toggle-status', [StaffsController::class, 'bulkToggleStatus'])->name('staffs.bulk-toggle-status');
Route::post('staffs/{staff}/toggle-status', [StaffsController::class, 'toggleStatus'])->name('staffs.toggle-status');

Route::resource('roles', RolesController::class)->except(['show']);
