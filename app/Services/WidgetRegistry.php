<?php

namespace App\Services;

use App\Modules\Shared\Contracts\DashboardWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class WidgetRegistry
{
    /** @var array<string, DashboardWidget> */
    protected array $widgets = [];

    /**
     * Register a widget.
     */
    public function register(DashboardWidget $widget): void
    {
        $this->widgets[$widget->id()] = $widget;
    }

    /**
     * Get widgets for a specific panel, filtered by permission and sorted by position.
     *
     * @return Collection<int, DashboardWidget>
     */
    public function getForPanel(string $panel, mixed $user = null): Collection
    {
        return collect($this->widgets)
            ->filter(function (DashboardWidget $widget) use ($panel) {
                return $widget->panel() === $panel || $widget->panel() === 'all';
            })
            ->filter(function (DashboardWidget $widget) use ($user) {
                $permission = $widget->permission();

                if ($permission === null) {
                    return true;
                }

                return $user && method_exists($user, 'can') && $user->can($permission);
            })
            ->filter(fn (DashboardWidget $widget) => $widget->shouldRender())
            ->sortBy(fn (DashboardWidget $widget) => $widget->position())
            ->values();
    }

    /**
     * Render a widget with optional caching.
     */
    public function renderWidget(DashboardWidget $widget): string
    {
        $cacheFor = $widget->cacheFor();

        if ($cacheFor === null) {
            return $widget->render();
        }

        return Cache::remember(
            'widget:'.$widget->id(),
            $cacheFor,
            fn () => $widget->render()
        );
    }

    /**
     * Get all registered widgets.
     *
     * @return Collection<string, DashboardWidget>
     */
    public function all(): Collection
    {
        return collect($this->widgets);
    }
}
