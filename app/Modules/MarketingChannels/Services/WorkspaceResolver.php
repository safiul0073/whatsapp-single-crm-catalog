<?php

namespace App\Modules\MarketingChannels\Services;

use App\Models\User;
use App\Modules\Workspaces\Enums\WorkspaceMemberRole;
use App\Modules\Workspaces\Enums\WorkspaceMemberStatus;
use App\Modules\Workspaces\Enums\WorkspaceStatus;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class WorkspaceResolver
{
    public function current(?User $user): ?Workspace
    {
        if (! $user) {
            return null;
        }

        $activeWorkspaceId = session('active_workspace_id');

        if ($activeWorkspaceId) {
            $activeWorkspace = Workspace::query()
                ->whereKey($activeWorkspaceId)
                ->where('status', WorkspaceStatus::Active->value)
                ->where(function ($query) use ($user): void {
                    $query->where('owner_id', $user->id)
                        ->orWhereHas('members', fn ($q) => $q->where('users.id', $user->id)->where('workspace_members.status', WorkspaceMemberStatus::Active->value));
                })
                ->first();

            if ($activeWorkspace) {
                return $activeWorkspace;
            }

            session()->forget('active_workspace_id');
        }

        $workspace = Workspace::query()
            ->where('owner_id', $user->id)
            ->orWhereHas('members', fn ($query) => $query->where('users.id', $user->id)->where('workspace_members.status', WorkspaceMemberStatus::Active->value))
            ->first();

        if ($workspace) {
            return $workspace;
        }

        $workspace = Workspace::query()->create([
            'owner_id' => $user->id,
            'name' => "{$user->name}'s Workspace",
            'slug' => Str::slug($user->name.'-'.$user->id),
            'status' => WorkspaceStatus::Active->value,
            'timezone' => config('app.timezone', 'UTC'),
        ]);

        $workspace->members()->attach($user->id, [
            'role' => WorkspaceMemberRole::Administrator->value,
            'status' => WorkspaceMemberStatus::Active->value,
        ]);

        $ownerRole = Role::findOrCreate('workspace-owner', 'web');
        if (! $user->hasRole($ownerRole)) {
            $user->assignRole($ownerRole);
        }

        return $workspace;
    }
}
