<?php

namespace App\Modules\Workspaces\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Workspaces\Enums\WorkspaceMemberRole;
use App\Modules\Workspaces\Enums\WorkspaceMemberStatus;
use App\Modules\Workspaces\Enums\WorkspaceStatus;
use App\Modules\Workspaces\Http\Requests\User\StoreWorkspaceRequest;
use App\Modules\Workspaces\Http\Requests\User\UpdateWorkspaceRequest;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WorkspaceController extends Controller
{
    public function __construct(
        protected WorkspaceResolver $workspaces,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $currentWorkspace = $this->workspaces->current($user);

        $ownedWorkspaces = Workspace::query()
            ->where('owner_id', $user->id)
            ->withCount('activeMembers')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Workspace $workspace) => $this->enrichWorkspaceForView($workspace, $user));

        $memberWorkspaces = Workspace::query()
            ->where('owner_id', '!=', $user->id)
            ->whereHas('activeMembers', fn ($q) => $q->where('users.id', $user->id))
            ->withCount('activeMembers')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Workspace $workspace) => $this->enrichWorkspaceForView($workspace, $user));

        $invitations = WorkspaceInvitation::query()
            ->where('email', $user->email)
            ->whereNull('accepted_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with(['workspace', 'inviter'])
            ->get();

        $currentEnriched = $currentWorkspace
            ? $this->enrichWorkspaceForView($currentWorkspace, $user)
            : null;

        $activeOwnedCount = $ownedWorkspaces->filter(fn (Workspace $w) => $w->status === WorkspaceStatus::Active)->count();

        return view('workspaces::user.index', [
            'currentWorkspace' => $currentEnriched,
            'ownedWorkspaces' => $ownedWorkspaces,
            'memberWorkspaces' => $memberWorkspaces,
            'invitations' => $invitations,
            'activeOwnedCount' => $activeOwnedCount,
        ]);
    }

    public function store(StoreWorkspaceRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Enforce single active workspace per administrator
        Workspace::query()
            ->where('owner_id', $user->id)
            ->where('status', WorkspaceStatus::Active)
            ->update(['status' => WorkspaceStatus::Suspended]);

        $workspace = Workspace::query()->create([
            'owner_id' => $user->id,
            'name' => $request->name,
            'slug' => $request->slug,
            'status' => WorkspaceStatus::Active,
            'timezone' => $request->timezone ?? config('app.timezone', 'UTC'),
        ]);

        $workspace->members()->attach($user->id, [
            'role' => WorkspaceMemberRole::Administrator->value,
            'status' => WorkspaceMemberStatus::Active->value,
        ]);

        $request->session()->put('active_workspace_id', $workspace->id);

        return redirect()->route('user.workspaces.index')
            ->with('success', __('Workspace created successfully.'));
    }

    public function update(UpdateWorkspaceRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->authorizeWorkspaceAction($request->user(), $workspace);

        $workspace->update($request->validated());

        return redirect()->route('user.workspaces.index')
            ->with('success', __('Workspace updated successfully.'));
    }

    public function toggleStatus(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorizeWorkspaceAction($request->user(), $workspace);

        $newStatus = $workspace->status === WorkspaceStatus::Active
            ? WorkspaceStatus::Suspended
            : WorkspaceStatus::Active;

        if ($newStatus === WorkspaceStatus::Active) {
            // Enforce single active workspace per administrator
            Workspace::query()
                ->where('owner_id', $request->user()->id)
                ->where('id', '!=', $workspace->id)
                ->where('status', WorkspaceStatus::Active)
                ->update(['status' => WorkspaceStatus::Suspended]);

            $request->session()->put('active_workspace_id', $workspace->id);
        } else {
            // Prevent deactivating the only active workspace
            $activeCount = Workspace::query()
                ->where('owner_id', $request->user()->id)
                ->where('status', WorkspaceStatus::Active)
                ->count();

            if ($activeCount <= 1) {
                return redirect()->route('user.workspaces.index')
                    ->with('error', __('You must have at least one active workspace.'));
            }
        }

        $workspace->update(['status' => $newStatus]);

        if ($newStatus === WorkspaceStatus::Suspended && $request->session()->get('active_workspace_id') === $workspace->id) {
            $request->session()->forget('active_workspace_id');
        }

        $statusMessage = $newStatus === WorkspaceStatus::Active
            ? __('Workspace activated.')
            : __('Workspace suspended.');

        return redirect()->route('user.workspaces.index')
            ->with('success', $statusMessage);
    }

    public function switch(Request $request, Workspace $workspace): RedirectResponse
    {
        $user = $request->user();

        $isMember = $workspace->isOwner($user)
            || $workspace->activeMembers()->where('users.id', $user->id)->exists();

        if (! $isMember) {
            throw new HttpException(403, __('You do not have access to this workspace.'));
        }

        if ($workspace->status !== WorkspaceStatus::Active) {
            throw new HttpException(422, __('Cannot switch to a suspended or archived workspace.'));
        }

        $request->session()->put('active_workspace_id', $workspace->id);

        return redirect()->route('user.dashboard')
            ->with('success', __('Switched to :workspace.', ['workspace' => $workspace->name]));
    }

    public function team(): View
    {
        return view('workspaces::user.team');
    }

    public function acceptInvite(Request $request, WorkspaceInvitation $invitation): RedirectResponse
    {
        $user = $request->user();

        if ($invitation->email !== $user->email) {
            throw new HttpException(403, __('This invitation is not for your account.'));
        }

        if ($invitation->isAccepted() || $invitation->isExpired()) {
            throw new HttpException(422, __('This invitation is no longer valid.'));
        }

        $role = $invitation->role instanceof WorkspaceMemberRole
            ? $invitation->role
            : WorkspaceMemberRole::from($invitation->role);

        $invitation->workspace->members()->syncWithoutDetaching([
            $user->id => [
                'role' => $role->value,
                'status' => WorkspaceMemberStatus::Active->value,
            ],
        ]);

        $invitation->update(['accepted_at' => now()]);

        return redirect()->route('user.workspaces.index')
            ->with('success', __('You have joined :workspace.', ['workspace' => $invitation->workspace->name]));
    }

    public function declineInvite(Request $request, WorkspaceInvitation $invitation): RedirectResponse
    {
        $user = $request->user();

        if ($invitation->email !== $user->email) {
            throw new HttpException(403, __('This invitation is not for your account.'));
        }

        $invitation->delete();

        return redirect()->route('user.workspaces.index')
            ->with('success', __('Invitation declined.'));
    }

    public function leave(Request $request, Workspace $workspace): RedirectResponse
    {
        $user = $request->user();

        if ($workspace->isOwner($user)) {
            throw new HttpException(422, __('You cannot leave a workspace you own. Transfer ownership first.'));
        }

        $workspace->members()->detach($user->id);

        if ($request->session()->get('active_workspace_id') === $workspace->id) {
            $request->session()->forget('active_workspace_id');
        }

        return redirect()->route('user.workspaces.index')
            ->with('success', __('You have left :workspace.', ['workspace' => $workspace->name]));
    }

    public function destroy(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorizeWorkspaceAction($request->user(), $workspace);

        if ($workspace->hasServices()) {
            throw new HttpException(403, __('Cannot delete a workspace that has active services. Clear all contacts, campaigns, and channels first.'));
        }

        $workspaceName = $workspace->name;

        $workspace->delete();

        if ($request->session()->get('active_workspace_id') === $workspace->id) {
            $request->session()->forget('active_workspace_id');
        }

        return redirect()->route('user.workspaces.index')
            ->with('success', __(':workspace has been deleted.', ['workspace' => $workspaceName]));
    }

    protected function authorizeWorkspaceAction(User $user, Workspace $workspace): void
    {
        if (! $workspace->isOwner($user)) {
            throw new HttpException(403, __('You are not authorized to perform this action.'));
        }
    }

    protected function enrichWorkspaceForView(Workspace $workspace, User $user): Workspace
    {
        $membership = $workspace->members()
            ->where('users.id', $user->id)
            ->withPivot(['role', 'status'])
            ->first();

        $workspace->setRelation('viewerMembership', $membership);

        if ($workspace->isOwner($user)) {
            $workspace->viewer_role = 'owner';
        } elseif ($membership) {
            $workspace->viewer_role = $membership->pivot->role->value;
        } else {
            $workspace->viewer_role = null;
        }

        $workspace->can_delete = $workspace->canDelete();

        return $workspace;
    }
}
