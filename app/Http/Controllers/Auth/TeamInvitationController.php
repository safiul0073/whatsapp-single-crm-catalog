<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Workspaces\Models\WorkspaceInvitation;
use App\Modules\Workspaces\Services\TeamManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TeamInvitationController extends Controller
{
    public function __construct(
        protected TeamManagementService $team,
    ) {}

    public function show(string $token): View
    {
        $invitation = $this->findValidInvitation($token);

        return view('auth.team-invitation', [
            'invitation' => $invitation,
            'token' => $token,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->findValidInvitation($token);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required' => __('Please provide your full name.'),
            'password.required' => __('Please provide a password.'),
            'password.min' => __('Password must be at least 8 characters.'),
            'password.confirmed' => __('Password confirmation does not match.'),
        ]);

        $user = $this->team->acceptInvite($invitation, $request->only('name', 'password'));

        Auth::login($user);

        return redirect()->route('user.dashboard')
            ->with('success', __('Welcome to :workspace. Your account has been created.', ['workspace' => $invitation->workspace->name]));
    }

    protected function findValidInvitation(string $token): WorkspaceInvitation
    {
        $invitation = WorkspaceInvitation::query()
            ->where('token', $token)
            ->with('workspace')
            ->firstOrFail();

        if ($invitation->isAccepted() || $invitation->isExpired()) {
            abort(404);
        }

        return $invitation;
    }
}
