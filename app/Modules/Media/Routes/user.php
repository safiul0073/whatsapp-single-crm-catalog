<?php

use App\Modules\Media\Http\Controllers\User\MediaController;
use Illuminate\Support\Facades\Route;

Route::get('media/browse', [MediaController::class, 'browse'])->name('media.browse');
Route::post('media/upload', [MediaController::class, 'upload'])->name('media.upload');
