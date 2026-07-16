<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Modules\SystemNotifications\Services\SystemNotificationService;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function __construct(protected SystemNotificationService $notifications) {}

    public function notice(Request $request): RedirectResponse|View
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('user.dashboard'));
        }

        return view('auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->route('user.dashboard')
            ->with('success', __('Your email has been verified successfully.'));
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('user.dashboard'));
        }

        $user = $request->user();
        $user->sendEmailVerificationNotification();

        $admins = Admin::where('is_active', true)->get();
        $this->notifications->sendToMany($admins, [
            'title' => __('Email verification requested'),
            'body' => $user->name.' ('.$user->email.')',
            'icon' => 'ph-envelope-simple',
            'url' => route('admin.users.show', $user),
            'type' => 'info',
        ], 'verification_request');

        return back()->with('success', __('A new verification link has been sent to your email address.'));
    }
}
