<?php

namespace App\Modules\PaymentGatewaySettings\Database\Seeders;

use App\Modules\PaymentGatewaySettings\Models\PaymentGatewaySetting;
use Illuminate\Database\Seeder;

class PaymentGatewaySettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('payment-gateway-settings', []) as $group) {
            foreach ($group['settings'] as $key => $definition) {
                $default = $definition['default'] ?? null;

                $stored = match ($definition['type'] ?? 'text') {
                    'boolean', 'feature' => $default ? '1' : '0',
                    default => $default !== null ? (string) $default : null,
                };

                PaymentGatewaySetting::firstOrCreate(
                    ['key' => $key],
                    ['value' => $stored]
                );
            }
        }
    }
}
