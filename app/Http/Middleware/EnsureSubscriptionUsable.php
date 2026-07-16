<?php

namespace App\Http\Middleware;

use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\PlansSubscriptions\Services\SubscriptionAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionUsable
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $workspace = app(WorkspaceResolver::class)->current($user);

        if (! $workspace || app(SubscriptionAccessService::class)->canUseServices($workspace->id)) {
            return $next($request);
        }

        if ($this->routeIsAllowedWhileExpired($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, __(SubscriptionAccessService::EXPIRED_MESSAGE));
        }

        return redirect()
            ->route('user.subscription.show')
            ->with('error', __(SubscriptionAccessService::EXPIRED_MESSAGE));
    }

    protected function routeIsAllowedWhileExpired(Request $request): bool
    {
        $routeName = (string) $request->route()?->getName();

        if (str_starts_with($routeName, 'user.subscription.')
            || str_starts_with($routeName, 'user.profile.')
            || str_starts_with($routeName, 'user.system-notifications.')
            || $routeName === 'user.dashboard'
            || $routeName === 'user.global-search') {
            return true;
        }

        if (! $request->isMethodSafe()) {
            return false;
        }

        return str_ends_with($routeName, '.index')
            || str_ends_with($routeName, '.show')
            || str_ends_with($routeName, '.report')
            || str_ends_with($routeName, '.export')
            || str_ends_with($routeName, '.history')
            || str_ends_with($routeName, '.preview')
            || str_ends_with($routeName, '.preview-recipients')
            || str_ends_with($routeName, '.conversations')
            || str_ends_with($routeName, '.conversations.show');
    }
}
