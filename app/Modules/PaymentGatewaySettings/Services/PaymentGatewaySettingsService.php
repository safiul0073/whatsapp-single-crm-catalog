<?php

namespace App\Modules\PaymentGatewaySettings\Services;

use App\Modules\Currencies\Models\Currency;
use App\Modules\PaymentGatewaySettings\Models\PaymentGatewaySetting;
use Illuminate\Support\Facades\Cache;

class PaymentGatewaySettingsService
{
    protected string $configKey = 'payment-gateway-settings';

    protected string $cacheKey = 'payment_gateway_settings_cache';

    protected int $cacheTtl = 86400;

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

        PaymentGatewaySetting::updateOrCreate(['key' => $key], ['value' => $stored]);

        $this->clearCache();
    }

    /**
     * Get all groups with settings and current values merged in (for the settings view).
     */
    public function getGroupedDefinitions(): array
    {
        $dbValues = $this->getAllFromDb();
        $groups = config($this->configKey, []);
        $result = [];

        foreach ($groups as $groupKey => $group) {
            $settings = [];

            foreach ($group['settings'] as $key => $definition) {
                if ($key === 'payment_currency') {
                    $definition['options'] = Currency::getActiveForSelect();
                }

                $rawValue = $dbValues[$key] ?? null;

                $settings[$key] = array_merge($definition, [
                    'key' => $key,
                    'value' => $rawValue !== null
                        ? $this->castValue($rawValue, $definition['type'] ?? 'text')
                        : ($definition['default'] ?? null),
                ]);
            }

            $result[$groupKey] = [
                'label' => $group['label'] ?? ucfirst($groupKey),
                'icon' => $group['icon'] ?? 'ph ph-credit-card',
                'description' => $group['description'] ?? '',
                'layout' => $group['layout'] ?? '',
                'webhook_url' => $group['webhook_url'] ?? false,
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
        foreach (config($this->configKey, []) as $group) {
            if (isset($group['settings'][$key])) {
                return $group['settings'][$key];
            }
        }

        return null;
    }

    /**
     * Get all DB values as a flat key => value array (public accessor).
     */
    public function getAllValues(): array
    {
        return $this->getAllFromDb();
    }

    /**
     * Get all DB values as a flat key => value array (cached).
     */
    protected function getAllFromDb(): array
    {
        return Cache::remember($this->cacheKey, $this->cacheTtl, function () {
            return PaymentGatewaySetting::pluck('value', 'key')->toArray();
        });
    }

    protected function castValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean', 'feature' => (bool) $value,
            'number', 'float' => (float) $value,
            'integer' => (int) $value,
            'tags' => is_string($value) ? array_filter(explode(',', $value)) : (array) $value,
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
            'tags' => is_array($value) ? implode(',', array_filter($value)) : (string) $value,
            default => is_array($value) ? implode(',', $value) : (string) $value,
        };
    }

    public function clearCache(): void
    {
        Cache::forget($this->cacheKey);
    }
}
