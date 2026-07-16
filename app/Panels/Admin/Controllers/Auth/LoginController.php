<?php

namespace App\Panels\Admin\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\AccountLockedMail;
use App\Modules\AuditLog\Services\AuditLogService;
use App\Modules\LoginActivity\Services\LoginActivityService;
use App\Rules\RecaptchaValid;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected LoginActivityService $loginActivityService,
    ) {}

    public function showLoginForm(): View
    {
        return view('panels.admin.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'g-recaptcha-response' => [new RecaptchaValid],
        ]);

        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->loginActivityService->recordLockout('App\Models\Admin', $request);

            $seconds = RateLimiter::availableIn($throttleKey);
            $minutes = (int) ceil($seconds / 60);

            Mail::to($request->input('email'))->queue(
                new AccountLockedMail($request->input('email'), $request->ip(), $minutes)
            );

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('Too many login attempts. Please try again in :seconds seconds.', ['seconds' => $seconds])]);
        }

        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        if (! Auth::guard('admin')->attempt($credentials, $remember)) {
            RateLimiter::hit($throttleKey);

            $this->loginActivityService->recordFailed('App\Models\Admin', $request, [
                'email' => $request->input('email'),
            ]);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('Invalid credentials.')]);
        }

        $admin = Auth::guard('admin')->user();

        if (! $admin->is_active) {
            Auth::guard('admin')->logout();

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('Your account is inactive.')]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();

        $admin->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $this->loginActivityService->recordLogin('App\Models\Admin', $admin->id, $request);

        $this->auditLogService->logCustom('admin_login', [
            'admin_id' => $admin->id,
            'email' => $admin->email,
        ]);

        if ($admin->hasTwoFactorEnabled() && $admin->hasConfirmedTwoFactor()) {
            return redirect()->route('admin.two-factor.challenge');
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    protected function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->input('email')).'|admin|'.$request->ip());
    }
}
