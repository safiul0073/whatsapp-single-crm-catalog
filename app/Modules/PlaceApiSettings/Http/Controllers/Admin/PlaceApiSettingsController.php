<?php

namespace App\Modules\PlaceApiSettings\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\PlaceApiSettings\Services\PlaceApiSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class PlaceApiSettingsController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:place-api-settings.view', only: ['index']),
            new Middleware('permission:place-api-settings.edit', only: ['update']),
        ];
    }

    public function __construct(
        protected PlaceApiSettingsService $settings
    ) {}

    public function index(): View
    {
        return view('place-api-settings::admin.index', [
            'groups' => $this->settings->getGroupedDefinitions(),
            'status' => $this->settings->status(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $settings = $request->input('settings', []);
        $rules = [];
        $attributes = [];

        foreach (config('place-api-settings', []) as $group) {
            foreach ($group['settings'] as $key => $definition) {
                if (isset($definition['rules'])) {
                    $rules["settings.{$key}"] = $definition['rules'];
                    $attributes["settings.{$key}"] = $definition['label'];
                }
            }
        }

        $request->validate($rules, [], $attributes);

        if (($settings['google_places_enabled'] ?? false)
            && blank($settings['google_places_api_key'] ?? null)
            && blank($this->settings->get('google_places_api_key'))
        ) {
            return back()
                ->withErrors(['settings.google_places_api_key' => 'Google Places API key is required when Place API lead source is enabled.'])
                ->withInput();
        }

        foreach ($settings as $key => $value) {
            $this->settings->set($key, $value);
        }

        return redirect()
            ->route('admin.place-api-settings.index')
            ->with('success', __('Place API settings updated successfully.'));
    }
}
