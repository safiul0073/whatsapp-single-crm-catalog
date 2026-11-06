<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Storefront\Models\StoreDomain;
use App\Modules\Commerce\Storefront\Models\StorefrontPageView;
use App\Modules\Commerce\Storefront\Models\StorefrontVisitor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregates storefront visits for the Visitors overview: totals, daily trend and top cities/countries/pages.
 */
class VisitorAnalyticsService
{
    public const LIVE_MINUTES = 5;

    /** Number of city cards shown as large figures on the overview. */
    public const CITY_CARDS = 6;

    public function views(int $workspaceId, Carbon $from, Carbon $to): Builder
    {
        return StorefrontPageView::query()->where('workspace_id', $workspaceId)->whereBetween('viewed_at', [$from, $to]);
    }

    public function live(int $workspaceId): Builder
    {
        return StorefrontVisitor::query()->where('workspace_id', $workspaceId)->where('last_seen_at', '>=', now()->subMinutes(self::LIVE_MINUTES));
    }

    /**
     * @return array{visitors: int, page_views: int, customers: int, views_per_visitor: float, cities: int, unlocated: int, live: int}
     */
    public function totals(int $workspaceId, Carbon $from, Carbon $to): array
    {
        $views = $this->views($workspaceId, $from, $to);
        $visitors = (clone $views)->distinct()->count('visitor_id');
        $pageViews = (clone $views)->count();

        return [
            'visitors' => $visitors,
            'page_views' => $pageViews,
            'customers' => (clone $views)->whereNotNull('customer_id')->distinct()->count('customer_id'),
            'views_per_visitor' => $visitors ? round($pageViews / $visitors, 1) : 0.0,
            'cities' => (clone $views)->whereNotNull('city')->distinct()->count('city'),
            'unlocated' => (clone $views)->whereNull('city')->count(),
            'live' => $this->live($workspaceId)->count(),
        ];
    }

    /**
     * Cities ranked by page views, with distinct visitors and share of all views in the range.
     *
     * @return Collection<int, array{city: ?string, region: ?string, country_code: ?string, views: int, visitors: int, share: float}>
     */
    public function topCities(int $workspaceId, Carbon $from, Carbon $to, int $limit = 25): Collection
    {
        $total = max(1, $this->views($workspaceId, $from, $to)->count());

        return $this->views($workspaceId, $from, $to)
            ->whereNotNull('city')
            ->selectRaw('city, max(region) as region, country_code, count(*) as views, count(distinct visitor_id) as visitors')
            ->groupBy('city', 'country_code')
            ->orderByDesc('views')
            ->limit($limit)
            ->get()
            ->map(fn (StorefrontPageView $row): array => [
                'city' => $row->city,
                'region' => $row->getAttribute('region'),
                'country_code' => $row->country_code,
                'views' => (int) $row->getAttribute('views'),
                'visitors' => (int) $row->getAttribute('visitors'),
                'share' => round((int) $row->getAttribute('views') / $total * 100, 1),
            ]);
    }

    /**
     * @return Collection<int, array{country_code: ?string, views: int, visitors: int}>
     */
    public function topCountries(int $workspaceId, Carbon $from, Carbon $to, int $limit = 8): Collection
    {
        return $this->views($workspaceId, $from, $to)
            ->whereNotNull('country_code')
            ->selectRaw('country_code, count(*) as views, count(distinct visitor_id) as visitors')
            ->groupBy('country_code')
            ->orderByDesc('views')
            ->limit($limit)
            ->get()
            ->map(fn (StorefrontPageView $row): array => [
                'country_code' => $row->country_code,
                'views' => (int) $row->getAttribute('views'),
                'visitors' => (int) $row->getAttribute('visitors'),
            ]);
    }

    /**
     * @return Collection<string, int>
     */
    public function topPages(int $workspaceId, Carbon $from, Carbon $to, int $limit = 8): Collection
    {
        return $this->views($workspaceId, $from, $to)->selectRaw('path, count(*) as total')->groupBy('path')->orderByDesc('total')->limit($limit)->pluck('total', 'path')->map(fn ($total): int => (int) $total);
    }

    /**
     * Referrer hosts, excluding visits with no referrer.
     *
     * @return Collection<string, int>
     */
    public function topReferrers(int $workspaceId, Carbon $from, Carbon $to, int $limit = 8): Collection
    {
        return $this->views($workspaceId, $from, $to)
            ->whereNotNull('referrer')
            ->where('referrer', '!=', '')
            ->selectRaw('referrer, count(*) as total')
            ->groupBy('referrer')
            ->orderByDesc('total')
            ->limit(500)
            ->get()
            ->toBase()
            ->groupBy(fn (StorefrontPageView $row): string => strtolower((string) (parse_url((string) $row->referrer, PHP_URL_HOST) ?: $row->referrer)))
            ->except($this->storeHosts($workspaceId))
            ->map(fn (Collection $rows): int => (int) $rows->sum('total'))
            ->sortDesc()
            ->take($limit);
    }

    /**
     * Views and visitors grouped by a user-agent dimension (device, os or browser).
     *
     * @param  'device'|'os'|'browser'  $dimension
     * @return Collection<int, array{key: string, views: int, visitors: int, share: float}>
     */
    public function breakdown(int $workspaceId, Carbon $from, Carbon $to, string $dimension): Collection
    {
        $column = in_array($dimension, ['device', 'os', 'browser'], true) ? $dimension : 'device';
        $rows = $this->views($workspaceId, $from, $to)
            ->selectRaw($column.' as dimension, count(*) as views, count(distinct visitor_id) as visitors')
            ->groupBy($column)
            ->orderByDesc('views')
            ->get();
        $total = max(1, (int) $rows->sum('views'));

        return $rows->map(fn (StorefrontPageView $row): array => [
            'key' => (string) ($row->getAttribute('dimension') ?: 'unknown'),
            'views' => (int) $row->getAttribute('views'),
            'visitors' => (int) $row->getAttribute('visitors'),
            'share' => round((int) $row->getAttribute('views') / $total * 100, 1),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function storeHosts(int $workspaceId): array
    {
        return StoreDomain::query()->where('workspace_id', $workspaceId)->pluck('host')->map(fn (string $host): string => strtolower($host))->all();
    }

    /**
     * Views and unique visitors per day, including days with no traffic.
     *
     * @return array{labels: array<int, string>, views: array<int, int>, visitors: array<int, int>}
     */
    public function daily(int $workspaceId, Carbon $from, Carbon $to): array
    {
        $rows = $this->views($workspaceId, $from, $to)
            ->selectRaw('date(viewed_at) as day, count(*) as views, count(distinct visitor_id) as visitors')
            ->groupByRaw('date(viewed_at)')
            ->get()
            ->keyBy(fn (StorefrontPageView $row): string => substr((string) $row->getAttribute('day'), 0, 10));
        $series = ['labels' => [], 'views' => [], 'visitors' => []];
        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            $row = $rows->get($day->toDateString());
            $series['labels'][] = $day->format('M j');
            $series['views'][] = (int) $row?->getAttribute('views');
            $series['visitors'][] = (int) $row?->getAttribute('visitors');
        }

        return $series;
    }
}
