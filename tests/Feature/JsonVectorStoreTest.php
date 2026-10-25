<?php

use App\Models\Admin;
use App\Models\User;
use App\Modules\AiSettings\Services\AiSettingsService;
use App\Modules\KnowledgeBases\Contracts\VectorStoreService;
use App\Modules\KnowledgeBases\Jobs\ReindexKnowledgeBaseVectorsJob;
use App\Modules\KnowledgeBases\Models\KnowledgeBase;
use App\Modules\KnowledgeBases\Models\KnowledgeBaseChunk;
use App\Modules\KnowledgeBases\Models\KnowledgeBaseSource;
use App\Modules\KnowledgeBases\Services\JsonFileVectorStoreService;
use App\Modules\KnowledgeBases\Services\QdrantVectorStoreService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(storage_path('app/knowledge-bases/vectors'));
});

afterEach(function (): void {
    File::deleteDirectory(storage_path('app/knowledge-bases/vectors'));
});

function enableJsonVectorStore(): void
{
    app(AiSettingsService::class)->set('vector_database_enabled', true);
    app(AiSettingsService::class)->set('vector_database_provider', 'json_file');
}

/**
 * @param  array<int, array<int, float>>  $embeddings
 * @return array{0: KnowledgeBase, 1: KnowledgeBaseSource, 2: array<int, KnowledgeBaseChunk>}
 */
function jsonStoreFixture(array $embeddings): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $knowledgeBase = KnowledgeBase::query()->create([
        'workspace_id' => app(WorkspaceResolver::class)->current($user)->id,
        'name' => 'JSON KB',
        'settings' => [],
    ]);
    $source = $knowledgeBase->sources()->create([
        'type' => 'text',
        'title' => 'Doc',
        'content' => 'Doc',
        'status' => 'ready',
        'metadata' => [],
    ]);

    $chunks = [];

    foreach ($embeddings as $position => $embedding) {
        $chunks[] = $source->chunks()->create([
            'knowledge_base_id' => $knowledgeBase->id,
            'content' => 'Chunk '.$position,
            'embedding' => $embedding,
            'token_count' => 2,
            'position' => $position,
            'metadata' => [],
        ]);
    }

    return [$knowledgeBase, $source, $chunks];
}

it('uses the json file store by default and qdrant when selected', function (): void {
    app(AiSettingsService::class)->set('vector_database_enabled', true);

    expect(app(VectorStoreService::class))->toBeInstanceOf(JsonFileVectorStoreService::class);

    app(AiSettingsService::class)->set('vector_database_provider', 'qdrant');

    expect(app(VectorStoreService::class))->toBeInstanceOf(QdrantVectorStoreService::class)
        ->and(app(VectorStoreService::class)->providerLabel())->toBe('Qdrant');
});

it('ranks json store matches by cosine similarity and respects threshold and limit', function (): void {
    enableJsonVectorStore();
    app(AiSettingsService::class)->set('qdrant_score_threshold', 0.5);

    [$knowledgeBase, , $chunks] = jsonStoreFixture([
        [1.0, 0.0, 0.0],
        [0.9, 0.1, 0.0],
        [0.0, 1.0, 0.0],
        [0.7, 0.7, 0.0],
    ]);

    $store = app(JsonFileVectorStoreService::class);

    expect($store->upsert(collect($chunks)))->toBeTrue()
        ->and($store->countForKnowledgeBase($knowledgeBase->id))->toBe(4);

    $results = $store->search([$knowledgeBase->id], [1.0, 0.0, 0.0], 2);

    expect($results->pluck('id')->all())->toBe([$chunks[0]->id, $chunks[1]->id])
        ->and($results->first()->score)->toEqualWithDelta(1.0, 0.0001);

    // The orthogonal chunk is below the threshold even with a large limit.
    expect($store->search([$knowledgeBase->id], [1.0, 0.0, 0.0], 10)->pluck('id'))
        ->not->toContain($chunks[2]->id);
});

it('updates existing json vectors in place and deletes them per source', function (): void {
    enableJsonVectorStore();

    [$knowledgeBase, $source, $chunks] = jsonStoreFixture([[1.0, 0.0], [0.0, 1.0]]);
    $store = app(JsonFileVectorStoreService::class);

    $store->upsert(collect($chunks));
    $store->upsert(collect([$chunks[0]]));

    expect($store->countForKnowledgeBase($knowledgeBase->id))->toBe(2);

    $store->deleteForSource($source->id);

    expect($store->countForKnowledgeBase($knowledgeBase->id))->toBe(0)
        ->and($store->search([$knowledgeBase->id], [1.0, 0.0], 5))->toBeEmpty();
});

it('reindexes stored embeddings into the json store via artisan', function (): void {
    enableJsonVectorStore();

    [$knowledgeBase, $source] = jsonStoreFixture([[1.0, 0.0], [0.5, 0.5]]);

    $this->artisan('knowledge-base:reindex-vectors')->assertSuccessful();

    expect(app(JsonFileVectorStoreService::class)->countForKnowledgeBase($knowledgeBase->id))->toBe(2)
        ->and($source->refresh()->vector_status)->toBe('synced');
});

it('tests the selected provider and skips qdrant cloud validation for json', function (): void {
    Queue::fake();
    $admin = Admin::factory()->create();

    $this->withoutMiddleware()
        ->actingAs($admin, 'admin')
        ->put(route('admin.ai-settings.vector-database.test'), [
            'settings' => ['vector_database_provider' => 'json_file'],
        ])
        ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'JSON file vector store is ready'));

    $this->withoutMiddleware()
        ->actingAs($admin, 'admin')
        ->put(route('admin.ai-settings.update'), [
            '_active_tab' => 'vector-database',
            'settings' => [
                'vector_database_enabled' => '1',
                'vector_database_provider' => 'json_file',
                'vector_database_mode' => 'cloud',
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.ai-settings.vector-database.index'));

    Queue::assertPushed(ReindexKnowledgeBaseVectorsJob::class);
});
