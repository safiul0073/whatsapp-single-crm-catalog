<?php

namespace App\Modules\PlaceApiSettings\Services;

use App\Modules\PlaceApiSettings\Models\PlaceApiSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class PlaceApiSettingsService
{
    protected string $configKey = 'place-api-settings';

    protected string $cacheKey = 'place_api_settings_cache';

    protected int $cacheTtl = 86400;

    public function get(string $key, mixed $default = null): mixed
    {
        $dbValues = $this->getAllFromDb();
        $definition = $this->getDefinition($key);

        if (array_key_exists($key, $dbValues)) {
            return $this->castValue($this->decryptIfNeeded($dbValues[$key], $definition), $definition['type'] ?? 'text');
        }

        return $definition['default'] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $definition = $this->getDefinition($key);
        $type = $definition['type'] ?? 'text';

        if (($definition['encrypted'] ?? false) && blank($value) && PlaceApiSetting::query()->where('key', $key)->exists()) {
            return;
        }

        $stored = $this->formatForStorage($value, $type);

        if (($definition['encrypted'] ?? false) && filled($stored)) {
            $stored = Crypt::encryptString($stored);
        }

        PlaceApiSetting::query()->updateOrCreate(['key' => $key], ['value' => $stored]);

        $this->clearCache();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getGroupedDefinitions(): array
    {
        $dbValues = $this->getAllFromDb();
        $groups = config($this->configKey, []);
        $result = [];

        foreach ($groups as $groupKey => $group) {
            $settings = [];

            foreach ($group['settings'] as $key => $definition) {
                $rawValue = $dbValues[$key] ?? null;
                $isEncrypted = (bool) ($definition['encrypted'] ?? false);

                $settings[$key] = array_merge($definition, [
                    'key' => $key,
                    'value' => $isEncrypted
                        ? ''
                        : ($rawValue !== null
                            ? $this->castValue($this->decryptIfNeeded($rawValue, $definition), $definition['type'] ?? 'text')
                            : ($definition['default'] ?? null)),
                    'has_value' => $isEncrypted && filled($this->decryptIfNeeded($rawValue, $definition)),
                ]);
            }

            $result[$groupKey] = [
                'label' => $group['label'] ?? ucfirst($groupKey),
                'icon' => $group['icon'] ?? 'ph ph-map-pin',
                'description' => $group['description'] ?? '',
                'settings' => $settings,
            ];
        }

        return $result;
    }

    public function getDefinition(string $key): ?array
    {
        foreach (config($this->configKey, []) as $group) {
            if (isset($group['settings'][$key])) {
                return $group['settings'][$key];
            }
        }

        return null;
    }

    public function isConfigured(): bool
    {
        return (bool) $this->get('google_places_enabled', false)
            && filled($this->get('google_places_api_key'));
    }

    /**
     * @return array{enabled: bool, configured: bool, provider: string, language: string|null, region: string|null, result_limit: int}
     */
    public function status(): array
    {
        return [
            'enabled' => (bool) $this->get('google_places_enabled', false),
            'configured' => $this->isConfigured(),
            'provider' => 'google_places',
            'language' => filled($this->get('google_places_language')) ? (string) $this->get('google_places_language') : null,
            'region' => filled($this->get('google_places_region')) ? strtoupper((string) $this->get('google_places_region')) : null,
            'result_limit' => max(1, min(60, (int) $this->get('google_places_result_limit', 25))),
        ];
    }

    public function apiKey(): ?string
    {
        $key = $this->get('google_places_api_key');

        return filled($key) ? (string) $key : null;
    }

    protected function getAllFromDb(): array
    {
        return Cache::remember($this->cacheKey, $this->cacheTtl, function (): array {
            return PlaceApiSetting::query()->pluck('value', 'key')->toArray();
        });
    }

    protected function castValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean', 'feature' => (bool) $value,
            'number', 'integer' => (int) $value,
            'float' => (float) $value,
            default => $value,
        };
    }

    protected function formatForStorage(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean', 'feature' => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    protected function decryptIfNeeded(?string $value, ?array $definition): ?string
    {
        if ($value === null || ! ($definition['encrypted'] ?? false)) {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function clearCache(): void
    {
        Cache::forget($this->cacheKey);
    }
}
