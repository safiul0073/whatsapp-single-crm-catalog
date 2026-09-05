<?php

namespace App\Modules\Workspaces\Services;

use App\Models\User;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Shared\Support\PermissionRegistrar;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceRole;
use App\Modules\Workspaces\Models\WorkspaceRolePermission;
use Illuminate\Support\Collection;

class WorkspacePermissionResolver
{
    /**
     * @var array<string>|null
     */
    protected ?array $webPermissions = null;

    /**
     * @var array<string, array<string>|null>
     */
    protected array $workspaceRolePermissions = [];

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

        if ($this->isOwnerOnlyAbility($ability)) {
            return false;
        }

        $role = $this->activeWorkspaceRole($workspace, $user);

        if (! $role) {
            return false;
        }

        return in_array($ability, $this->permissionsForRole($role), true);
    }

    protected function activeWorkspaceRole(Workspace $workspace, User $user): ?WorkspaceRole
    {
        $member = $workspace->activeMembers()
            ->withPivot('workspace_role_id')
            ->where('users.id', $user->id)
            ->first();

        if (! $member?->pivot?->workspace_role_id) {
            return null;
        }

        return WorkspaceRole::find($member->pivot->workspace_role_id);
    }

    /**
     * @return array<string>
     */
    protected function permissionsForRole(WorkspaceRole $role): array
    {
        $cacheKey = "{$role->id}";

        if (array_key_exists($cacheKey, $this->workspaceRolePermissions)) {
            return $this->workspaceRolePermissions[$cacheKey];
        }

        return $this->workspaceRolePermissions[$cacheKey] = WorkspaceRolePermission::query()
            ->where('workspace_role_id', $role->id)
            ->pluck('permission_name')
            ->values()
            ->all();
    }

    protected function isOwnerOnlyAbility(string $ability): bool
    {
        return in_array($ability, ['team.manage', 'team.manage.staff_only'], true);
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
