<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $panel = app('current.panel');

        if (! $panel) {
            return $next($request);
        }

        $guard = $panel['guard'] ?? 'web';
        $panelKey = $panel['key'] ?? 'user';
        $user = Auth::guard($guard)->user();

        if (! $user || ! method_exists($user, 'hasTwoFactorEnabled')) {
            return $next($request);
        }

        // Check if 2FA is enabled for this panel via settings
        $isEnabled = $this->is2faEnabled($guard);

        if (! $isEnabled) {
            return $next($request);
        }

        // Check if 2FA is required for this panel
        $isRequired = $this->is2faRequired($guard);

        // If required but user hasn't set it up yet, redirect to setup
        if ($isRequired && ! $user->hasConfirmedTwoFactor()) {
            // Allow access to 2FA setup routes to avoid infinite redirect
            if ($request->routeIs("{$panelKey}.two-factor.*") || $request->routeIs("{$panelKey}.profile.*")) {
                return $next($request);
            }

            return redirect()->route("{$panelKey}.two-factor.setup")
                ->with('warning', __('Two-factor authentication is required. Please set it up to continue.'));
        }

        // If user has 2FA enabled, check if they've verified this session
        if ($user->hasTwoFactorEnabled() && $user->hasConfirmedTwoFactor()) {
            if (! $request->session()->get('2fa_verified')) {
                return redirect()->route("{$panelKey}.two-factor.challenge");
            }
        }

        return $next($request);
    }

    protected function is2faEnabled(string $guard): bool
    {
        if ($guard === 'admin') {
            return true; // 2FA is always available for admins
        }

        return (bool) setting('enable_2fa_for_users', true);
    }

    protected function is2faRequired(string $guard): bool
    {
        if ($guard === 'admin') {
            return (bool) setting('require_2fa_for_admins', false);
        }

        return (bool) setting('require_2fa_for_users', false);
    }
}
