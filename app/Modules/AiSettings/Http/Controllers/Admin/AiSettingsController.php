<?php

namespace App\Modules\AiSettings\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\AiSettings\Services\AiSettingsService;
use App\Modules\KnowledgeBases\Services\QdrantVectorStoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class AiSettingsController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:ai-settings.view', only: ['index', 'vectorDatabase']),
            new Middleware('permission:ai-settings.edit', only: ['update', 'testVectorDatabase']),
        ];
    }

    public function __construct(
        protected AiSettingsService $service
    ) {}

    public function index(): View
    {
        $groups = $this->service->getGroupedDefinitions();
        $activeGroup = array_key_first($groups);

        return view('ai-settings::admin.index', compact('groups', 'activeGroup'));
    }

    public function vectorDatabase(): View
    {
        $groups = $this->service->getGroupedDefinitions();
        $activeGroup = 'vector-database';

        return view('ai-settings::admin.index', compact('groups', 'activeGroup'));
    }

    public function update(Request $request): RedirectResponse
    {
        $settings = $request->input('settings', []);
        $rules = [];
        $attributes = [];

        foreach (config('ai-settings', []) as $group) {
            foreach ($group['settings'] as $key => $definition) {
                if (isset($definition['rules'])) {
                    $rules["settings.{$key}"] = $definition['rules'];
                    $attributes["settings.{$key}"] = $definition['label'];
                }
            }
        }

        $request->validate($rules, [], $attributes);

        if (($settings['vector_database_enabled'] ?? false)
            && ($settings['vector_database_mode'] ?? null) === 'cloud'
            && blank($settings['qdrant_api_key'] ?? null)
            && blank($this->service->get('qdrant_api_key'))
        ) {
            return back()
                ->withErrors(['settings.qdrant_api_key' => 'Qdrant Cloud mode requires an API key.'])
                ->withInput();
        }

        foreach ($settings as $key => $value) {
            $this->service->set($key, $value);
        }

        $tab = $request->input('_active_tab', array_key_first(config('ai-settings', [])));

        return redirect()->to($tab === 'vector-database' ? route('admin.ai-settings.vector-database.index') : route('admin.ai-settings.index').'#'.$tab)
            ->with('success', __('AI settings updated successfully.'));
    }

    public function testVectorDatabase(Request $request, QdrantVectorStoreService $qdrant): RedirectResponse
    {
        $result = $qdrant->testConnection($request->input('settings', []));

        return redirect()->route('admin.ai-settings.vector-database.index')
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
