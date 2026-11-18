<?php

namespace App\Modules\Shipping\Http\Controllers\User;

use App\Modules\Shipping\Http\Requests\CalculatorRequest;
use App\Modules\Shipping\Models\CarrierAccount;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Models\ShippingAgent;
use App\Modules\Shipping\Services\ShipmentWeightCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ToolsController extends ShippingBaseController
{
    public function calculator(Request $request): View
    {
        return view('shipping::user.management.calculator', ['results' => null, 'cartons' => [], 'flagLabels' => ShipmentWeightCalculator::flagLabels()]);
    }

    /**
     * Billable weight for the same cartons under every provider's own divisor and rounding.
     */
    public function calculate(CalculatorRequest $request, ShipmentWeightCalculator $weights): View
    {
        $workspaceId = $this->workspaceId($request);
        $cartons = $request->cartons();

        $providers = collect([['name' => __('Standard (÷:divisor)', ['divisor' => config('shipping_carriers.default_dim_divisor')]), 'divisor' => null, 'step' => null]])
            ->concat(CarrierAccount::query()->where('workspace_id', $workspaceId)->active()->orderBy('name')->get()->map(fn (CarrierAccount $account): array => ['name' => $account->name.' ('.$account->carrierLabel().')', 'divisor' => $account->dim_divisor, 'step' => $account->weight_rounding_step]))
            ->concat(ShippingAgent::query()->where('workspace_id', $workspaceId)->active()->orderBy('name')->get()->map(fn (ShippingAgent $agent): array => ['name' => $agent->name, 'divisor' => $agent->dim_divisor, 'step' => $agent->weight_rounding_step]));

        return view('shipping::user.management.calculator', [
            'cartons' => $cartons,
            'results' => $providers->map(fn (array $provider): array => ['name' => $provider['name']] + $weights->calculate($cartons, $provider['divisor'], $provider['step'])),
            'flagLabels' => ShipmentWeightCalculator::flagLabels(),
        ]);
    }

    public function comparison(Request $request): View
    {
        $shipments = Shipment::query()
            ->with(['order', 'quotes.agent', 'approvedQuote.agent'])
            ->withCount('quotes')
            ->where('workspace_id', $this->workspaceId($request))
            ->whereIn('status', ['pending', 'quoted', 'approved'])
            ->latest('id')
            ->paginate(25);

        return view('shipping::user.management.comparison', ['shipments' => $shipments]);
    }
}
