<?php

namespace App\Modules\Shipping\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Shipping\Models\ShippingMethod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShippingMethodController extends Controller
{
    public function index(Request $request)
    {
        $workspaceId = app(\App\Modules\MarketingChannels\Services\WorkspaceResolver::class)->current($request->user())->id;
        
        $methods = ShippingMethod::where('workspace_id', $workspaceId)->get();

        return view('shipping::user.methods.index', compact('methods'));
    }

    public function create()
    {
        return view('shipping::user.methods.create');
    }

    public function store(Request $request)
    {
        $workspaceId = app(\App\Modules\MarketingChannels\Services\WorkspaceResolver::class)->current($request->user())->id;

        $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('shipping_methods')->where('workspace_id', $workspaceId)
            ],
            'carrier' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'estimated_delivery_min_days' => ['nullable', 'integer', 'min:0'],
            'estimated_delivery_max_days' => ['nullable', 'integer', 'min:0', 'gte:estimated_delivery_min_days'],
        ]);

        ShippingMethod::create([
            'workspace_id' => $workspaceId,
            'name' => $request->name,
            'code' => str($request->name)->slug(),
            'type' => 'manual',
            'carrier' => $request->carrier,
            'description' => $request->description,
            'estimated_delivery_min_days' => $request->estimated_delivery_min_days,
            'estimated_delivery_max_days' => $request->estimated_delivery_max_days,
            'is_active' => true,
        ]);

        return redirect()->route('user.shipping.methods.index')->with('success', __('Shipping method created.'));
    }

    public function edit(Request $request, ShippingMethod $method)
    {
        $workspaceId = app(\App\Modules\MarketingChannels\Services\WorkspaceResolver::class)->current($request->user())->id;
        abort_if($method->workspace_id !== $workspaceId, 403);

        return view('shipping::user.methods.edit', compact('method'));
    }

    public function update(Request $request, ShippingMethod $method)
    {
        $workspaceId = app(\App\Modules\MarketingChannels\Services\WorkspaceResolver::class)->current($request->user())->id;
        abort_if($method->workspace_id !== $workspaceId, 403);

        $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('shipping_methods')->where('workspace_id', $workspaceId)->ignore($method->id)
            ],
            'carrier' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'estimated_delivery_min_days' => ['nullable', 'integer', 'min:0'],
            'estimated_delivery_max_days' => ['nullable', 'integer', 'min:0', 'gte:estimated_delivery_min_days'],
        ]);

        $method->update([
            'name' => $request->name,
            'carrier' => $request->carrier,
            'description' => $request->description,
            'estimated_delivery_min_days' => $request->estimated_delivery_min_days,
            'estimated_delivery_max_days' => $request->estimated_delivery_max_days,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('user.shipping.methods.index')->with('success', __('Shipping method updated.'));
    }

    public function destroy(Request $request, ShippingMethod $method)
    {
        $workspaceId = app(\App\Modules\MarketingChannels\Services\WorkspaceResolver::class)->current($request->user())->id;
        abort_if($method->workspace_id !== $workspaceId, 403);

        $method->delete();

        return redirect()->route('user.shipping.methods.index')->with('success', __('Shipping method deleted.'));
    }
}
