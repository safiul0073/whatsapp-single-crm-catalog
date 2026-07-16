<?php

use App\Models\Admin;
use App\Modules\AiSettings\Models\AiSetting;
use App\Modules\AiSettings\Services\AiSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('saves qdrant settings and keeps the api key encrypted', function (): void {
    $admin = Admin::factory()->create();

    $this->withoutMiddleware()
        ->actingAs($admin, 'admin')
        ->put(route('admin.ai-settings.update'), [
            '_active_tab' => 'vector-database',
            'settings' => [
                'vector_database_enabled' => '1',
                'vector_database_mode' => 'cloud',
                'vector_database_provider' => 'qdrant',
                'qdrant_url' => 'https://cluster.qdrant.io',
                'qdrant_api_key' => 'qdrant-secret',
                'qdrant_collection' => 'workspace_chunks',
                'qdrant_vector_dimension' => '1536',
                'qdrant_search_limit' => '5',
                'qdrant_score_threshold' => '0.2',
                'qdrant_timeout' => '10',
            ],
        ])
        ->assertRedirect(route('admin.ai-settings.vector-database.index'));

    $stored = AiSetting::query()->where('key', 'qdrant_api_key')->value('value');

    expect($stored)->not->toBe('qdrant-secret')
        ->and(Crypt::decryptString($stored))->toBe('qdrant-secret')
        ->and(app(AiSettingsService::class)->get('qdrant_api_key'))->toBe('qdrant-secret');

    $groups = app(AiSettingsService::class)->getGroupedDefinitions();

    expect($groups['vector-database']['settings']['qdrant_api_key']['value'])->toBe('')
        ->and($groups['vector-database']['settings']['qdrant_api_key']['has_value'])->toBeTrue();
});

it('allows local qdrant mode without an api key but requires cloud credentials', function (): void {
    $admin = Admin::factory()->create();

    $this->withoutMiddleware()
        ->actingAs($admin, 'admin')
        ->put(route('admin.ai-settings.update'), [
            '_active_tab' => 'vector-database',
            'settings' => [
                'vector_database_enabled' => '1',
                'vector_database_mode' => 'local',
                'vector_database_provider' => 'qdrant',
                'qdrant_url' => 'http://localhost:6333',
                'qdrant_collection' => 'knowledge_base_chunks',
            ],
        ])
        ->assertRedirect(route('admin.ai-settings.vector-database.index'));

    $this->withoutMiddleware()
        ->actingAs($admin, 'admin')
        ->from(route('admin.ai-settings.index'))
        ->put(route('admin.ai-settings.update'), [
            '_active_tab' => 'vector-database',
            'settings' => [
                'vector_database_enabled' => '1',
                'vector_database_mode' => 'cloud',
                'vector_database_provider' => 'qdrant',
                'qdrant_url' => 'https://cluster.qdrant.io',
                'qdrant_collection' => 'knowledge_base_chunks',
            ],
        ])
        ->assertRedirect(route('admin.ai-settings.index'))
        ->assertSessionHasErrors('settings.qdrant_api_key');
});

it('tests qdrant connection from admin settings', function (): void {
    Http::fake([
        'http://localhost:6333/collections' => Http::response(['result' => ['collections' => []]], 200),
    ]);

    $admin = Admin::factory()->create();

    $this->withoutMiddleware()
        ->actingAs($admin, 'admin')
        ->put(route('admin.ai-settings.vector-database.test'), [
            'settings' => [
                'vector_database_mode' => 'local',
                'qdrant_url' => 'http://localhost:6333',
            ],
        ])
        ->assertRedirect(route('admin.ai-settings.vector-database.index'))
        ->assertSessionHas('success', 'Qdrant connection successful.');
});
