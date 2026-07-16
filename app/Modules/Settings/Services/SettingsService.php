<?php

namespace App\Modules\Settings\Services;

use App\Modules\Settings\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    protected string $cacheKey = 'app_settings';

    protected int $cacheTtl = 86400; // 24 hours

    /**
     * Get a setting value: DB override → config default → fallback.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $dbValues = $this->getAllFromDb();
        $definition = $this->getDefinition($key);

        if (array_key_exists($key, $dbValues)) {
            return $this->castValue($dbValues[$key], $definition['type'] ?? 'text');
        }

        return $definition['default'] ?? $default;
    }

    /**
     * Set a setting value in the database.
     */
    public function set(string $key, mixed $value): void
    {
        $definition = $this->getDefinition($key);
        $type = $definition['type'] ?? 'text';
        $stored = $this->formatForStorage($value, $type);

        Setting::updateOrCreate(['key' => $key], ['value' => $stored]);

        $this->clearCache();
    }

    /**
     * Get all groups with settings and current values merged in (for the settings view).
     */
    public function getGroupedDefinitions(): array
    {
        $dbValues = $this->getAllFromDb();
        $groups = config('settings', []);
        $result = [];

        foreach ($groups as $groupKey => $group) {
            $settings = [];

            foreach ($group['settings'] as $key => $definition) {
                $rawValue = $dbValues[$key] ?? null;
                $value = $this->resolvedDefinitionValue($key, $definition, $rawValue);

                $settings[$key] = array_merge($definition, [
                    'key' => $key,
                    'value' => $value,
                ]);
            }

            $result[$groupKey] = [
                'label' => $group['label'] ?? ucfirst($groupKey),
                'icon' => $group['icon'] ?? 'ph ph-sliders-horizontal',
                'description' => $group['description'] ?? '',
                'card_groups' => $group['card_groups'] ?? false,
                'layout' => $group['layout'] ?? '',
                'settings' => $settings,
            ];
        }

        return $result;
    }

    /**
     * Find a setting's definition from config.
     */
    public function getDefinition(string $key): ?array
    {
        foreach (config('settings', []) as $group) {
            if (isset($group['settings'][$key])) {
                return $group['settings'][$key];
            }
        }

        return null;
    }

    /**
     * Get all public settings as key => value.
     */
    public function getPublicSettings(): array
    {
        $dbValues = $this->getAllFromDb();
        $public = [];

        foreach (config('settings', []) as $group) {
            foreach ($group['settings'] as $key => $definition) {
                if (! empty($definition['public'])) {
                    $rawValue = $dbValues[$key] ?? null;
                    $public[$key] = $rawValue !== null
                        ? $this->castValue($rawValue, $definition['type'] ?? 'text')
                        : ($definition['default'] ?? null);
                }
            }
        }

        return $public;
    }

    /**
     * Get all DB values as a flat key => value array (cached).
     */
    protected function getAllFromDb(): array
    {
        return Cache::remember($this->cacheKey, $this->cacheTtl, function () {
            return Setting::pluck('value', 'key')->toArray();
        });
    }

    protected function resolvedDefinitionValue(string $key, array $definition, mixed $rawValue): mixed
    {
        if (($definition['value_resolver'] ?? null) === 'social_callback_url') {
            $provider = (string) ($definition['provider'] ?? '');

            return $provider !== '' ? url("auth/{$provider}/callback") : '';
        }

        return $rawValue !== null
            ? $this->castValue($rawValue, $definition['type'] ?? 'text')
            : ($definition['default'] ?? null);
    }

    /**
     * Cast a DB string value to the proper PHP type.
     */
    protected function castValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean', 'feature' => (bool) $value,
            'number', 'integer' => (int) $value,
            'float' => (float) $value,
            'checkbox', 'tags' => array_filter(explode(',', $value), fn ($v) => $v !== ''),
            default => $value,
        };
    }

    /**
     * Format a PHP value for DB string storage.
     */
    protected function formatForStorage(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean', 'feature' => $value ? '1' : '0',
            'checkbox', 'tags' => is_array($value) ? implode(',', $value) : (string) $value,
            default => (string) $value,
        };
    }

    public function clearCache(): void
    {
        Cache::forget($this->cacheKey);
    }
}
