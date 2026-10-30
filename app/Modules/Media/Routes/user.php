<?php

use App\Modules\Media\Http\Controllers\User\MediaController;
use Illuminate\Support\Facades\Route;

Route::get('media/browse', [MediaController::class, 'browse'])->name('media.browse');
Route::post('media/upload', [MediaController::class, 'upload'])->name('media.upload');
Route::delete('media', [MediaController::class, 'bulkDestroy'])->name('media.bulk-destroy');
Route::delete('media/{media}', [MediaController::class, 'destroy'])->whereNumber('media')->name('media.destroy');
