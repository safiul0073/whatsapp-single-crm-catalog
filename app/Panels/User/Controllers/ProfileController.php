<?php

namespace App\Panels\User\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\PasswordChangedMail;
use App\Modules\Media\Services\MediaService;
use App\Modules\Shared\Services\SessionService;
use App\Modules\SystemNotifications\Services\SystemNotificationService;
use App\Panels\User\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        protected SessionService $sessionService,
        protected SystemNotificationService $notifications,
        protected MediaService $mediaService,
    ) {}

    public function edit(): View
    {
        $user = auth()->user();
        $sessions = $this->sessionService->getActiveSessions($user->id);
        $timezones = timezone_identifiers_list();
        $locales = collect(glob(resource_path('lang/*.json')) ?: [])
            ->mapWithKeys(fn (string $path): array => [
                basename($path, '.json') => match (basename($path, '.json')) {
                    'ar' => __('Arabic'),
                    'bn' => __('Bengali'),
                    'en' => __('English'),
                    default => strtoupper(basename($path, '.json')),
                },
            ])
            ->sortKeys()
            ->all();

        return view('panels.user.profile.edit', compact('user', 'sessions', 'timezones', 'locales'));
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = auth()->user();
        $validated = $request->validated();

        match ($validated['section']) {
            'security' => $this->updateSecurity($request),
            'avatar' => $this->updateAvatar($request),
            'preferences' => $this->updatePreferences($request),
            default => $this->updateDetails($request),
        };

        return back()->with('success', $this->successMessage($validated['section']));
    }

    protected function updateDetails(UpdateProfileRequest $request): void
    {
        $user = $request->user();
        $data = $request->safe()->only(['name', 'email', 'phone', 'bio']);

        if ($user->email !== $data['email']) {
            $data['email_verified_at'] = null;
        }

        $user->forceFill($data)->save();
    }

    protected function updateSecurity(UpdateProfileRequest $request): void
    {
        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->string('password')->toString()),
        ]);

        Mail::to($user)->queue(new PasswordChangedMail($user, $request->ip()));

        $this->notifications->send($user, [
            'title' => __('You have a new mail'),
            'body' => __('A password change confirmation has been sent to your mailbox.'),
            'icon' => 'mail',
            'url' => null,
            'type' => 'info',
        ]);
    }

    protected function updateAvatar(UpdateProfileRequest $request): void
    {
        $avatar = null;

        if ($request->boolean('remove_avatar')) {
            $request->user()->update(['avatar' => null]);

            return;
        }

        if ($request->hasFile('avatar_upload')) {
            $avatar = $this->mediaService->upload($request->file('avatar_upload'))->id;
        } elseif ($request->filled('avatar')) {
            $avatar = $request->integer('avatar');
        }

        $request->user()->update(['avatar' => $avatar]);
    }

    protected function updatePreferences(UpdateProfileRequest $request): void
    {
        $request->user()->update([
            'locale' => $request->string('locale')->toString(),
            'timezone' => $request->string('timezone')->toString(),
        ]);
    }

    protected function successMessage(string $section): string
    {
        return match ($section) {
            'security' => __('Password updated successfully.'),
            'avatar' => __('Profile photo updated successfully.'),
            'preferences' => __('Preferences saved successfully.'),
            default => __('Profile updated successfully.'),
        };
    }

    public function revokeSession(Request $request, string $sessionId): RedirectResponse
    {
        $user = auth()->user();

        $this->sessionService->revokeSession($sessionId, $user->id);

        return back()->with('success', __('Session revoked successfully.'));
    }

    public function revokeAllSessions(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $this->sessionService->revokeAllOtherSessions($user->id, session()->getId());

        return back()->with('success', __('All other sessions have been revoked.'));
    }
}
