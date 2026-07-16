<?php

namespace App\Modules\Workspaces\Policies;

use App\Models\User;
use App\Modules\Workspaces\Models\Workspace;

class WorkspacePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->isOwner($user)
            || $workspace->activeMembers()->where('users.id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $workspace->isOwner($user);
    }

    public function toggleStatus(User $user, Workspace $workspace): bool
    {
        return $workspace->isOwner($user);
    }

    public function switch(User $user, Workspace $workspace): bool
    {
        return $this->view($user, $workspace);
    }
}
