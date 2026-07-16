<?php

namespace App\Http\Middleware;

use App\Services\Onboarding\OnboardingProgress;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $nextRoute = app(OnboardingProgress::class)->nextRoute($user);
        $workspace = app(OnboardingProgress::class)->workspaceFor($user);

        if ($nextRoute === route('onboarding.plan') && app(OnboardingProgress::class)->hasSubscription($workspace)) {
            return $next($request);
        }

        if ($nextRoute !== route('user.dashboard') && ! $request->is('onboarding*')) {
            return redirect()->to($nextRoute);
        }

        return $next($request);
    }
}
