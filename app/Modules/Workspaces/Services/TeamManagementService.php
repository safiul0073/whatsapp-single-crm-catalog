<?php

namespace App\Modules\Workspaces\Services;

use App\Models\User;
use App\Modules\PlansSubscriptions\Enums\SubscriptionStatus;
use App\Modules\PlansSubscriptions\Models\Subscription;
use App\Modules\Shared\Support\PermissionRegistrar as ModulePermissionRegistrar;
use App\Modules\Workspaces\Enums\WorkspaceMemberRole;
use App\Modules\Workspaces\Enums\WorkspaceMemberStatus;
use App\Modules\Workspaces\Mail\TeamInvitationMail;
use App\Modules\Workspaces\Mail\TeamMemberWelcomeMail;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceInvitation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar as SpatiePermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TeamManagementService
{
    public function getTeamData(Workspace $workspace): array
    {
        $members = $workspace->members()
            ->with('roles')
            ->orderBy('workspace_members.created_at')
            ->get();

        $invitations = $workspace->invitations()
            ->whereNull('accepted_at')
            ->orderByDesc('created_at')
            ->get();

        $activeMembers = $members->filter(
            fn (User $member): bool => $member->pivot->status === WorkspaceMemberStatus::Active
        );

        $seatLimit = $this->seatLimit($workspace);
        $seatsUsed = $activeMembers->count() + $invitations->count();

        return [
            'members' => $members,
            'invitations' => $invitations,
            'owner' => $workspace->owner,
            'counts' => [
                'total' => $activeMembers->count(),
                'administrators' => $activeMembers->where('pivot.role', WorkspaceMemberRole::Administrator->value)->count(),
                'managers' => $activeMembers->where('pivot.role', WorkspaceMemberRole::Manager->value)->count(),
                'staff' => $activeMembers->where('pivot.role', WorkspaceMemberRole::Staff->value)->count(),
                'pending_invites' => $invitations->count(),
                'seats_used' => $seatsUsed,
                'seat_limit' => $seatLimit,
            ],
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
        $subscription = Subscription::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value])
            ->with('plan')
            ->first();

        if (! $subscription?->plan) {
            return null;
        }

        $limit = data_get($subscription->plan->limits, 'team_members', data_get($subscription->plan->limits, 'team_seats'));

        return $limit === null ? null : (int) $limit;
    }

    public function rolePermissionMatrix(): array
    {
        $permissionGroups = $this->workspacePermissionGroups();

        $roles = collect(WorkspaceMemberRole::cases())
            ->mapWithKeys(function (WorkspaceMemberRole $role): array {
                $spatieRole = $this->spatieRoleFor($role)->loadMissing('permissions');

                return [
                    $role->value => [
                        'label' => $role->label(),
                        'permissions' => $spatieRole->permissions->pluck('name')->values()->all(),
                    ],
                ];
            })
            ->all();

        return [
            'roles' => $roles,
            'groups' => $permissionGroups,
        ];
    }

    public function rolePermissionDetails(WorkspaceMemberRole $role): array
    {
        $spatieRole = $this->spatieRoleFor($role)->loadMissing('permissions');

        return [
            'label' => $role->label(),
            'permissions' => $spatieRole->permissions->pluck('name')->values()->all(),
            'groups' => $this->workspacePermissionGroups(),
        ];
    }

    /**
     * @param  array<string>  $permissions
     */
    public function updateRolePermissions(WorkspaceMemberRole $role, array $permissions): void
    {
        $allowedPermissions = $this->workspacePermissionCatalog()->pluck('name')->all();
        $permissionNames = collect($permissions)
            ->intersect($allowedPermissions)
            ->unique()
            ->values()
            ->all();

        $this->spatieRoleFor($role)->syncPermissions($permissionNames);

        app(SpatiePermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function createMember(Workspace $workspace, array $data, User $actor): User
    {
        if (! $this->canAddMember($workspace)) {
            throw new HttpException(403, __('This workspace has reached its team member limit.'));
        }

        $role = WorkspaceMemberRole::from($data['role']);
        $this->ensureCanManageRole($actor, $role);

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
        $this->ensureNotOwner($workspace, $member);
        $this->ensureNotSelf($actor, $member);

        $currentRole = $this->resolveRole($member->pivot->role);
        $this->ensureCanManageRole($actor, $currentRole);

        $member->update([
            'first_name' => $data['first_name'] ?? $member->first_name,
            'last_name' => $data['last_name'] ?? $member->last_name,
            'email' => $data['email'] ?? $member->email,
        ]);

        if (isset($data['role'])) {
            $newRole = $this->resolveRole($data['role']);
            $this->ensureCanManageRole($actor, $newRole);

            if ($currentRole !== $newRole) {
                $workspace->members()->updateExistingPivot($member->id, ['role' => $newRole->value]);
                $this->syncWorkspaceRole($member, $newRole);
            }
        }

        return $member->fresh();
    }

    public function removeMember(Workspace $workspace, User $member, User $actor): void
    {
        $this->ensureNotOwner($workspace, $member);
        $this->ensureNotSelf($actor, $member);

        $role = $this->resolveRole($member->pivot->role);
        $this->ensureCanManageRole($actor, $role);

        $workspace->members()->detach($member->id);
        $this->syncWorkspaceRolesFromMemberships($member);
    }

    public function inviteMember(Workspace $workspace, array $data, User $actor): WorkspaceInvitation
    {
        if (! $this->canAddMember($workspace)) {
            throw new HttpException(403, __('This workspace has reached its team member limit.'));
        }

        $role = WorkspaceMemberRole::from($data['role']);
        $this->ensureCanManageRole($actor, $role);

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
            'role' => $role->value,
            'token' => Str::random(64),
            'invited_by' => $actor->id,
            'expires_at' => now()->addDays(7),
        ]);

        Mail::to($invitation->email)->queue(new TeamInvitationMail($workspace, $invitation, $actor));

        return $invitation;
    }

    public function resendInvite(WorkspaceInvitation $invitation, User $actor): WorkspaceInvitation
    {
        if ($invitation->isAccepted() || $invitation->isExpired()) {
            throw new HttpException(422, __('This invitation is no longer valid.'));
        }

        $invitation->update(['expires_at' => now()->addDays(7)]);

        Mail::to($invitation->email)->queue(new TeamInvitationMail($invitation->workspace, $invitation, $actor));

        return $invitation;
    }

    public function revokeInvite(WorkspaceInvitation $invitation): void
    {
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

        $role = $invitation->role;

        $this->attachMember($invitation->workspace, $user, $role);

        $invitation->update(['accepted_at' => now()]);

        return $user;
    }

    protected function attachMember(Workspace $workspace, User $user, WorkspaceMemberRole $role): void
    {
        $workspace->members()->syncWithoutDetaching([
            $user->id => [
                'role' => $role->value,
                'status' => WorkspaceMemberStatus::Active->value,
            ],
        ]);

        $this->syncWorkspaceRole($user, $role);
    }

    protected function syncWorkspaceRole(User $user, WorkspaceMemberRole $role): void
    {
        $this->syncWorkspaceRolesFromMemberships($user, $role);
    }

    protected function syncWorkspaceRolesFromMemberships(User $user, ?WorkspaceMemberRole $fallbackRole = null): void
    {
        $workspaceRoleNames = [
            'workspace-administrator',
            'workspace-manager',
            'workspace-staff',
        ];

        $roleValues = DB::table('workspace_members')
            ->where('user_id', $user->id)
            ->where('status', WorkspaceMemberStatus::Active->value)
            ->pluck('role')
            ->all();

        $role = $this->highestWorkspaceRole($roleValues) ?? $fallbackRole;

        $roles = $user->roles()
            ->whereNotIn('name', $workspaceRoleNames)
            ->pluck('name')
            ->all();

        if ($role) {
            $roles[] = $this->spatieRoleFor($role)->name;
        }

        $user->syncRoles(array_values(array_unique($roles)));
    }

    /**
     * @param  array<string>  $roleValues
     */
    protected function highestWorkspaceRole(array $roleValues): ?WorkspaceMemberRole
    {
        foreach ([WorkspaceMemberRole::Administrator, WorkspaceMemberRole::Manager, WorkspaceMemberRole::Staff] as $role) {
            if (in_array($role->value, $roleValues, true)) {
                return $role;
            }
        }

        return null;
    }

    protected function spatieRoleFor(WorkspaceMemberRole $role): Role
    {
        return match ($role) {
            WorkspaceMemberRole::Administrator => Role::findOrCreate('workspace-administrator', 'web'),
            WorkspaceMemberRole::Manager => Role::findOrCreate('workspace-manager', 'web'),
            WorkspaceMemberRole::Staff => Role::findOrCreate('workspace-staff', 'web'),
        };
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
            ->map(fn (array $permission): array => [
                'name' => $permission['name'],
                'module' => $permission['module'],
                'label' => $permission['label'] ?: str($permission['name'])->after('.')->replace('_', ' ')->title()->toString(),
            ])
            ->sortBy('name')
            ->values();
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

    protected function ensureCanManageRole(User $actor, WorkspaceMemberRole $role): void
    {
        if ($actor->can('team.manage')) {
            return;
        }

        if ($actor->can('team.manage.staff_only') && $role === WorkspaceMemberRole::Staff) {
            return;
        }

        throw new HttpException(403, __('You do not have permission to manage this role.'));
    }

    public function resolveRole(WorkspaceMemberRole|string $role): WorkspaceMemberRole
    {
        return $role instanceof WorkspaceMemberRole ? $role : WorkspaceMemberRole::from($role);
    }
}
