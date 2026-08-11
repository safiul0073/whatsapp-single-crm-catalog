<?php

namespace App\Modules\Workspaces\Services;

use App\Models\User;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Shared\Support\PermissionRegistrar;
use App\Modules\Workspaces\Enums\WorkspaceMemberRole;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceRolePermission;
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

        return in_array($ability, $this->permissionsForRole($workspace, $role), true);
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
    protected function permissionsForRole(Workspace $workspace, WorkspaceMemberRole $role): array
    {
        $workspacePermissions = $this->workspacePermissionsForRole($workspace, $role);

        if ($workspacePermissions !== null) {
            return $workspacePermissions;
        }

        return $this->defaultPermissionsForRole($role);
    }

    /**
     * @return array<string>|null
     */
    protected function workspacePermissionsForRole(Workspace $workspace, WorkspaceMemberRole $role): ?array
    {
        if (! $this->hasCustomPermissionSet($workspace, $role)) {
            return null;
        }

        $cacheKey = "{$workspace->id}:{$role->value}";

        if (array_key_exists($cacheKey, $this->workspaceRolePermissions)) {
            return $this->workspaceRolePermissions[$cacheKey];
        }

        return $this->workspaceRolePermissions[$cacheKey] = WorkspaceRolePermission::query()
            ->where('workspace_id', $workspace->id)
            ->where('role', $role->value)
            ->pluck('permission_name')
            ->values()
            ->all();
    }

    /**
     * @return array<string>
     */
    protected function defaultPermissionsForRole(WorkspaceMemberRole $role): array
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

        if (! $spatieRole || $spatieRole->permissions->isEmpty()) {
            return $this->rolePermissions[$roleName] = $this->defaultPermissionsForRoleName($roleName);
        }

        return $this->rolePermissions[$roleName] = $spatieRole->permissions->pluck('name')->values()->all();
    }

    /**
     * @return array<string>
     */
    protected function defaultPermissionsForRoleName(string $roleName): array
    {
        $availablePermissions = Collection::make($this->webPermissions());

        $defaults = match ($roleName) {
            'workspace-administrator' => $availablePermissions->all(),
            'workspace-manager' => [
                'workspace.view',
                'contacts.view',
                'contacts.manage',
                'leads.view',
                'leads.manage',
                'campaigns.view',
                'campaigns.create',
                'templates.manage',
                'inbox.view',
                'inbox.reply',
                'inbox.assign',
                'reports.view',
                'automations.manage',
            ],
            'workspace-staff' => [
                'workspace.view',
                'contacts.view',
                'leads.view',
                'campaigns.view',
                'inbox.view',
                'inbox.assigned_only',
                'inbox.reply',
            ],
            default => [],
        };

        return $availablePermissions
            ->intersect($defaults)
            ->values()
            ->all();
    }

    protected function hasCustomPermissionSet(Workspace $workspace, WorkspaceMemberRole $role): bool
    {
        return in_array($role->value, data_get($workspace->settings, 'custom_role_permissions', []), true);
    }

    protected function isOwnerOnlyAbility(string $ability): bool
    {
        return in_array($ability, ['team.manage', 'team.manage.staff_only'], true);
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
