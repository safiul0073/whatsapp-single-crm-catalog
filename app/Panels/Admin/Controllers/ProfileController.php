<?php

namespace App\Panels\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\PasswordChangedMail;
use App\Modules\Shared\Services\SessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(protected SessionService $sessionService) {}

    public function edit(): View
    {
        $user = Auth::guard('admin')->user();
        $sessions = $this->sessionService->getActiveSessions($user->id);

        return view('panels.admin.profile.edit', compact('user', 'sessions'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = Auth::guard('admin')->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email,'.$user->id,
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|confirmed|min:8',
            'avatar' => 'nullable|image|max:2048',
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
        ];

        $passwordChanged = false;

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
            $passwordChanged = true;
        }

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $data['avatar'] = $path;
        }

        $user->update($data);

        if ($passwordChanged) {
            Mail::to($user)->queue(new PasswordChangedMail($user, $request->ip()));
        }

        return back()->with('success', 'Profile updated successfully.');
    }

    public function revokeSession(Request $request, string $sessionId): RedirectResponse
    {
        $user = Auth::guard('admin')->user();

        $this->sessionService->revokeSession($sessionId, $user->id);

        return back()->with('success', __('Session revoked successfully.'));
    }

    public function revokeAllSessions(Request $request): RedirectResponse
    {
        $user = Auth::guard('admin')->user();

        $this->sessionService->revokeAllOtherSessions($user->id, session()->getId());

        return back()->with('success', __('All other sessions have been revoked.'));
    }
}
