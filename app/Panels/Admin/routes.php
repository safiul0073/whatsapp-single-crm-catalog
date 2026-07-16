<?php

use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\ImpersonationController;
use App\Panels\Admin\Controllers\Auth\ForgotPasswordController;
use App\Panels\Admin\Controllers\Auth\LoginController;
use App\Panels\Admin\Controllers\Auth\LogoutController;
use App\Panels\Admin\Controllers\Auth\ResetPasswordController;
use App\Panels\Admin\Controllers\DashboardController;
use App\Panels\Admin\Controllers\ProfileController;
use App\Panels\Admin\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Auth Routes (no auth middleware required)
|--------------------------------------------------------------------------
*/
Route::withoutMiddleware(['auth:admin', '2fa', 'panel:admin'])->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
        Route::post('login', [LoginController::class, 'login'])->name('login.submit')->middleware('throttle:login');

        Route::get('forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
        Route::post('forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');

        Route::get('reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
        Route::post('reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');
    });

    Route::post('logout', [LogoutController::class, 'logout'])->name('logout');
});

// Two-Factor Challenge (authenticated but not yet 2FA verified)
Route::withoutMiddleware(['2fa', 'panel:admin'])->group(function () {
    Route::get('two-factor-challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('two-factor-challenge', [TwoFactorController::class, 'verify'])->name('two-factor.verify');
    Route::post('two-factor-challenge/recovery', [TwoFactorController::class, 'verifyRecoveryCode'])->name('two-factor.verify-recovery');
});

/*
|--------------------------------------------------------------------------
| Admin Protected Routes (auth:admin + panel:admin middleware applied by PanelServiceProvider)
|--------------------------------------------------------------------------
*/
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// Global Search
Route::get('global-search', [GlobalSearchController::class, 'search'])->name('global-search');

// Impersonation
Route::post('impersonation/stop', [ImpersonationController::class, 'stop'])->name('impersonation.stop');

// User Management (users table — regular website users)
Route::resource('users', UserController::class);
Route::post('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');

// Profile
Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
Route::delete('profile/sessions/{session}', [ProfileController::class, 'revokeSession'])->name('profile.sessions.revoke');
Route::delete('profile/sessions', [ProfileController::class, 'revokeAllSessions'])->name('profile.sessions.revoke-all');

// Two-Factor Authentication Management
Route::get('two-factor/setup', [TwoFactorController::class, 'setup'])->name('two-factor.setup');
Route::post('two-factor/enable', [TwoFactorController::class, 'enable'])->name('two-factor.enable');
Route::post('two-factor/disable', [TwoFactorController::class, 'disable'])->name('two-factor.disable');
