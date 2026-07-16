<?php

namespace App\Modules\Frontend\Services;

class MenuSlotRegistry
{
    public function all(): array
    {
        return config('frontend-menus', []);
    }

    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    public function options(): array
    {
        $options = [];

        foreach ($this->all() as $key => $slot) {
            $options[$key] = $slot['label'] ?? ucfirst($key);
        }

        return $options;
    }

    public function label(string $key): string
    {
        return $this->options()[$key] ?? ucfirst($key);
    }

    public function assignmentSettingKey(string $themeKey, string $slotKey): string
    {
        return "theme.{$themeKey}.menu.{$slotKey}";
    }
}
