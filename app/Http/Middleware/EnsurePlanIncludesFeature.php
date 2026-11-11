<?php

namespace App\Http\Middleware;

use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\PlansSubscriptions\Services\PlanFeatureGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanIncludesFeature
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $routeName = $request->route()?->getName();

        if (! $user || ! is_string($routeName) || $routeName === '') {
            return $next($request);
        }

        $workspace = app(WorkspaceResolver::class)->current($user);

        if (! $workspace) {
            return $next($request);
        }

        $feature = app(PlanFeatureGate::class)->deniedFeatureLabel($workspace->id, $routeName);

        if ($feature === null) {
            return $next($request);
        }

        $message = __("Your current plan doesn't include :feature. Upgrade to unlock it.", ['feature' => $feature]);

        if ($request->expectsJson()) {
            abort(403, $message);
        }

        return redirect()->route('user.subscription.show')->with('error', $message);
    }
}
