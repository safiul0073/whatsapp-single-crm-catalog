<?php

use App\Modules\Workspaces\Http\Controllers\User\TeamController;
use App\Modules\Workspaces\Http\Controllers\User\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:settings.view'])->group(function () {
    Route::get('workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::put('workspaces/{workspace}', [WorkspaceController::class, 'update'])->name('workspaces.update');
    Route::patch('workspaces/{workspace}/status', [WorkspaceController::class, 'toggleStatus'])->name('workspaces.toggle-status');
    Route::delete('workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');
    Route::post('workspaces/{workspace}/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');
    Route::post('workspaces/{workspace}/leave', [WorkspaceController::class, 'leave'])->name('workspaces.leave');
    Route::post('workspaces/invitations/{invitation}/accept', [WorkspaceController::class, 'acceptInvite'])->name('workspaces.invitations.accept');
    Route::post('workspaces/invitations/{invitation}/decline', [WorkspaceController::class, 'declineInvite'])->name('workspaces.invitations.decline');
});

Route::middleware(['permission:team.manage|team.manage.staff_only'])->group(function () {
    Route::get('team', [TeamController::class, 'index'])->name('workspaces.team');
    Route::post('team', [TeamController::class, 'store'])->name('workspaces.team.store');
    Route::put('team/roles/{role}/permissions', [TeamController::class, 'updateRolePermissions'])
        ->where('role', 'administrator|manager|staff')
        ->middleware('can:team.manage')
        ->name('workspaces.team.roles.permissions.update');
    Route::get('team/{member}/permissions', [TeamController::class, 'permissions'])
        ->middleware('can:team.manage')
        ->name('workspaces.team.permissions');
    Route::put('team/{member}/permissions', [TeamController::class, 'updateMemberPermissions'])
        ->middleware('can:team.manage')
        ->name('workspaces.team.permissions.update');
    Route::put('team/{member}', [TeamController::class, 'update'])->name('workspaces.team.update');
    Route::delete('team/{member}', [TeamController::class, 'destroy'])->name('workspaces.team.destroy');
    Route::post('team/invite', [TeamController::class, 'invite'])->name('workspaces.team.invite');
    Route::post('team/invitations/{invitation}/resend', [TeamController::class, 'resendInvite'])->name('workspaces.team.invitations.resend');
    Route::delete('team/invitations/{invitation}', [TeamController::class, 'revokeInvite'])->name('workspaces.team.invitations.revoke');
});
