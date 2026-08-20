<?php

namespace App\Modules\Shipping\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Shipping\Models\ShippingSetting;
use App\Modules\Shipping\Models\ShippingZone;
use App\Modules\Shipping\Models\ShippingRate;
use App\Modules\Shipping\Models\ShippingMethod;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    public function index(Request $request): View
    {
        $workspaceId = app(\App\Modules\MarketingChannels\Services\WorkspaceResolver::class)->current($request->user())->id;

        $zones = ShippingZone::query()
            ->with(['countries', 'rates.method'])
            ->where('workspace_id', $workspaceId)
            ->get();

        return view('shipping::user.shipping.index', [
            'zones' => $zones,
        ]);
    }

    public function settings(Request $request): View
    {
        $workspaceId = app(\App\Modules\MarketingChannels\Services\WorkspaceResolver::class)->current($request->user())->id;

        $settings = ShippingSetting::query()->firstOrCreate(
            ['workspace_id' => $workspaceId],
            [
                'default_packaging_weight_kg' => 0,
                'is_packaging_weight_enabled' => false,
            ]
        );

        return view('shipping::user.shipping.settings', [
            'settings' => $settings,
        ]);
    }

    public function updateSettings(Request $request)
    {
        $workspaceId = app(\App\Modules\MarketingChannels\Services\WorkspaceResolver::class)->current($request->user())->id;
        
        $request->validate([
            'default_packaging_weight_kg' => ['required', 'numeric', 'min:0'],
            'is_packaging_weight_enabled' => ['boolean'],
        ]);

        ShippingSetting::query()->updateOrCreate(
            ['workspace_id' => $workspaceId],
            [
                'default_packaging_weight_kg' => $request->default_packaging_weight_kg,
                'is_packaging_weight_enabled' => $request->boolean('is_packaging_weight_enabled'),
            ]
        );

        return back()->with('success', __('Shipping settings updated successfully.'));
    }

    public function createZone(): View
    {
        return view('shipping::user.shipping.create_zone');
    }

    public function storeZone(Request $request)
    {
        $workspaceId = app(\App\Modules\MarketingChannels\Services\WorkspaceResolver::class)->current($request->user())->id;

        $request->validate([
            'name' => [
                'required', 
                'string', 
                'max:80',
                \Illuminate\Validation\Rule::unique('shipping_zones')->where(function ($query) use ($workspaceId) {
                    return $query->where('workspace_id', $workspaceId);
                })
            ],
            'countries' => ['required', 'array'],
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $workspaceId) {
            $zone = ShippingZone::create([
                'workspace_id' => $workspaceId,
                'name' => $request->name,
                'is_active' => true,
            ]);

            foreach ($request->countries as $country) {
                $zone->countries()->create([
                    'workspace_id' => $workspaceId,
                    'country_code' => $country,
                ]);
            }
        });

        return redirect()->route('user.shipping.index')->with('success', __('Shipping zone created successfully.'));
    }

    public function editZone(Request $request, ShippingZone $zone): View
    {
        $workspaceId = app(\App\Modules\MarketingChannels\Services\WorkspaceResolver::class)->current($request->user())->id;
        abort_if($zone->workspace_id !== $workspaceId, 403);
        
        $zone->load('countries', 'rates.method');
        $methods = ShippingMethod::where('workspace_id', $workspaceId)->where('is_active', true)->get();
        
        if ($methods->isEmpty()) {
            $defaultMethod = ShippingMethod::create([
                'workspace_id' => $workspaceId,
                'name' => 'Standard Shipping',
                'code' => 'standard',
                'type' => 'manual',
                'is_active' => true,
            ]);
            $methods->push($defaultMethod);
        }
        
        return view('shipping::user.shipping.edit_zone', compact('zone', 'methods'));
    }

    public function updateZone(Request $request, ShippingZone $zone)
    {
        $workspaceId = app(\App\Modules\MarketingChannels\Services\WorkspaceResolver::class)->current($request->user())->id;
        abort_if($zone->workspace_id !== $workspaceId, 403);

        $request->validate([
            'name' => [
                'required', 
                'string', 
                'max:80',
                \Illuminate\Validation\Rule::unique('shipping_zones')->where(function ($query) use ($workspaceId) {
                    return $query->where('workspace_id', $workspaceId);
                })->ignore($zone->id)
            ],
            'countries' => ['required', 'array'],
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $zone) {
            $zone->update([
                'name' => $request->name,
                'is_active' => $request->boolean('is_active', true),
            ]);

            $zone->countries()->delete();
            foreach ($request->countries as $country) {
                $zone->countries()->create([
                    'workspace_id' => $zone->workspace_id,
                    'country_code' => $country,
                ]);
            }
        });

        return redirect()->route('user.shipping.index')->with('success', __('Shipping zone updated successfully.'));
    }

    public function destroyZone(Request $request, ShippingZone $zone)
    {
        $workspaceId = app(\App\Modules\MarketingChannels\Services\WorkspaceResolver::class)->current($request->user())->id;
        abort_if($zone->workspace_id !== $workspaceId, 403);

        $zone->delete();

        return redirect()->route('user.shipping.index')->with('success', __('Shipping zone deleted successfully.'));
    }

    public function storeRate(Request $request, ShippingZone $zone)
    {
        $workspaceId = app(\App\Modules\MarketingChannels\Services\WorkspaceResolver::class)->current($request->user())->id;
        abort_if($zone->workspace_id !== $workspaceId, 403);

        $request->validate([
            'shipping_method_id' => ['required', 'exists:shipping_methods,id'],
            'min_weight_kg' => ['required', 'numeric', 'min:0'],
            'max_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        $zone->rates()->create([
            'workspace_id' => $workspaceId,
            'shipping_method_id' => $request->shipping_method_id,
            'min_weight_kg' => $request->min_weight_kg,
            'max_weight_kg' => $request->max_weight_kg,
            'price' => $request->price,
        ]);

        return back()->with('success', __('Shipping rate added successfully.'));
    }

    public function destroyRate(Request $request, ShippingRate $rate)
    {
        $workspaceId = app(\App\Modules\MarketingChannels\Services\WorkspaceResolver::class)->current($request->user())->id;
        abort_if($rate->workspace_id !== $workspaceId, 403);

        $rate->delete();

        return back()->with('success', __('Shipping rate removed successfully.'));
    }
}
