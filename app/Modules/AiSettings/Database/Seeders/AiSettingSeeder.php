<?php

namespace App\Modules\AiSettings\Database\Seeders;

use App\Modules\AiSettings\Models\AiSetting;
use Illuminate\Database\Seeder;

class AiSettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('ai-settings', []) as $group) {
            foreach ($group['settings'] as $key => $definition) {
                $default = $definition['default'] ?? null;

                $stored = match ($definition['type'] ?? 'text') {
                    'boolean', 'feature' => $default ? '1' : '0',
                    default => $default !== null ? (string) $default : null,
                };

                AiSetting::firstOrCreate(
                    ['key' => $key],
                    ['value' => $stored]
                );
            }
        }
    }
}
