<?php

namespace App\Modules\Commerce\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Storefront\Models\StorefrontPageView;
use App\Modules\Commerce\Storefront\Models\StorefrontVisitor;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class VisitorController extends Controller
{
    public const LIVE_MINUTES = 5;

    public function __construct(protected WorkspaceResolver $workspaces) {}

    public function index(Request $request): View
    {
        $workspaceId = $this->workspaces->current($request->user())->id;
        $date = rescue(fn () => Carbon::parse((string) $request->query('date', today()->toDateString())), today(), false)->startOfDay();
        $end = $date->copy()->endOfDay();

        $views = StorefrontPageView::query()->where('workspace_id', $workspaceId)->whereBetween('viewed_at', [$date, $end]);

        $visitors = StorefrontVisitor::query()
            ->with('customer.contact')
            ->where('workspace_id', $workspaceId)
            ->whereHas('pageViews', fn ($q) => $q->whereBetween('viewed_at', [$date, $end]))
            ->withCount(['pageViews as day_views' => fn ($q) => $q->whereBetween('viewed_at', [$date, $end])])
            ->withMin(['pageViews as day_first' => fn ($q) => $q->whereBetween('viewed_at', [$date, $end])], 'viewed_at')
            ->withMax(['pageViews as day_last' => fn ($q) => $q->whereBetween('viewed_at', [$date, $end])], 'viewed_at')
            ->when($request->filled('country'), fn ($q) => $q->where('country_code', strtoupper((string) $request->query('country'))))
            ->orderByDesc('day_last')
            ->paginate(25)
            ->withQueryString();

        return view('commerce::user.visitors', [
            'date' => $date,
            'visitors' => $visitors,
            'stats' => [
                'visitors' => (clone $views)->distinct()->count('visitor_id'),
                'page_views' => (clone $views)->count(),
                'customers' => (clone $views)->whereNotNull('customer_id')->distinct()->count('customer_id'),
                'live' => $this->liveQuery($workspaceId)->count(),
            ],
            'countries' => (clone $views)->selectRaw('country_code, count(distinct visitor_id) as total')->groupBy('country_code')->orderByDesc('total')->limit(8)->pluck('total', 'country_code'),
            'topPages' => (clone $views)->selectRaw('path, count(*) as total')->groupBy('path')->orderByDesc('total')->limit(8)->pluck('total', 'path'),
        ]);
    }

    public function live(Request $request): JsonResponse
    {
        $workspaceId = $this->workspaces->current($request->user())->id;
        $visitors = $this->liveQuery($workspaceId)->with(['customer.contact', 'pageViews' => fn ($q) => $q->latest('viewed_at')->limit(1)])->latest('last_seen_at')->limit(50)->get();

        return response()->json([
            'count' => $visitors->count(),
            'visitors' => $visitors->map(fn (StorefrontVisitor $v) => [
                'id' => $v->id,
                'ip' => $v->ip,
                'country' => $v->country_name ?: $v->country_code,
                'city' => $v->city,
                'device' => $v->device,
                'customer' => $v->customer?->contact?->name,
                'path' => $v->pageViews->first()?->path,
                'last_seen' => $v->last_seen_at?->diffForHumans(),
            ])->values(),
        ]);
    }

    public function show(Request $request, StorefrontVisitor $visitor): JsonResponse
    {
        abort_unless($visitor->workspace_id === $this->workspaces->current($request->user())->id, 404);

        return response()->json([
            'views' => $visitor->pageViews()->latest('viewed_at')->limit(200)->get(['path', 'referrer', 'viewed_at'])->map(fn (StorefrontPageView $v) => [
                'path' => $v->path,
                'referrer' => $v->referrer,
                'at' => $v->viewed_at->format('M j, H:i:s'),
            ]),
        ]);
    }

    private function liveQuery(int $workspaceId): Builder
    {
        return StorefrontVisitor::query()->where('workspace_id', $workspaceId)->where('last_seen_at', '>=', now()->subMinutes(self::LIVE_MINUTES));
    }
}
