<?php

namespace App\Http\Middleware;

use App\Modules\Shared\Support\ModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Symfony\Component\HttpFoundation\Response;

class PanelAccess
{
    public function handle(Request $request, Closure $next, string $panel): Response
    {
        $config = config("panels.{$panel}");

        if (! $config || empty($config['active'])) {
            abort(404);
        }

        // Get the user from the panel's configured guard
        $guard = $config['guard'] ?? 'web';
        $user = Auth::guard($guard)->user();

        if (! $user) {
            abort(403, "You don't have access to this panel.");
        }

        // Check role-based access (empty roles = all authenticated users allowed)
        $requiredRoles = $config['roles'] ?? [];

        if (! empty($requiredRoles) && ! $user->hasAnyRole($requiredRoles)) {
            abort(403, "You don't have access to this panel.");
        }

        $config['navigation'] = array_merge(
            $config['navigation'] ?? [],
            app(ModuleRegistry::class)->buildNavigation($panel)
        );

        // Register panel-specific component overrides
        $this->registerComponentOverrides($panel, $config);

        // Share panel context with all views
        view()->share('panel', $panel);
        view()->share('panelConfig', $config);
        view()->share('panelGuard', $guard);
        view()->share('authUser', $user);

        return $next($request);
    }

    protected function registerComponentOverrides(string $panel, array $config): void
    {
        $componentsMode = $config['components'] ?? 'default';

        if ($componentsMode !== 'custom') {
            return;
        }

        $panelComponentsPath = resource_path("views/panels/{$panel}/components");

        if (is_dir($panelComponentsPath)) {
            // Register panel's component directory with highest priority
            // Components found here override the shared ones in resources/views/components/
            Blade::anonymousComponentPath($panelComponentsPath);
        }
    }
}
