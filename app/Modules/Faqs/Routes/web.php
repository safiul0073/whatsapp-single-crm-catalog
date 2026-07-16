<?php

use App\Modules\Faqs\Http\Controllers\FaqPageController;
use Illuminate\Support\Facades\Route;

Route::get('faq', [FaqPageController::class, 'index'])->name('faq.index');
