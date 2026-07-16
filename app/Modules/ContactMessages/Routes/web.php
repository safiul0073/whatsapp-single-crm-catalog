<?php

use App\Modules\ContactMessages\Http\Controllers\ContactMessageSubmissionController;
use Illuminate\Support\Facades\Route;

Route::post('contact', [ContactMessageSubmissionController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('contact.submit');
