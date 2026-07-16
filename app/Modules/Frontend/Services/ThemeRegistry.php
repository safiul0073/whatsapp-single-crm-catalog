<?php

namespace App\Modules\Frontend\Services;

class ThemeRegistry
{
    public function all(): array
    {
        return config('frontend-themes', []);
    }

    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    public function options(): array
    {
        $options = [];

        foreach ($this->all() as $key => $theme) {
            $options[$key] = $theme['label'] ?? ucfirst($key);
        }

        return $options;
    }

    public function label(string $key): string
    {
        return $this->options()[$key] ?? ucfirst($key);
    }

    public function layoutOptions(): array
    {
        $options = [];

        foreach ($this->all() as $theme) {
            foreach ($theme['page_layouts'] ?? [] as $layoutKey => $layout) {
                $options[$layoutKey] = $layout['label'] ?? ucfirst($layoutKey);
            }
        }

        return $options;
    }

    public function defaultThemeKey(): string
    {
        $themes = $this->all();
        $first = array_key_first($themes);

        return $first ?: 'classic';
    }

    public function defaultLayoutKey(string $themeKey): string
    {
        $theme = $this->get($themeKey);
        $layouts = $theme['page_layouts'] ?? [];

        foreach ($layouts as $key => $layout) {
            if (! empty($layout['is_default'])) {
                return $key;
            }
        }

        return array_key_first($layouts) ?: 'default';
    }

    public function supportsSection(string $themeKey, string $sectionType): bool
    {
        $theme = $this->get($themeKey);

        if (! $theme) {
            return false;
        }

        return in_array($sectionType, $theme['supported_section_types'] ?? [], true);
    }
}
