<?php

namespace App\Modules\Shipping\Database\Seeders;

use App\Modules\Shipping\Models\ShippingMethod;
use App\Modules\Shipping\Models\ShippingRate;
use App\Modules\Shipping\Models\ShippingZone;
use App\Modules\Shipping\Models\ShippingZoneCountry;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Seeder;

class ShippingSeeder extends Seeder
{
    public function run(): void
    {
        $workspace = Workspace::query()->first();
        if (!$workspace) {
            $this->command->warn('No workspace found. Skipping Shipping Seeder.');
            return;
        }

        $workspaceId = $workspace->id;

        // Create Shipping Zones
        $zones = [
            'North America' => ['US', 'CA', 'MX'],
            'Europe' => ['GB', 'DE', 'FR', 'IT', 'ES'],
            'Middle East' => ['AE', 'SA', 'QA'],
            'Asia Pacific' => ['AU', 'JP', 'SG'],
        ];

        $createdZones = [];
        foreach ($zones as $zoneName => $countries) {
            $zone = ShippingZone::query()->updateOrCreate(
                ['workspace_id' => $workspaceId, 'name' => $zoneName],
                ['code' => strtoupper(str_replace(' ', '_', $zoneName)), 'is_active' => true]
            );
            $createdZones[$zoneName] = $zone;

            foreach ($countries as $countryCode) {
                ShippingZoneCountry::query()->updateOrCreate(
                    ['workspace_id' => $workspaceId, 'shipping_zone_id' => $zone->id, 'country_code' => $countryCode]
                );
            }
        }

        // Create Shipping Methods
        $methods = [
            'DHL Express Air' => [
                'code' => 'DHL_EXPRESS',
                'carrier' => 'DHL',
                'type' => 'air',
                'description' => 'Premium fast shipping for small to medium orders.',
                'estimated_delivery_min_days' => 3,
                'estimated_delivery_max_days' => 5,
            ],
            'Standard Air Freight' => [
                'code' => 'STD_AIR',
                'carrier' => 'Multiple',
                'type' => 'air',
                'description' => 'Economical air shipping.',
                'estimated_delivery_min_days' => 7,
                'estimated_delivery_max_days' => 14,
            ],
            'Sea Freight (LCL/FCL)' => [
                'code' => 'SEA_FREIGHT',
                'carrier' => 'Multiple',
                'type' => 'sea',
                'description' => 'Best for large wholesale orders (>100kg).',
                'estimated_delivery_min_days' => 30,
                'estimated_delivery_max_days' => 45,
            ],
        ];

        $createdMethods = [];
        foreach ($methods as $methodName => $methodData) {
            $method = ShippingMethod::query()->updateOrCreate(
                ['workspace_id' => $workspaceId, 'name' => $methodName],
                array_merge($methodData, ['is_active' => true])
            );
            $createdMethods[$methodName] = $method;
        }

        // Create Shipping Rates
        // Rates mapping format: Zone -> Method -> Rates (min_kg, max_kg, base_price, price_per_kg)
        $ratesSetup = [
            'North America' => [
                'DHL Express Air' => [
                    [0, 5, 15.00, 10.00],
                    [5, 10, 15.00, 8.00],
                    [10, 50, 15.00, 6.00],
                ],
                'Standard Air Freight' => [
                    [0, 5, 10.00, 8.00],
                    [5, 10, 10.00, 6.00],
                    [10, 50, 10.00, 4.00],
                    [50, 100, 10.00, 3.50],
                ],
                'Sea Freight (LCL/FCL)' => [
                    [100, 500, 100.00, 1.50],
                    [500, 1000, 100.00, 1.20],
                    [1000, null, 100.00, 1.00],
                ],
            ],
            'Europe' => [
                'DHL Express Air' => [
                    [0, 5, 12.00, 9.00],
                    [5, 10, 12.00, 7.50],
                    [10, 50, 12.00, 5.50],
                ],
                'Standard Air Freight' => [
                    [0, 5, 8.00, 7.00],
                    [5, 10, 8.00, 5.50],
                    [10, 50, 8.00, 3.80],
                    [50, 100, 8.00, 3.00],
                ],
                'Sea Freight (LCL/FCL)' => [
                    [100, 500, 90.00, 1.40],
                    [500, 1000, 90.00, 1.10],
                    [1000, null, 90.00, 0.90],
                ],
            ],
            'Middle East' => [
                'DHL Express Air' => [
                    [0, 5, 10.00, 8.00],
                    [5, 10, 10.00, 6.50],
                    [10, 50, 10.00, 4.50],
                ],
                'Standard Air Freight' => [
                    [0, 5, 6.00, 6.00],
                    [5, 10, 6.00, 4.50],
                    [10, 50, 6.00, 3.00],
                    [50, 100, 6.00, 2.50],
                ],
                'Sea Freight (LCL/FCL)' => [
                    [100, 500, 80.00, 1.20],
                    [500, 1000, 80.00, 0.90],
                    [1000, null, 80.00, 0.70],
                ],
            ],
            'Asia Pacific' => [
                'DHL Express Air' => [
                    [0, 5, 8.00, 7.00],
                    [5, 10, 8.00, 5.50],
                    [10, 50, 8.00, 4.00],
                ],
                'Standard Air Freight' => [
                    [0, 5, 5.00, 5.00],
                    [5, 10, 5.00, 4.00],
                    [10, 50, 5.00, 2.80],
                    [50, 100, 5.00, 2.20],
                ],
                'Sea Freight (LCL/FCL)' => [
                    [100, 500, 70.00, 1.10],
                    [500, 1000, 70.00, 0.80],
                    [1000, null, 70.00, 0.60],
                ],
            ],
        ];

        foreach ($ratesSetup as $zoneName => $methodsSetup) {
            $zone = $createdZones[$zoneName] ?? null;
            if (!$zone) continue;

            foreach ($methodsSetup as $methodName => $rates) {
                $method = $createdMethods[$methodName] ?? null;
                if (!$method) continue;

                foreach ($rates as $rateData) {
                    [$minWeight, $maxWeight, $price, $pricePerKg] = $rateData;

                    ShippingRate::query()->updateOrCreate(
                        [
                            'workspace_id' => $workspaceId,
                            'shipping_zone_id' => $zone->id,
                            'shipping_method_id' => $method->id,
                            'min_weight_kg' => $minWeight,
                            'max_weight_kg' => $maxWeight,
                        ],
                        [
                            'price' => $price,
                            'price_per_kg' => $pricePerKg,
                            'currency' => 'USD',
                            'is_active' => true,
                        ]
                    );
                }
            }
        }
    }
}
