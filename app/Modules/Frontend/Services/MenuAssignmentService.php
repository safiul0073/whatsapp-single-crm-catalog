<?php

namespace App\Modules\Frontend\Services;

use App\Modules\Frontend\Models\FrontendMenu;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MenuAssignmentService
{
    public function __construct(
        protected ThemeRegistry $themes,
        protected ThemeSettingsService $settings,
        protected MenuSlotRegistry $slots,
        protected MenuTreeService $trees
    ) {}

    public function usageForMenu(FrontendMenu $menu): array
    {
        $usage = [];

        foreach ($this->themes->all() as $themeKey => $theme) {
            foreach ($this->slots->all() as $slotKey => $slot) {
                $assignedMenuId = $this->settings->getThemeSetting($themeKey, "menu.{$slotKey}");

                if ((int) $assignedMenuId !== (int) $menu->id) {
                    continue;
                }

                $usage[] = [
                    'theme_key' => $themeKey,
                    'theme_label' => $theme['label'] ?? ucfirst($themeKey),
                    'slot_key' => $slotKey,
                    'slot_label' => $slot['label'] ?? ucfirst($slotKey),
                ];
            }
        }

        return $usage;
    }

    public function usageMap(Collection $menus): array
    {
        $usage = [];

        foreach ($menus as $menu) {
            $usage[$menu->id] = $this->usageForMenu($menu);
        }

        return $usage;
    }

    public function ensureNotAssigned(FrontendMenu $menu): void
    {
        $usage = $this->usageForMenu($menu);

        if ($usage === []) {
            return;
        }

        $labels = collect($usage)
            ->map(fn (array $item) => "{$item['theme_label']} / {$item['slot_label']}")
            ->implode(', ');

        throw ValidationException::withMessages([
            'menu' => __('This menu is currently assigned to: :labels. Unassign it first.', ['labels' => $labels]),
        ]);
    }

    public function validateForSlot(string $slotKey, FrontendMenu $menu, ?string $errorKey = null): void
    {
        $slot = $this->slots->get($slotKey);

        if (! $slot) {
            return;
        }

        $levels = $this->trees->maxLevels($this->trees->serializeForEditor($menu));

        if ($levels > (int) ($slot['max_depth'] ?? 1)) {
            throw ValidationException::withMessages([
                ($errorKey ?: "settings.theme.menu.{$slotKey}") => __('The selected menu exceeds the depth allowed for the :slot slot.', [
                    'slot' => $slot['label'] ?? ucfirst($slotKey),
                ]),
            ]);
        }
    }
}
