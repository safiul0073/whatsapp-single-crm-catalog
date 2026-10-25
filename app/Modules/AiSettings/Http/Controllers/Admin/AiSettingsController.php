<?php

namespace App\Modules\AiSettings\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\AiSettings\Services\AiSettingsService;
use App\Modules\KnowledgeBases\Jobs\ReindexKnowledgeBaseVectorsJob;
use App\Modules\KnowledgeBases\Services\VectorStoreResolver;
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

        $previousProvider = $this->service->get('vector_database_provider');
        $previouslyEnabled = (bool) $this->service->get('vector_database_enabled', false);
        $provider = $settings['vector_database_provider'] ?? $previousProvider;

        if (($settings['vector_database_enabled'] ?? false)
            && $provider === 'qdrant'
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
        $message = __('AI settings updated successfully.');

        // Copy existing embeddings into the newly selected store so search keeps working.
        if (array_key_exists('vector_database_provider', $settings)
            && (bool) $this->service->get('vector_database_enabled', false)
            && ($previousProvider !== $provider || ! $previouslyEnabled)
        ) {
            ReindexKnowledgeBaseVectorsJob::dispatch();
            $message .= ' '.__('Knowledge bases are being re-indexed into the :provider vector store in the background.', [
                'provider' => app(VectorStoreResolver::class)->current()->providerLabel(),
            ]);
        }

        return redirect()->to($tab === 'vector-database' ? route('admin.ai-settings.vector-database.index') : route('admin.ai-settings.index').'#'.$tab)
            ->with('success', $message);
    }

    public function testVectorDatabase(Request $request, VectorStoreResolver $resolver): RedirectResponse
    {
        $settings = (array) $request->input('settings', []);
        $provider = $settings['vector_database_provider'] ?? $resolver->currentProvider();
        $result = $resolver->for($provider)->testConnection($settings);

        return redirect()->route('admin.ai-settings.vector-database.index')
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
