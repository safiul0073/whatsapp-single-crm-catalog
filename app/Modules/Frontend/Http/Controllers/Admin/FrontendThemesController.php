<?php

namespace App\Modules\Frontend\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Frontend\Models\FrontendMenu;
use App\Modules\Frontend\Services\MenuAssignmentService;
use App\Modules\Frontend\Services\MenuRenderService;
use App\Modules\Frontend\Services\MenuService;
use App\Modules\Frontend\Services\MenuSlotRegistry;
use App\Modules\Frontend\Services\ThemeRegistry;
use App\Modules\Frontend\Services\ThemeSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FrontendThemesController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:frontend-themes.view', only: ['index']),
            new Middleware('permission:frontend-themes.edit', only: ['update']),
        ];
    }

    public function __construct(
        protected ThemeRegistry $themes,
        protected ThemeSettingsService $settings,
        protected MenuSlotRegistry $menuSlots,
        protected MenuService $menus,
        protected MenuAssignmentService $menuAssignments,
        protected MenuRenderService $menuRender
    ) {}

    public function index(): View
    {
        $groups = [
            'global' => [
                'label' => 'Theme Activation',
                'icon' => 'ph ph-swatch-book',
                'description' => 'Choose the active public theme and manage installed theme availability.',
                'settings' => [
                    'active_theme' => [
                        'type' => 'select',
                        'label' => 'Active Theme',
                        'options' => $this->themes->options(),
                        'value' => $this->settings->activeTheme(),
                    ],
                ],
            ],
        ];

        foreach ($this->themes->all() as $themeKey => $theme) {
            $themeSettings = [
                "theme.{$themeKey}.enabled" => [
                    'type' => 'feature',
                    'label' => 'Enable Theme',
                    'hint' => 'Only enabled themes can be activated.',
                    'value' => $this->settings->isEnabled($themeKey),
                ],
            ];

            foreach ($this->settings->getThemeSettingsPayload($themeKey) as $themeGroup) {
                foreach ($themeGroup['settings'] as $settingKey => $definition) {
                    $themeSettings["theme.{$themeKey}.{$settingKey}"] = $definition;
                }
            }

            foreach ($this->menuSlotSettings($themeKey) as $settingKey => $definition) {
                $themeSettings["theme.{$themeKey}.{$settingKey}"] = $definition;
            }

            $groups[$themeKey] = [
                'label' => $theme['label'],
                'icon' => 'ph ph-paint-brush',
                'description' => $theme['description'],
                'settings' => $themeSettings,
                'theme' => $theme,
            ];
        }

        return view('frontend::admin.frontend-themes.index', compact('groups'));
    }

    public function update(Request $request): RedirectResponse
    {
        $values = $request->input('settings', []);
        $rules = [
            'settings.active_theme' => 'required|string',
        ];

        foreach ($this->themes->all() as $themeKey => $theme) {
            $rules["settings.theme.{$themeKey}.enabled"] = 'nullable|boolean';

            foreach ($this->themeSettingDefinitions($themeKey) as $settingKey => $definition) {
                if (! empty($definition['rules'])) {
                    $rules["settings.theme.{$themeKey}.{$settingKey}"] = $definition['rules'];
                }
            }
        }

        $validated = $request->validate($rules);
        $activeTheme = $validated['settings']['active_theme'];
        $submittedValues = $request->input('settings', []);
        $activeEnabled = (bool) data_get($submittedValues, "theme.{$activeTheme}.enabled", $this->settings->isEnabled($activeTheme));

        if (! $activeEnabled) {
            return back()
                ->withInput()
                ->withErrors(['settings.active_theme' => __('Only enabled themes can be selected as active.')]);
        }

        $this->settings->set('active_theme', $validated['settings']['active_theme']);

        foreach ($this->themes->all() as $themeKey => $theme) {
            $themeInput = data_get($values, "theme.{$themeKey}", []);

            $this->settings->set("theme.{$themeKey}.enabled", (bool) data_get($themeInput, 'enabled', false));

            foreach ($this->themeSettingDefinitions($themeKey) as $settingKey => $definition) {
                $rawValue = data_get($themeInput, $settingKey);
                $type = $definition['type'] ?? 'text';

                if (in_array($type, ['feature', 'boolean'], true)) {
                    $rawValue = (bool) $rawValue;
                }

                if ($type === 'checkbox' || $type === 'tags') {
                    $rawValue = is_array($rawValue) ? $rawValue : [];
                }

                if ($rawValue === '' || $rawValue === []) {
                    $rawValue = null;
                }

                if (str_starts_with($settingKey, 'menu.') && $rawValue) {
                    $slotKey = (string) str($settingKey)->after('menu.');
                    $menu = FrontendMenu::query()->published()->find($rawValue);

                    if (! $menu) {
                        continue;
                    }

                    $this->menuAssignments->validateForSlot($slotKey, $menu, "settings.theme.{$themeKey}.menu.{$slotKey}");
                }

                $this->settings->set("theme.{$themeKey}.{$settingKey}", $rawValue);
            }
        }

        $this->menuRender->clearCache();

        $tab = $request->input('_active_tab', 'global');

        return redirect()
            ->to(route('admin.frontend-themes.index').'#'.$tab)
            ->with('success', __('Frontend theme settings updated successfully.'));
    }

    protected function themeSettingDefinitions(string $themeKey): array
    {
        $theme = $this->themes->get($themeKey);
        $definitions = [];

        foreach (($theme['theme_settings_schema'] ?? []) as $group) {
            foreach (($group['settings'] ?? []) as $settingKey => $definition) {
                $definitions[$settingKey] = $definition;
            }
        }

        foreach ($this->menuSlotSettings($themeKey) as $settingKey => $definition) {
            $definitions[$settingKey] = $definition;
        }

        return $definitions;
    }

    protected function menuSlotSettings(string $themeKey): array
    {
        $options = $this->menus->publishedOptions();
        $settings = [];

        foreach ($this->menuSlots->all() as $slotKey => $slot) {
            $settings["menu.{$slotKey}"] = [
                'type' => 'select',
                'label' => $slot['label'] ?? ucfirst($slotKey),
                'hint' => $slot['description'] ?? null,
                'options' => $options,
                'value' => $this->settings->getThemeSetting($themeKey, "menu.{$slotKey}"),
                'rules' => [
                    'nullable',
                    Rule::exists('frontend_menus', 'id')->where(fn ($query) => $query->where('status', 'published')),
                ],
            ];
        }

        return $settings;
    }
}
