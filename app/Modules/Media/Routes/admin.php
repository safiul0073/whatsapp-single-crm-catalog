<?php

use App\Modules\Media\Http\Controllers\Admin\MediaController;
use Illuminate\Support\Facades\Route;

Route::get('media', [MediaController::class, 'index'])->name('media.index');
Route::get('media/browse', [MediaController::class, 'browse'])->name('media.browse');
Route::post('media/upload', [MediaController::class, 'upload'])->name('media.upload');
Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
