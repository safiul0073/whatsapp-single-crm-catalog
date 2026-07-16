<?php

use App\Modules\AuthApi\Http\Controllers\Api\V1\Auth\AuthenticatedUserController;
use App\Modules\AuthApi\Http\Controllers\Api\V1\Auth\LoginController;
use App\Modules\AuthApi\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Modules\AuthApi\Http\Controllers\Api\V1\Auth\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::middleware('throttle:api')->group(function (): void {
        Route::post('login', [LoginController::class, 'store'])->name('login');
        Route::post('forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
        Route::post('reset-password', [PasswordResetController::class, 'reset'])->name('password.reset');

        Route::post('2fa/verify', [TwoFactorAuthenticationController::class, 'verifyChallenge'])
            ->name('2fa.verify');
        Route::post('2fa/recovery', [TwoFactorAuthenticationController::class, 'verifyRecoveryCode'])
            ->name('2fa.recovery');
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthenticatedUserController::class, 'show'])->name('me');
        Route::post('logout', [AuthenticatedUserController::class, 'destroy'])->name('logout');

        Route::prefix('2fa')->name('2fa.')->group(function (): void {
            Route::post('setup', [TwoFactorAuthenticationController::class, 'setup'])->name('setup');
            Route::post('confirm', [TwoFactorAuthenticationController::class, 'confirmSetup'])->name('confirm');
            Route::post('disable', [TwoFactorAuthenticationController::class, 'disable'])->name('disable');
        });
    });
});
