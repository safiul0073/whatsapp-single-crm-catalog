<?php

namespace App\Modules\Settings\Database\Seeders;

use App\Modules\Settings\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('settings', []) as $group) {
            foreach ($group['settings'] as $key => $definition) {
                $default = $definition['default'] ?? null;

                $stored = match ($definition['type'] ?? 'text') {
                    'boolean' => $default ? '1' : '0',
                    default => $default !== null ? (string) $default : null,
                };

                Setting::firstOrCreate(
                    ['key' => $key],
                    ['value' => $stored]
                );
            }
        }
    }
}
