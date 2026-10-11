<?php

use App\Modules\Commerce\Http\Controllers\Api\MobileOrderController;
use App\Modules\Workspaces\Http\Middleware\ResolveMobileWorkspace;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile/workspaces/{workspace}/orders')->middleware(['auth:sanctum', ResolveMobileWorkspace::class])->group(function (): void {
    Route::get('/', [MobileOrderController::class, 'index']);
    Route::get('{order}', [MobileOrderController::class, 'show']);
    Route::put('{order}/status', [MobileOrderController::class, 'transition']);
});
