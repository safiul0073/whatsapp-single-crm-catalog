<?php

use App\Modules\LoginActivity\Http\Controllers\Admin\LoginActivityController;
use Illuminate\Support\Facades\Route;

Route::get('login-activity', [LoginActivityController::class, 'index'])->name('login-activity.index');
