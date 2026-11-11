<?php

namespace App\Modules\PlansSubscriptions\Services;

use App\Modules\PlansSubscriptions\Models\Plan;
use App\Services\Navigation\WorkspaceNavigationRegistry;

/**
 * Decides which user-panel modules and menu items a workspace's plan includes.
 */
class PlanFeatureGate
{
    /**
     * @var array<int, Plan|null>
     */
    protected array $plans = [];

    public function __construct(protected SubscriptionAccessService $access) {}

    public function allows(int $workspaceId, string $module, ?string $item = null): bool
    {
        if (in_array($module, WorkspaceNavigationRegistry::ALWAYS_INCLUDED_MODULES, true)) {
            return true;
        }

        $plan = $this->planFor($workspaceId);

        return $plan === null || $plan->includesFeature($module, $item);
    }

    public function allowsRoute(int $workspaceId, string $routeName): bool
    {
        return $this->deniedFeatureLabel($workspaceId, $routeName) === null;
    }

    /**
     * The name of the plan feature blocking this route, or null when the route is allowed.
     */
    public function deniedFeatureLabel(int $workspaceId, string $routeName): ?string
    {
        if ($this->planFor($workspaceId) === null) {
            return null;
        }

        $features = app(WorkspaceNavigationRegistry::class)->featuresForRoute($routeName);

        foreach ($features as $feature) {
            if ($this->allows($workspaceId, $feature['module'], $feature['item'])) {
                return null;
            }
        }

        return $features === [] ? null : $features[0]['label'];
    }

    /**
     * A permission is outside the plan when every menu item it unlocks is blocked.
     * Permissions not tied to any menu item are always in the plan.
     */
    public function includesPermission(int $workspaceId, string $permission): bool
    {
        $features = app(WorkspaceNavigationRegistry::class)->permissionFeatures()[$permission] ?? [];

        if ($features === []) {
            return true;
        }

        foreach ($features as $feature) {
            if ($this->allows($workspaceId, $feature['module'], $feature['item'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Workspaces without a subscription are not feature-gated; the subscription middleware handles them.
     */
    protected function planFor(int $workspaceId): ?Plan
    {
        if (! array_key_exists($workspaceId, $this->plans)) {
            $this->plans[$workspaceId] = $this->access->currentForWorkspace($workspaceId)?->plan;
        }

        return $this->plans[$workspaceId];
    }
}
