<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\AuditLog\Services\AuditLogService;
use App\Modules\AuthApi\Services\SocialAccountService;
use App\Modules\LoginActivity\Services\LoginActivityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialLoginController extends Controller
{
    private const PROVIDERS = ['google', 'facebook', 'github'];

    public function __construct(
        protected SocialAccountService $socialAccountService,
        protected AuditLogService $auditLogService,
        protected LoginActivityService $loginActivityService,
    ) {}

    public function redirect(string $provider): RedirectResponse
    {
        if (! $this->isEnabled($provider)) {
            return redirect()->route('login')
                ->withErrors(['email' => __('That sign-in method is unavailable.')]);
        }

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        if (! $this->isEnabled($provider)) {
            return redirect()->route('login')
                ->withErrors(['email' => __('That sign-in method is unavailable.')]);
        }

        try {
            $providerUser = Socialite::driver($provider)->user();
        } catch (Throwable) {
            return redirect()->route('login')
                ->withErrors(['email' => __('We could not complete the sign-in. Please try again.')]);
        }

        $user = $this->socialAccountService->resolveOrCreate($provider, $providerUser);

        if (! $user->is_active) {
            return redirect()->route('login')
                ->withErrors(['email' => __('Your account is inactive.')]);
        }

        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $this->loginActivityService->recordLogin('App\Models\User', $user->id, $request);

        $this->auditLogService->logCustom('login.social', [
            'user_id' => $user->id,
            'email' => $user->email,
            'provider' => $provider,
        ]);

        return redirect()->intended(route('user.dashboard'));
    }

    private function isEnabled(string $provider): bool
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            return false;
        }

        return (bool) setting("social_{$provider}_enabled", false)
            && (string) config("services.{$provider}.client_id") !== ''
            && (string) config("services.{$provider}.client_secret") !== '';
    }
}
