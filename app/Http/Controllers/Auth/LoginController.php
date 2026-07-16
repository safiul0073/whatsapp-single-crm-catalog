<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Mail\AccountLockedMail;
use App\Modules\AuditLog\Services\AuditLogService;
use App\Modules\LoginActivity\Services\LoginActivityService;
use Illuminate\Http\RedirectResponse;
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
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->loginActivityService->recordLockout('App\Models\User', $request);

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

        if (! Auth::guard('web')->attempt($credentials, $remember)) {
            RateLimiter::hit($throttleKey);

            $this->loginActivityService->recordFailed('App\Models\User', $request, [
                'email' => $request->input('email'),
            ]);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => $this->failedLoginMessage($request)]);
        }

        $user = Auth::guard('web')->user();

        if (! $user->is_active) {
            Auth::guard('web')->logout();

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('Your account is inactive.')]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $this->loginActivityService->recordLogin('App\Models\User', $user->id, $request);

        $this->auditLogService->logCustom('login', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return redirect()->intended(route('user.dashboard'));
    }

    protected function throttleKey(LoginRequest $request): string
    {
        return Str::transliterate(Str::lower($request->input('email')).'|'.$request->ip());
    }

    protected function failedLoginMessage(LoginRequest $request): string
    {
        return __('Invalid credentials.');
    }
}
