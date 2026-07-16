<?php

namespace App\Modules\Settings\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class SettingController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:settings.view', only: ['index']),
            new Middleware('permission:settings.edit', only: ['update']),
        ];
    }

    public function __construct(
        protected SettingsService $settingsService
    ) {}

    public function index(): View
    {
        $groups = $this->settingsService->getGroupedDefinitions();

        return view('settings::admin.index', compact('groups'));
    }

    public function update(Request $request): RedirectResponse
    {
        $settings = $request->input('settings', []);
        $rules = [];
        $attributes = [];

        foreach (config('settings', []) as $group) {
            foreach ($group['settings'] as $key => $definition) {
                if (! empty($definition['readonly']) || ($definition['type'] ?? null) === 'readonly_url') {
                    continue;
                }

                if (isset($definition['rules'])) {
                    $rules["settings.{$key}"] = $definition['rules'];
                    $attributes["settings.{$key}"] = $definition['label'];
                }
            }
        }

        $request->validate($rules, [], $attributes);

        foreach ($settings as $key => $value) {
            $definition = $this->settingsService->getDefinition($key);

            if (! $definition || ! empty($definition['readonly']) || ($definition['type'] ?? null) === 'readonly_url') {
                continue;
            }

            $this->settingsService->set($key, $value);
        }

        $tab = $request->input('_active_tab', array_key_first(config('settings', [])));

        return redirect()->to(route('admin.settings.index').'#'.$tab)
            ->with('success', __('Settings updated successfully.'));
    }
}
