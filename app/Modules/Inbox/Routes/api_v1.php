<?php

use App\Modules\Inbox\Http\Controllers\Api\MobileInboxController;
use App\Modules\Workspaces\Http\Middleware\ResolveMobileWorkspace;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile/workspaces/{workspace}/inbox')->middleware(['auth:sanctum', ResolveMobileWorkspace::class])->group(function (): void {
    Route::get('conversations', [MobileInboxController::class, 'index']);
    Route::get('conversations/{conversation}', [MobileInboxController::class, 'show']);
    Route::post('conversations/{conversation}/read', [MobileInboxController::class, 'markRead']);
    Route::post('conversations/{conversation}/messages', [MobileInboxController::class, 'store']);
    Route::post('conversations/{conversation}/ai-reply', [MobileInboxController::class, 'aiReply']);
});
