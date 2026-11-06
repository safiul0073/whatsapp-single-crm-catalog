<?php

namespace App\Modules\Commerce\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Http\Requests\VisitorAnalyticsRequest;
use App\Modules\Commerce\Storefront\Models\StorefrontPageView;
use App\Modules\Commerce\Storefront\Models\StorefrontVisitor;
use App\Modules\Commerce\Storefront\Services\UserAgentParser;
use App\Modules\Commerce\Storefront\Services\VisitorAnalyticsService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VisitorController extends Controller
{
    public const LIVE_MINUTES = VisitorAnalyticsService::LIVE_MINUTES;

    public function __construct(protected WorkspaceResolver $workspaces, protected VisitorAnalyticsService $analytics) {}

    /** Overview: totals, daily trend and where visitors come from, with cities as the headline figures. */
    public function index(VisitorAnalyticsRequest $request): View
    {
        $workspaceId = $this->workspaceId($request);
        [$from, $to] = $request->period();

        return view('commerce::user.visitors.overview', [
            'tab' => 'overview',
            'range' => $request->rangeKey(),
            'from' => $from,
            'to' => $to,
            'stats' => $this->analytics->totals($workspaceId, $from, $to),
            'cities' => $this->analytics->topCities($workspaceId, $from, $to),
            'countries' => $this->analytics->topCountries($workspaceId, $from, $to),
            'topPages' => $this->analytics->topPages($workspaceId, $from, $to),
            'referrers' => $this->analytics->topReferrers($workspaceId, $from, $to),
            'platforms' => $this->analytics->breakdown($workspaceId, $from, $to, 'os'),
            'deviceTypes' => $this->analytics->breakdown($workspaceId, $from, $to, 'device'),
            'browsers' => $this->analytics->breakdown($workspaceId, $from, $to, 'browser'),
            'daily' => $this->analytics->daily($workspaceId, $from, $to),
        ]);
    }

    public function livePage(Request $request): View
    {
        return view('commerce::user.visitors.live', [
            'tab' => 'live',
            'liveCount' => $this->analytics->live($this->workspaceId($request))->count(),
        ]);
    }

    /** Every visitor in the period, filterable by place, device and customer status. */
    public function list(VisitorAnalyticsRequest $request): View
    {
        $workspaceId = $this->workspaceId($request);
        [$from, $to] = $request->period();
        $inRange = fn ($query) => $query->whereBetween('viewed_at', [$from, $to]);

        $visitors = StorefrontVisitor::query()
            ->with('customer.contact')
            ->where('workspace_id', $workspaceId)
            ->whereHas('pageViews', $inRange)
            ->withCount(['pageViews as range_views' => $inRange])
            ->withMin(['pageViews as range_first' => $inRange], 'viewed_at')
            ->withMax(['pageViews as range_last' => $inRange], 'viewed_at')
            ->when($request->filled('country'), fn ($q) => $q->where('country_code', strtoupper((string) $request->validated('country'))))
            ->when($request->filled('city'), fn ($q) => $q->where('city', (string) $request->validated('city')))
            ->when($request->filled('device'), fn ($q) => $q->where('device', (string) $request->validated('device')))
            ->when($request->filled('os'), fn ($q) => $q->where('os', (string) $request->validated('os')))
            ->when($request->boolean('customers'), fn ($q) => $q->whereNotNull('customer_id'))
            ->orderByDesc('range_last')
            ->paginate(25)
            ->withQueryString();

        return view('commerce::user.visitors.list', [
            'tab' => 'list',
            'range' => $request->rangeKey(),
            'from' => $from,
            'to' => $to,
            'visitors' => $visitors,
            'cityOptions' => $this->analytics->topCities($workspaceId, $from, $to, 50)->pluck('city')->filter()->unique()->values(),
            'countryOptions' => $this->analytics->topCountries($workspaceId, $from, $to, 50)->pluck('country_code')->filter()->values(),
        ]);
    }

    public function live(Request $request): JsonResponse
    {
        $visitors = $this->analytics->live($this->workspaceId($request))
            ->with(['customer.contact', 'latestPageView'])
            ->latest('last_seen_at')
            ->limit(50)
            ->get();

        return response()->json([
            'count' => $visitors->count(),
            'cities' => $visitors->groupBy(fn (StorefrontVisitor $v): string => $v->city ?: __('Unknown'))->map->count()->sortDesc()->map(fn (int $total, string $city): array => ['city' => $city, 'count' => $total])->values(),
            'visitors' => $visitors->map(fn (StorefrontVisitor $v): array => [
                'id' => $v->id,
                'ip' => $v->ip,
                'country' => $v->country_name ?: $v->country_code,
                'city' => $v->city,
                'region' => $v->region,
                'device' => $v->device,
                'os' => $v->os ? UserAgentParser::label($v->os) : null,
                'browser' => $v->browser ? UserAgentParser::label($v->browser) : null,
                'customer' => $v->customer?->contact?->name,
                'path' => $v->latestPageView?->path,
                'last_seen' => $v->last_seen_at?->diffForHumans(),
            ])->values(),
        ]);
    }

    public function show(Request $request, StorefrontVisitor $visitor): JsonResponse
    {
        abort_unless($visitor->workspace_id === $this->workspaceId($request), 404);

        return response()->json([
            'views' => $visitor->pageViews()->latest('viewed_at')->limit(200)->get(['path', 'referrer', 'city', 'viewed_at'])->map(fn (StorefrontPageView $v): array => [
                'path' => $v->path,
                'referrer' => $v->referrer,
                'city' => $v->city,
                'at' => $v->viewed_at->format('M j, H:i:s'),
            ]),
        ]);
    }

    private function workspaceId(Request $request): int
    {
        return $this->workspaces->current($request->user())->id;
    }
}
