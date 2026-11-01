<?php

use App\Modules\Commerce\Http\Controllers\Api\MobileLiveStreamController;
use App\Modules\Commerce\Http\Controllers\Api\MobileOrderController;
use App\Modules\Workspaces\Http\Middleware\ResolveMobileWorkspace;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile/workspaces/{workspace}/orders')->middleware(['auth:sanctum', ResolveMobileWorkspace::class])->group(function (): void {
    Route::get('/', [MobileOrderController::class, 'index']);
    Route::get('{order}', [MobileOrderController::class, 'show']);
    Route::get('{order}/invoice', [MobileOrderController::class, 'invoice']);
    Route::post('{order}/invoice/send', [MobileOrderController::class, 'sendInvoice']);
    Route::put('{order}/status', [MobileOrderController::class, 'transition']);
});

Route::prefix('mobile/workspaces/{workspace}/lives')->middleware(['auth:sanctum', ResolveMobileWorkspace::class])->group(function (): void {
    Route::get('/', [MobileLiveStreamController::class, 'index']);
    Route::put('settings', [MobileLiveStreamController::class, 'updateSettings']);
    Route::post('facebook/verify', [MobileLiveStreamController::class, 'verifyFacebook']);
    Route::post('/', [MobileLiveStreamController::class, 'store']);
    Route::get('{live}', [MobileLiveStreamController::class, 'show']);
    Route::post('{live}/start', [MobileLiveStreamController::class, 'start']);
    Route::post('{live}/signal', [MobileLiveStreamController::class, 'signal']);
    Route::post('{live}/pin-product', [MobileLiveStreamController::class, 'pinProduct']);
    Route::post('{live}/comments', [MobileLiveStreamController::class, 'comment']);
    Route::post('{live}/facebook/sync', [MobileLiveStreamController::class, 'syncFacebook']);
    Route::post('{live}/chunk', [MobileLiveStreamController::class, 'chunk']);
    Route::post('{live}/stop', [MobileLiveStreamController::class, 'stop']);
    Route::post('{live}/toggle-slider', [MobileLiveStreamController::class, 'toggleSlider']);
    Route::delete('{live}', [MobileLiveStreamController::class, 'destroy']);
});
