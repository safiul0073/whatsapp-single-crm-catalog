<?php

namespace App\Modules\Workspaces\Services;

use App\Models\User;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Shared\Support\PermissionRegistrar;
use App\Modules\Workspaces\Enums\WorkspaceMemberRole;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class WorkspacePermissionResolver
{
    /**
     * @var array<string>|null
     */
    protected ?array $webPermissions = null;

    /**
     * @var array<string, array<string>>
     */
    protected array $rolePermissions = [];

    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected PermissionRegistrar $permissions,
    ) {}

    public function can(User $user, string $ability): ?bool
    {
        if (! in_array($ability, $this->webPermissions(), true)) {
            return null;
        }

        $workspace = $this->workspaces->current($user);

        if (! $workspace) {
            return false;
        }

        if ($workspace->isOwner($user)) {
            return true;
        }

        $role = $this->activeWorkspaceRole($workspace, $user);

        if (! $role) {
            return false;
        }

        return in_array($ability, $this->permissionsForRole($role), true);
    }

    protected function activeWorkspaceRole(Workspace $workspace, User $user): ?WorkspaceMemberRole
    {
        $member = $workspace->activeMembers()
            ->where('users.id', $user->id)
            ->first();

        if (! $member?->pivot?->role) {
            return null;
        }

        return $member->pivot->role instanceof WorkspaceMemberRole
            ? $member->pivot->role
            : WorkspaceMemberRole::tryFrom((string) $member->pivot->role);
    }

    /**
     * @return array<string>
     */
    protected function permissionsForRole(WorkspaceMemberRole $role): array
    {
        $roleName = $this->spatieRoleName($role);

        if (array_key_exists($roleName, $this->rolePermissions)) {
            return $this->rolePermissions[$roleName];
        }

        $spatieRole = Role::query()
            ->where('name', $roleName)
            ->where('guard_name', 'web')
            ->with('permissions')
            ->first();

        return $this->rolePermissions[$roleName] = $spatieRole
            ? $spatieRole->permissions->pluck('name')->values()->all()
            : [];
    }

    protected function spatieRoleName(WorkspaceMemberRole $role): string
    {
        return match ($role) {
            WorkspaceMemberRole::Administrator => 'workspace-administrator',
            WorkspaceMemberRole::Manager => 'workspace-manager',
            WorkspaceMemberRole::Staff => 'workspace-staff',
        };
    }

    /**
     * @return array<string>
     */
    protected function webPermissions(): array
    {
        if ($this->webPermissions !== null) {
            return $this->webPermissions;
        }

        return $this->webPermissions = Collection::make($this->permissions->permissions())
            ->filter(fn (array $permission): bool => $permission['guard'] === 'web')
            ->pluck('name')
            ->values()
            ->all();
    }
}
