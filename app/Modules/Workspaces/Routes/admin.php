<?php

use App\Modules\Workspaces\Http\Controllers\Admin\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('clients', [WorkspaceController::class, 'index'])->name('workspaces.index');
