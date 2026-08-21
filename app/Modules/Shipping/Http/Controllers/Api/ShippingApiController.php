<?php

namespace App\Modules\Shipping\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Shipping\Services\ShippingCalculatorService;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingApiController extends Controller
{
    public function __construct(
        protected ShippingCalculatorService $calculator
    ) {}

    public function calculateRates(Request $request): JsonResponse
    {
        $request->validate([
            'workspace_id' => ['nullable', 'integer'], // Made optional for frontend ease
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'shipping_address.country_code' => ['required', 'string', 'size:2'],
        ]);

        $workspaceId = $request->input('workspace_id');
        $workspace = $workspaceId ? Workspace::query()->findOrFail($workspaceId) : Workspace::query()->firstOrFail();


        $countryCode = $request->input('shipping_address.country_code');
        $items = $request->input('items');

        $weightData = $this->calculator->calculateCartWeight($workspace, $items);
        $rates = $this->calculator->getAvailableRates($workspace, $countryCode, $weightData['total_weight_kg']);

        return response()->json([
            'weight_data' => $weightData,
            'shipping_options' => $rates->map(fn ($rate) => [
                'id' => $rate->method->id,
                'rate_id' => $rate->id,
                'name' => $rate->method->name,
                'carrier' => $rate->method->carrier,
                'price' => $rate->price,
                'currency' => $rate->currency,
                'estimated_delivery' => $this->formatEstimatedDelivery($rate->method->estimated_delivery_min_days, $rate->method->estimated_delivery_max_days),
            ]),
        ]);
    }

    public function quote(Request $request): JsonResponse
    {
        $request->validate([
            'workspace_id' => ['nullable', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'shipping_address.country_code' => ['required', 'string', 'size:2'],
            'shipping_method_id' => ['nullable', 'integer'],
        ]);

        $workspaceId = $request->input('workspace_id');
        $workspace = $workspaceId ? Workspace::query()->findOrFail($workspaceId) : Workspace::query()->firstOrFail();


        $countryCode = $request->input('shipping_address.country_code');
        $items = $request->input('items');
        $methodId = $request->input('shipping_method_id');

        $quote = $this->calculator->getQuote($workspace, $items, $countryCode, $methodId);

        return response()->json([
            'shipping' => [
                'method_id' => $quote['selected_rate'] ? $quote['selected_rate']->shipping_method_id : null,
                'zone_id' => $quote['selected_rate'] ? $quote['selected_rate']->shipping_zone_id : null,
                'weight_kg' => $quote['weight_data']['total_weight_kg'],
                'price' => $quote['shipping_price'],
                'currency' => $quote['shipping_currency'],
            ],
            'available_rates' => $quote['available_rates']->map(fn ($rate) => [
                'id' => $rate->method->id,
                'name' => $rate->method->name,
                'price' => $rate->price,
                'currency' => $rate->currency,
            ]),
        ]);
    }

    protected function formatEstimatedDelivery(?int $min, ?int $max): string
    {
        if ($min && $max) {
            return "{$min}-{$max} business days";
        }
        if ($min) {
            return "From {$min} business days";
        }
        if ($max) {
            return "Up to {$max} business days";
        }

        return '';
    }
}
