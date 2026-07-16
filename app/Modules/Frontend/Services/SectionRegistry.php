<?php

namespace App\Modules\Frontend\Services;

class SectionRegistry
{
    public function all(): array
    {
        return config('frontend-sections', []);
    }

    public function get(string $type): ?array
    {
        return $this->all()[$type] ?? null;
    }

    public function options(): array
    {
        $options = [];

        foreach ($this->all() as $type => $definition) {
            $options[$type] = $definition['label'] ?? ucfirst(str_replace('_', ' ', $type));
        }

        return $options;
    }

    public function fields(string $type): array
    {
        return $this->get($type)['fields'] ?? [];
    }

    public function supportsTheme(string $type, string $themeKey): bool
    {
        $definition = $this->get($type);

        if (! $definition) {
            return false;
        }

        $supportedThemes = $definition['supported_themes'] ?? [];

        return in_array($themeKey, $supportedThemes, true);
    }

    public function defaults(string $type): array
    {
        $fields = $this->fields($type);
        $defaults = [];

        foreach ($fields as $key => $field) {
            $defaults[$key] = $field['default'] ?? null;
        }

        return $defaults;
    }
}
