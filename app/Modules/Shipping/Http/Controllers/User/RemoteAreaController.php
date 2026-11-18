<?php

namespace App\Modules\Shipping\Http\Controllers\User;

use App\Modules\Shipping\Http\Requests\RemoteAreaCheckRequest;
use App\Modules\Shipping\Http\Requests\RemoteAreaRequest;
use App\Modules\Shipping\Models\CarrierAccount;
use App\Modules\Shipping\Models\RemoteArea;
use App\Modules\Shipping\Models\ShippingAgent;
use App\Modules\Shipping\Services\RemoteAreaService;
use App\Modules\Shipping\Services\ShippingAuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RemoteAreaController extends ShippingBaseController
{
    public function index(RemoteAreaCheckRequest $request, RemoteAreaService $remoteAreas): View
    {
        $workspaceId = $this->workspaceId($request);
        $check = $request->validated();
        $canViewCosts = $request->user()->can('shipping.costs.view');

        return view('shipping::user.management.remote-areas.index', [
            'check' => $check,
            'results' => filled($check['country'] ?? null)
                ? $remoteAreas->statusForAllProviders($workspaceId, $check['country'], $check['postal_code'] ?? null, $check['city'] ?? null)->filter(fn (array $row): bool => $canViewCosts || $row['agent_id'] === null)->values()
                : null,
            'areas' => RemoteArea::query()->with('agent')->where('workspace_id', $workspaceId)
                ->when(! $canViewCosts, fn ($query) => $query->whereNull('shipping_agent_id'))
                ->orderBy('country_code')->orderBy('postal_from')->paginate(50)->withQueryString(),
            'agents' => ShippingAgent::query()->where('workspace_id', $workspaceId)->orderBy('name')->get(),
            'carriers' => CarrierAccount::CARRIERS,
            'canManage' => $request->user()->can('shipping.agents.manage'),
            'canViewCosts' => $canViewCosts,
            'storeCurrency' => $this->storeCurrency($request),
            'countries' => $this->countries(),
        ]);
    }

    public function store(RemoteAreaRequest $request, ShippingAuditService $audit): RedirectResponse
    {
        $workspaceId = $this->workspaceId($request);
        $area = RemoteArea::query()->create($request->areaData($workspaceId) + ['workspace_id' => $workspaceId]);
        $audit->record($workspaceId, 'remote_area.added', 'Remote-area entry added for '.$area->country_code.' '.($area->postal_from ?: $area->city), null, null, $area->only(['carrier', 'shipping_agent_id', 'country_code', 'postal_from', 'postal_to', 'city', 'status', 'fee']));

        return back()->with('success', $area->verified_at
            ? __('Remote-area entry saved.')
            : __('Entry saved as unconfirmed. It will not be used until it is confirmed with the provider.'));
    }

    public function destroy(Request $request, RemoteArea $area, ShippingAuditService $audit): RedirectResponse
    {
        $this->assertWorkspace($request, $area);
        $old = $area->only(['carrier', 'shipping_agent_id', 'country_code', 'postal_from', 'postal_to', 'city', 'status', 'fee']);
        $area->delete();
        $audit->record($this->workspaceId($request), 'remote_area.deleted', 'Remote-area entry removed', null, $old);

        return back()->with('success', __('Remote-area entry removed.'));
    }
}
