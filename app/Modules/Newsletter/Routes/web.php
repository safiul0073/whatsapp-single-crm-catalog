<?php

use App\Modules\Newsletter\Http\Controllers\NewsletterController;
use Illuminate\Support\Facades\Route;

Route::post('newsletter/subscribe', [NewsletterController::class, 'subscribe'])->middleware('throttle:5,1')->name('newsletter.subscribe');
