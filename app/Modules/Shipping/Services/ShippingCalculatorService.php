<?php

namespace App\Modules\Shipping\Services;

use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Shipping\Models\ShippingRate;
use App\Modules\Shipping\Models\ShippingSetting;
use App\Modules\Shipping\Models\ShippingZoneCountry;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Collection;

class ShippingCalculatorService
{
    /**
     * Calculate the weight for a set of cart items.
     *
     * @param  array  $items  Array of ['product_id' => int, 'variant_id' => int|null, 'quantity' => int]
     * @return array ['total_weight_kg' => float, 'items' => array]
     */
    public function calculateCartWeight(Workspace $workspace, array $items): array
    {
        $totalWeight = 0.0;
        $processedItems = [];

        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;
            $variantId = $item['variant_id'] ?? null;
            $quantity = max(1, (int) ($item['quantity'] ?? 1));

            if (! $productId) {
                continue;
            }

            $product = Product::query()
                ->where('workspace_id', $workspace->id)
                ->whereKey($productId)
                ->first();

            if (! $product) {
                continue;
            }

            $unitWeight = (float) $product->default_unit_weight_kg;

            if ($variantId) {
                $variant = ProductVariant::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('product_id', $product->id)
                    ->whereKey($variantId)
                    ->first();

                if ($variant && $variant->weight_kg !== null) {
                    $unitWeight = (float) $variant->weight_kg;
                }
            }

            $lineWeight = $unitWeight * $quantity;
            $totalWeight += $lineWeight;

            $processedItems[] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity' => $quantity,
                'unit_weight_kg' => $unitWeight,
                'total_weight_kg' => $lineWeight,
            ];
        }

        // Add packaging weight
        $settings = ShippingSetting::query()->where('workspace_id', $workspace->id)->first();
        $packagingWeight = 0.0;
        if ($settings && $settings->is_packaging_weight_enabled) {
            $packagingWeight = (float) $settings->default_packaging_weight_kg;
        }

        return [
            'product_weight_kg' => $totalWeight,
            'packaging_weight_kg' => $packagingWeight,
            'total_weight_kg' => $totalWeight + $packagingWeight,
            'items' => $processedItems,
        ];
    }

    /**
     * Retrieve applicable shipping options for a destination and weight.
     *
     * @return Collection<ShippingRate>
     */
    public function getAvailableRates(Workspace $workspace, string $countryCode, float $totalWeightKg): Collection
    {
        // 1. Find the shipping zone for this country
        $zoneCountry = ShippingZoneCountry::query()
            ->where('workspace_id', $workspace->id)
            ->where('country_code', strtoupper($countryCode))
            ->with('zone')
            ->first();

        if (! $zoneCountry || ! $zoneCountry->zone || ! $zoneCountry->zone->is_active) {
            return collect(); // No active zone for this country
        }

        // 2. Fetch rates matching the weight in this zone
        // Weight rule: min_weight_kg < weight <= max_weight_kg
        // Except if weight is exactly 0, then 0 <= 0
        return ShippingRate::query()
            ->with('method')
            ->where('workspace_id', $workspace->id)
            ->where('shipping_zone_id', $zoneCountry->zone->id)
            ->where('is_active', true)
            ->whereHas('method', fn ($query) => $query->where('is_active', true))
            ->where(function ($query) use ($totalWeightKg) {
                if ($totalWeightKg == 0) {
                    $query->where('min_weight_kg', 0);
                } else {
                    $query->where('min_weight_kg', '<', $totalWeightKg);
                }
            })
            ->where(function ($query) use ($totalWeightKg) {
                $query->where('max_weight_kg', '>=', $totalWeightKg)
                    ->orWhereNull('max_weight_kg');
            })
            ->get();
    }

    /**
     * Generate a full shipping quote.
     */
    public function getQuote(Workspace $workspace, array $cartItems, string $countryCode, ?int $selectedMethodId = null): array
    {
        $weightData = $this->calculateCartWeight($workspace, $cartItems);
        $totalWeight = $weightData['total_weight_kg'];

        $rates = $this->getAvailableRates($workspace, $countryCode, $totalWeight);

        $selectedRate = null;
        if ($selectedMethodId) {
            $selectedRate = $rates->firstWhere('shipping_method_id', $selectedMethodId);
        }

        // If no method selected or valid, pick the cheapest by default
        if (! $selectedRate && $rates->isNotEmpty()) {
            $selectedRate = $rates->sortBy('price')->first();
        }

        $shippingPrice = 0.0;
        if ($selectedRate) {
            $shippingPrice = (float) $selectedRate->price;
        }

        return [
            'weight_data' => $weightData,
            'available_rates' => $rates,
            'selected_rate' => $selectedRate,
            'shipping_price' => $shippingPrice,
            'shipping_currency' => $selectedRate ? $selectedRate->currency : 'USD',
        ];
    }
}
