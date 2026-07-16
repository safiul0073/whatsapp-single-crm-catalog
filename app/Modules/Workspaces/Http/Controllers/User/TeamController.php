<?php

namespace App\Modules\Workspaces\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Workspaces\Enums\WorkspaceMemberRole;
use App\Modules\Workspaces\Http\Requests\User\InviteTeamMemberRequest;
use App\Modules\Workspaces\Http\Requests\User\StoreTeamMemberRequest;
use App\Modules\Workspaces\Http\Requests\User\UpdateRolePermissionsRequest;
use App\Modules\Workspaces\Http\Requests\User\UpdateTeamMemberRequest;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceInvitation;
use App\Modules\Workspaces\Services\TeamManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected TeamManagementService $team,
    ) {}

    public function index(Request $request): View
    {
        $workspace = $this->workspaces->current($request->user());
        $teamData = $this->team->getTeamData($workspace);

        return view('workspaces::user.team', [
            'workspace' => $workspace,
            'members' => $teamData['members'],
            'invitations' => $teamData['invitations'],
            'counts' => $teamData['counts'],
            'canAddMember' => $this->team->canAddMember($workspace),
        ]);
    }

    public function store(StoreTeamMemberRequest $request): RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());

        $this->team->createMember($workspace, $request->validated(), $request->user());

        return redirect()->route('user.workspaces.team')
            ->with('success', __('Team member added successfully.'));
    }

    public function update(UpdateTeamMemberRequest $request, User $member): RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $member = $this->resolveWorkspaceMember($workspace, $member);

        $this->team->updateMember($workspace, $member, $request->validated(), $request->user());

        return redirect()->route('user.workspaces.team')
            ->with('success', __('Team member updated successfully.'));
    }

    public function updateRolePermissions(UpdateRolePermissionsRequest $request, string $role): RedirectResponse
    {
        $this->team->updateRolePermissions(
            WorkspaceMemberRole::from($role),
            $request->validated('permissions') ?? []
        );

        return redirect()->route('user.workspaces.team')
            ->with('success', __('Role permissions updated successfully.'));
    }

    public function permissions(Request $request, User $member): View
    {
        $workspace = $this->workspaces->current($request->user());
        $member = $this->resolveWorkspaceMember($workspace, $member);
        $role = $this->team->resolveRole($member->pivot->role);

        return view('workspaces::user.permissions', [
            'workspace' => $workspace,
            'member' => $member,
            'role' => $role,
            'rolePermissions' => $this->team->rolePermissionDetails($role),
        ]);
    }

    public function updateMemberPermissions(UpdateRolePermissionsRequest $request, User $member): RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $member = $this->resolveWorkspaceMember($workspace, $member);
        $role = $this->team->resolveRole($member->pivot->role);

        $this->team->updateRolePermissions($role, $request->validated('permissions') ?? []);

        return redirect()->route('user.workspaces.team.permissions', $member)
            ->with('success', __('Role permissions updated successfully.'));
    }

    public function destroy(Request $request, User $member): RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $member = $this->resolveWorkspaceMember($workspace, $member);

        $this->team->removeMember($workspace, $member, $request->user());

        return redirect()->route('user.workspaces.team')
            ->with('success', __('Team member removed successfully.'));
    }

    public function invite(InviteTeamMemberRequest $request): RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());

        $this->team->inviteMember($workspace, $request->validated(), $request->user());

        return redirect()->route('user.workspaces.team')
            ->with('success', __('Invitation sent successfully.'));
    }

    public function resendInvite(Request $request, WorkspaceInvitation $invitation): RedirectResponse
    {
        $this->team->resendInvite($invitation, $request->user());

        return redirect()->route('user.workspaces.team')
            ->with('success', __('Invitation resent successfully.'));
    }

    public function revokeInvite(Request $request, WorkspaceInvitation $invitation): RedirectResponse
    {
        $this->team->revokeInvite($invitation);

        return redirect()->route('user.workspaces.team')
            ->with('success', __('Invitation revoked successfully.'));
    }

    protected function resolveWorkspaceMember(Workspace $workspace, User $member): User
    {
        return $workspace->members()->where('users.id', $member->id)->firstOrFail();
    }
}
