<?php

namespace App\Modules\Workspaces\Services;

use App\Models\User;
use App\Modules\Shared\Support\PermissionRegistrar as ModulePermissionRegistrar;
use App\Modules\Workspaces\Enums\WorkspaceMemberStatus;
use App\Modules\Workspaces\Mail\TeamInvitationMail;
use App\Modules\Workspaces\Mail\TeamMemberWelcomeMail;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceInvitation;
use App\Modules\Workspaces\Models\WorkspaceRole;
use App\Modules\Workspaces\Models\WorkspaceRolePermission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TeamManagementService
{
    public function getTeamData(Workspace $workspace): array
    {
        $members = $workspace->members()
            ->withPivot('workspace_role_id', 'status')
            ->orderBy('workspace_members.created_at')
            ->get();

        $invitations = $workspace->invitations()
            ->with('workspaceRole')
            ->whereNull('accepted_at')
            ->orderByDesc('created_at')
            ->get();

        $activeMembers = $members->filter(
            fn (User $member): bool => $member->pivot->status === WorkspaceMemberStatus::Active
        );

        $seatLimit = $this->seatLimit($workspace);
        $seatsUsed = $activeMembers->count() + $invitations->count();

        $roles = WorkspaceRole::where('workspace_id', $workspace->id)->get();

        $counts = [
            'total' => $activeMembers->count(),
            'pending_invites' => $invitations->count(),
            'seats_used' => $seatsUsed,
            'seat_limit' => $seatLimit,
        ];
        
        foreach ($roles as $role) {
            $counts['role_' . $role->id] = $activeMembers->where('pivot.workspace_role_id', $role->id)->count();
        }

        return [
            'members' => $members,
            'invitations' => $invitations,
            'owner' => $workspace->owner,
            'roles' => $roles,
            'counts' => $counts,
        ];
    }

    public function canAddMember(Workspace $workspace): bool
    {
        $limit = $this->seatLimit($workspace);

        if ($limit === null) {
            return true;
        }

        $activeMembers = $workspace->activeMembers()->count();
        $pendingInvites = $workspace->invitations()->whereNull('accepted_at')->count();

        return ($activeMembers + $pendingInvites) < $limit;
    }

    public function seatLimit(Workspace $workspace): ?int
    {
        return null;
    }

    public function rolePermissionMatrix(Workspace $workspace): array
    {
        $permissionGroups = $this->workspacePermissionGroups();

        $roles = WorkspaceRole::where('workspace_id', $workspace->id)->get();
        $matrixRoles = [];

        foreach ($roles as $role) {
            $matrixRoles[$role->id] = [
                'label' => $role->name,
                'permissions' => $this->permissionsForRole($role),
            ];
        }

        return [
            'roles' => $matrixRoles,
            'groups' => $permissionGroups,
        ];
    }

    public function rolePermissionDetails(WorkspaceRole $role): array
    {
        return [
            'label' => $role->name,
            'permissions' => $this->permissionsForRole($role),
            'groups' => $this->workspacePermissionGroups(),
        ];
    }

    public function updateRolePermissions(WorkspaceRole $role, array $permissions): void
    {
        $allowedPermissions = $this->workspacePermissionCatalog()->pluck('name')->all();
        $permissionNames = collect($permissions)
            ->intersect($allowedPermissions)
            ->unique()
            ->values()
            ->all();

        DB::transaction(function () use ($role, $permissionNames): void {
            WorkspaceRolePermission::query()
                ->where('workspace_role_id', $role->id)
                ->delete();

            foreach ($permissionNames as $permissionName) {
                WorkspaceRolePermission::query()->create([
                    'workspace_id' => $role->workspace_id,
                    'workspace_role_id' => $role->id,
                    'permission_name' => $permissionName,
                ]);
            }
        });
    }

    public function createMember(Workspace $workspace, array $data, User $actor): User
    {
        $this->ensureOwner($workspace, $actor);

        if (! $this->canAddMember($workspace)) {
            throw new HttpException(403, __('This workspace has reached its team member limit.'));
        }

        $role = $this->resolveRole($workspace, $data['role']);

        $user = User::query()->create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->attachMember($workspace, $user, $role);

        Mail::to($user)->queue(new TeamMemberWelcomeMail($workspace, $user, $data['password'], $actor));

        return $user;
    }

    public function updateMember(Workspace $workspace, User $member, array $data, User $actor): User
    {
        $this->ensureOwner($workspace, $actor);
        $this->ensureNotOwner($workspace, $member);
        $this->ensureNotSelf($actor, $member);

        $member->update([
            'first_name' => $data['first_name'] ?? $member->first_name,
            'last_name' => $data['last_name'] ?? $member->last_name,
            'email' => $data['email'] ?? $member->email,
        ]);

        if (isset($data['role'])) {
            $newRole = $this->resolveRole($workspace, $data['role']);
            
            if ($member->pivot->workspace_role_id !== $newRole->id) {
                $workspace->members()->updateExistingPivot($member->id, ['workspace_role_id' => $newRole->id]);
            }
        }

        return $member->fresh();
    }

    public function removeMember(Workspace $workspace, User $member, User $actor): void
    {
        $this->ensureOwner($workspace, $actor);
        $this->ensureNotOwner($workspace, $member);
        $this->ensureNotSelf($actor, $member);

        $workspace->members()->detach($member->id);
    }

    public function inviteMember(Workspace $workspace, array $data, User $actor): WorkspaceInvitation
    {
        $this->ensureOwner($workspace, $actor);

        if (! $this->canAddMember($workspace)) {
            throw new HttpException(403, __('This workspace has reached its team member limit.'));
        }

        $role = $this->resolveRole($workspace, $data['role']);

        $existingUser = User::query()->where('email', $data['email'])->first();

        if ($existingUser && $workspace->members()->where('users.id', $existingUser->id)->exists()) {
            throw new HttpException(422, __('This user is already a member of the workspace.'));
        }

        $workspace->invitations()
            ->where('email', $data['email'])
            ->whereNull('accepted_at')
            ->delete();

        $invitation = $workspace->invitations()->create([
            'email' => $data['email'],
            'workspace_role_id' => $role->id,
            'token' => Str::random(64),
            'invited_by' => $actor->id,
            'expires_at' => now()->addDays(7),
        ]);

        Mail::to($invitation->email)->queue(new TeamInvitationMail($workspace, $invitation, $actor));

        return $invitation;
    }

    public function resendInvite(WorkspaceInvitation $invitation, User $actor): WorkspaceInvitation
    {
        $this->ensureOwner($invitation->workspace, $actor);

        if ($invitation->isAccepted() || $invitation->isExpired()) {
            throw new HttpException(422, __('This invitation is no longer valid.'));
        }

        $invitation->update(['expires_at' => now()->addDays(7)]);

        Mail::to($invitation->email)->queue(new TeamInvitationMail($invitation->workspace, $invitation, $actor));

        return $invitation;
    }

    public function revokeInvite(WorkspaceInvitation $invitation, User $actor): void
    {
        $this->ensureOwner($invitation->workspace, $actor);

        if ($invitation->isAccepted()) {
            throw new HttpException(422, __('This invitation has already been accepted.'));
        }

        $invitation->delete();
    }

    public function acceptInvite(WorkspaceInvitation $invitation, array $data): User
    {
        if ($invitation->isAccepted() || $invitation->isExpired()) {
            throw new HttpException(422, __('This invitation is no longer valid.'));
        }

        $existingUser = User::query()->where('email', $invitation->email)->first();

        if ($existingUser) {
            $user = $existingUser;
        } else {
            $parts = explode(' ', trim($data['name'] ?? ''), 2);

            $user = User::query()->create([
                'first_name' => $parts[0] ?? null,
                'last_name' => $parts[1] ?? null,
                'email' => $invitation->email,
                'password' => Hash::make($data['password']),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        }

        $role = $invitation->workspaceRole;

        $this->attachMember($invitation->workspace, $user, $role);

        $invitation->update(['accepted_at' => now()]);

        return $user;
    }

    protected function attachMember(Workspace $workspace, User $user, WorkspaceRole $role): void
    {
        $workspace->members()->syncWithoutDetaching([
            $user->id => [
                'workspace_role_id' => $role->id,
                'status' => WorkspaceMemberStatus::Active->value,
            ],
        ]);
    }

    protected function workspacePermissionGroups(): array
    {
        return $this->workspacePermissionCatalog()
            ->groupBy('module')
            ->map(fn (Collection $permissions, string $module): array => [
                'label' => str($module)->replace('-', ' ')->title()->toString(),
                'permissions' => $permissions->values()->all(),
            ])
            ->values()
            ->all();
    }

    protected function workspacePermissionCatalog(): Collection
    {
        return collect(app(ModulePermissionRegistrar::class)->permissions())
            ->filter(fn (array $permission): bool => $permission['guard'] === 'web')
            ->reject(fn (array $permission): bool => in_array($permission['name'], ['team.manage', 'team.manage.staff_only'], true))
            ->map(fn (array $permission): array => [
                'name' => $permission['name'],
                'module' => $permission['module'],
                'label' => $permission['label'] ?: str($permission['name'])->after('.')->replace('_', ' ')->title()->toString(),
            ])
            ->sortBy('name')
            ->values();
    }

    protected function permissionsForRole(WorkspaceRole $role): array
    {
        return WorkspaceRolePermission::query()
            ->where('workspace_role_id', $role->id)
            ->pluck('permission_name')
            ->values()
            ->all();
    }

    protected function ensureNotOwner(Workspace $workspace, User $member): void
    {
        if ($workspace->isOwner($member)) {
            throw new HttpException(403, __('The workspace owner cannot be modified or removed.'));
        }
    }

    protected function ensureNotSelf(User $actor, User $member): void
    {
        if ($actor->id === $member->id) {
            throw new HttpException(403, __('You cannot modify or remove your own account.'));
        }
    }

    protected function ensureOwner(Workspace $workspace, User $actor): void
    {
        if ($workspace->isOwner($actor)) {
            return;
        }

        throw new HttpException(403, __('Only the workspace owner can manage team members.'));
    }

    public function resolveRole(Workspace $workspace, mixed $roleId): WorkspaceRole
    {
        $role = WorkspaceRole::where('workspace_id', $workspace->id)->where('id', $roleId)->first();
        if (!$role) {
            throw new HttpException(422, __('Invalid role selected.'));
        }
        return $role;
    }
}
