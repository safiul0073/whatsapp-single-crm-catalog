<?php

use App\Modules\Workspaces\Http\Controllers\Api\MobileWorkspaceController;
use App\Modules\Workspaces\Http\Controllers\Api\MobileDeviceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('mobile/workspaces', [MobileWorkspaceController::class, 'index']);
Route::middleware('auth:sanctum')->post('mobile/devices', [MobileDeviceController::class, 'store']);
Route::middleware('auth:sanctum')->delete('mobile/devices', [MobileDeviceController::class, 'destroy']);
