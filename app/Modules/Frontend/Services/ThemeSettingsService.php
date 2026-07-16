<?php

namespace App\Modules\Frontend\Services;

use App\Modules\Frontend\Models\FrontendThemeSetting;
use Illuminate\Support\Facades\Cache;

class ThemeSettingsService
{
    protected string $cacheKey = 'frontend_theme_settings_cache';

    protected int $cacheTtl = 86400;

    public function __construct(
        protected ThemeRegistry $themes
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $values = $this->getAll();

        return $values[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $stored = is_array($value) ? json_encode($value) : (string) $value;

        FrontendThemeSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $stored]
        );

        $this->clearCache();
    }

    public function getAll(): array
    {
        return Cache::remember($this->cacheKey, $this->cacheTtl, function () {
            return FrontendThemeSetting::pluck('value', 'key')->toArray();
        });
    }

    public function isEnabled(string $themeKey): bool
    {
        $stored = $this->get("theme.{$themeKey}.enabled");

        if ($stored === null) {
            $stored = $this->get($this->legacyThemePath($themeKey, 'enabled'));
        }

        if ($stored === null) {
            return (bool) ($this->themes->get($themeKey)['default_enabled'] ?? false);
        }

        return (bool) $stored;
    }

    public function activeTheme(): string
    {
        $configured = (string) $this->get('active_theme', $this->themes->defaultThemeKey());

        if ($configured && $this->themes->get($configured) && $this->isEnabled($configured)) {
            return $configured;
        }

        foreach (array_keys($this->themes->all()) as $themeKey) {
            if ($this->isEnabled($themeKey)) {
                return $themeKey;
            }
        }

        return $this->themes->defaultThemeKey();
    }

    public function getThemeSetting(string $themeKey, string $settingKey, mixed $default = null): mixed
    {
        $theme = $this->themes->get($themeKey);
        $path = "theme.{$themeKey}.{$settingKey}";
        $value = $this->get($path);

        if ($value === null) {
            $value = $this->get($this->legacyThemePath($themeKey, $settingKey));
        }

        if ($value === null) {
            foreach (($theme['theme_settings_schema'] ?? []) as $group) {
                if (isset($group['settings'][$settingKey])) {
                    return $group['settings'][$settingKey]['default'] ?? $default;
                }
            }

            return $default;
        }

        return $this->castThemeValue($themeKey, $settingKey, $value);
    }

    public function getThemeSettingsPayload(string $themeKey): array
    {
        $theme = $this->themes->get($themeKey);
        $groups = [];

        foreach (($theme['theme_settings_schema'] ?? []) as $groupKey => $group) {
            $settings = [];

            foreach ($group['settings'] as $settingKey => $definition) {
                $settings[$settingKey] = array_merge($definition, [
                    'key' => $settingKey,
                    'value' => $this->getThemeSetting($themeKey, $settingKey, $definition['default'] ?? null),
                ]);
            }

            $groups[$groupKey] = [
                'label' => $group['label'] ?? ucfirst($groupKey),
                'icon' => $group['icon'] ?? 'ph ph-palette',
                'description' => $group['description'] ?? '',
                'settings' => $settings,
            ];
        }

        return $groups;
    }

    protected function castThemeValue(string $themeKey, string $settingKey, mixed $value): mixed
    {
        $theme = $this->themes->get($themeKey);

        foreach (($theme['theme_settings_schema'] ?? []) as $group) {
            if (! isset($group['settings'][$settingKey])) {
                continue;
            }

            $type = $group['settings'][$settingKey]['type'] ?? 'text';

            return match ($type) {
                'boolean', 'feature' => (bool) $value,
                'number' => is_numeric($value) ? (int) $value : $value,
                'checkbox', 'tags' => is_string($value) ? array_filter(explode(',', $value), fn ($item) => $item !== '') : (array) $value,
                default => $value,
            };
        }

        return $value;
    }

    protected function legacyThemePath(string $themeKey, string $settingKey): string
    {
        return $themeKey === 'classic' ? "theme.softivus.{$settingKey}" : "theme.{$themeKey}.{$settingKey}";
    }

    public function clearCache(): void
    {
        Cache::forget($this->cacheKey);
    }
}
