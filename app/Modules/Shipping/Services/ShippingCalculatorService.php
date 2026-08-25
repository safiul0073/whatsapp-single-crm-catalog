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
        $totalVolumetricAir = 0.0;
        $totalVolumetricSea = 0.0;
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
            $dimensions = $product->default_package_dimensions ?? [];

            if ($variantId) {
                $variant = ProductVariant::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('product_id', $product->id)
                    ->whereKey($variantId)
                    ->first();

                if ($variant) {
                    if ($variant->weight_kg !== null) {
                        $unitWeight = (float) $variant->weight_kg;
                    }
                    if (!empty($variant->package_dimensions)) {
                        $dimensions = $variant->package_dimensions;
                    }
                }
            }

            $lineWeight = $unitWeight * $quantity;
            $totalWeight += $lineWeight;

            // Volumetric calculation: (L x W x H)
            $l = (float) ($dimensions['length_cm'] ?? 0);
            $w = (float) ($dimensions['width_cm'] ?? 0);
            $h = (float) ($dimensions['height_cm'] ?? 0);
            $volumeCm3 = $l * $w * $h;

            $unitVolAir = $volumeCm3 / 5000;
            $unitVolSea = $volumeCm3 / 1000; // 1 CBM = 1,000,000 cm3 => 1,000,000 / 1000 = 1000 kg

            $lineVolAir = $unitVolAir * $quantity;
            $lineVolSea = $unitVolSea * $quantity;

            $totalVolumetricAir += $lineVolAir;
            $totalVolumetricSea += $lineVolSea;

            $processedItems[] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity' => $quantity,
                'unit_weight_kg' => $unitWeight,
                'total_weight_kg' => $lineWeight,
                'volumetric_weight_air_kg' => $lineVolAir,
                'volumetric_weight_sea_kg' => $lineVolSea,
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
            'total_physical_weight_kg' => $totalWeight + $packagingWeight,
            'total_volumetric_air_kg' => $totalVolumetricAir + $packagingWeight,
            'total_volumetric_sea_kg' => $totalVolumetricSea + $packagingWeight,
            'items' => $processedItems,
        ];
    }

    /**
     * Retrieve applicable shipping options for a destination and weight data.
     *
     * @return Collection<ShippingRate>
     */
    public function getAvailableRates(Workspace $workspace, string $countryCode, array $weightData): Collection
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

        // 2. Fetch ALL active rates for this zone
        $rates = ShippingRate::query()
            ->with('method')
            ->where('workspace_id', $workspace->id)
            ->where('shipping_zone_id', $zoneCountry->zone->id)
            ->where('is_active', true)
            ->whereHas('method', fn ($query) => $query->where('is_active', true))
            ->get();

        // 3. Filter in PHP based on Chargeable Weight for each method type
        return $rates->filter(function (ShippingRate $rate) use ($weightData) {
            $methodType = strtolower($rate->method->type ?? 'air');
            
            $physicalWeight = $weightData['total_physical_weight_kg'];
            $volumetricWeight = $methodType === 'sea' 
                ? $weightData['total_volumetric_sea_kg'] 
                : $weightData['total_volumetric_air_kg'];

            $chargeableWeight = max($physicalWeight, $volumetricWeight);

            // Temporarily store chargeable weight on the rate for later use
            $rate->chargeable_weight_kg = $chargeableWeight;

            // Check if weight falls in bracket
            if ($chargeableWeight == 0 && $rate->min_weight_kg > 0) {
                return false;
            }
            if ($chargeableWeight != 0 && $chargeableWeight <= $rate->min_weight_kg) {
                return false;
            }
            if ($rate->max_weight_kg !== null && $chargeableWeight > $rate->max_weight_kg) {
                return false;
            }
            return true;
        })->values();
    }

    /**
     * Generate a full shipping quote.
     */
    public function getQuote(Workspace $workspace, array $cartItems, string $countryCode, ?int $selectedMethodId = null): array
    {
        $weightData = $this->calculateCartWeight($workspace, $cartItems);

        $rates = $this->getAvailableRates($workspace, $countryCode, $weightData);

        $selectedRate = null;
        if ($selectedMethodId) {
            $selectedRate = $rates->firstWhere('shipping_method_id', $selectedMethodId);
        }

        // If no method selected or valid, pick the cheapest by default
        if (! $selectedRate && $rates->isNotEmpty()) {
            // Sort by calculated dynamic price
            $selectedRate = $rates->sortBy(function (ShippingRate $rate) {
                return (float) $rate->price + ((float) $rate->price_per_kg * $rate->chargeable_weight_kg);
            })->first();
        }

        $shippingPrice = 0.0;
        if ($selectedRate) {
            $basePrice = (float) $selectedRate->price;
            $pricePerKg = (float) $selectedRate->price_per_kg;
            $chargeableWeight = $selectedRate->chargeable_weight_kg;
            $shippingPrice = round($basePrice + ($pricePerKg * $chargeableWeight), 2);
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
