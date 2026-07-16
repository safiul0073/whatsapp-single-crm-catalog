<?php

namespace App\Modules\KnowledgeBases\Services;

use App\Modules\AiSettings\Services\AiSettingsService;
use App\Modules\KnowledgeBases\Contracts\VectorStoreService;
use App\Modules\KnowledgeBases\Models\KnowledgeBaseChunk;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class QdrantVectorStoreService implements VectorStoreService
{
    public function __construct(protected AiSettingsService $settings) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('vector_database_enabled', false)
            && $this->settings->get('vector_database_provider', 'qdrant') === 'qdrant'
            && filled($this->url());
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function testConnection(array $settings = []): array
    {
        $mode = (string) ($settings['vector_database_mode'] ?? $this->settings->get('vector_database_mode', 'local'));
        $url = rtrim((string) ($settings['qdrant_url'] ?? $this->url()), '/');
        $apiKey = filled($settings['qdrant_api_key'] ?? null)
            ? (string) $settings['qdrant_api_key']
            : (string) $this->settings->get('qdrant_api_key', '');

        if ($mode === 'cloud' && blank($apiKey)) {
            return ['ok' => false, 'message' => 'Qdrant Cloud mode requires an API key.'];
        }

        if (blank($url)) {
            return ['ok' => false, 'message' => 'Add a Qdrant URL before testing the connection.'];
        }

        try {
            $response = $this->client($url, $apiKey)->get('/collections');

            if ($response->successful()) {
                return ['ok' => true, 'message' => 'Qdrant connection successful.'];
            }

            return ['ok' => false, 'message' => 'Qdrant returned HTTP '.$response->status().'.'];
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => 'Qdrant connection failed: '.$exception->getMessage()];
        }
    }

    /**
     * @param  Collection<int, KnowledgeBaseChunk>  $chunks
     */
    public function upsert(Collection $chunks): bool
    {
        if (! $this->enabled() || $chunks->isEmpty()) {
            return false;
        }

        $points = $chunks
            ->filter(fn (KnowledgeBaseChunk $chunk): bool => is_array($chunk->embedding) && $chunk->embedding !== [])
            ->map(function (KnowledgeBaseChunk $chunk): array {
                $vectorId = $chunk->vector_id ?: (string) Str::uuid();
                $chunk->forceFill(['vector_id' => $vectorId])->save();

                return [
                    'id' => $vectorId,
                    'vector' => $chunk->embedding,
                    'payload' => [
                        'workspace_id' => $chunk->knowledgeBase?->workspace_id,
                        'knowledge_base_id' => $chunk->knowledge_base_id,
                        'source_id' => $chunk->source_id,
                        'chunk_id' => $chunk->id,
                        'source_title' => $chunk->source?->title,
                        'source_type' => $chunk->source?->type,
                    ],
                ];
            })
            ->values();

        if ($points->isEmpty()) {
            return false;
        }

        try {
            $this->ensureCollection(count($points->first()['vector']));

            return $this->client()
                ->put('/collections/'.$this->collection().'/points?wait=true', ['points' => $points->all()])
                ->successful();
        } catch (Throwable) {
            return false;
        }
    }

    public function deleteForSource(int $sourceId): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            $this->client()->post('/collections/'.$this->collection().'/points/delete?wait=true', [
                'filter' => [
                    'must' => [
                        ['key' => 'source_id', 'match' => ['value' => $sourceId]],
                    ],
                ],
            ]);
        } catch (Throwable) {
        }
    }

    /**
     * @param  array<int, int>  $knowledgeBaseIds
     * @param  array<float>  $embedding
     * @return Collection<int, KnowledgeBaseChunk>
     */
    public function search(array $knowledgeBaseIds, array $embedding, int $limit): Collection
    {
        if (! $this->enabled() || $knowledgeBaseIds === [] || $embedding === []) {
            return collect();
        }

        try {
            $response = $this->client()->post('/collections/'.$this->collection().'/points/search', [
                'vector' => $embedding,
                'limit' => $limit,
                'score_threshold' => (float) $this->settings->get('qdrant_score_threshold', 0.2),
                'with_payload' => true,
                'filter' => [
                    'must' => [
                        ['key' => 'knowledge_base_id', 'match' => ['any' => $knowledgeBaseIds]],
                    ],
                ],
            ]);

            if (! $response->successful()) {
                return collect();
            }

            $matches = collect($response->json('result', []));
            $chunkIds = $matches->pluck('payload.chunk_id')->filter()->all();
            $scores = $matches->mapWithKeys(fn (array $match): array => [(int) data_get($match, 'payload.chunk_id') => (float) data_get($match, 'score', 0)]);

            return KnowledgeBaseChunk::query()
                ->with(['knowledgeBase', 'source'])
                ->whereIn('id', $chunkIds)
                ->get()
                ->each(fn (KnowledgeBaseChunk $chunk) => $chunk->setAttribute('score', $scores[$chunk->id] ?? null))
                ->sortByDesc(fn (KnowledgeBaseChunk $chunk): float => (float) $chunk->getAttribute('score'))
                ->values();
        } catch (Throwable) {
            return collect();
        }
    }

    protected function ensureCollection(int $dimension): void
    {
        $response = $this->client()->get('/collections/'.$this->collection());

        if ($response->successful()) {
            return;
        }

        $this->client()->put('/collections/'.$this->collection(), [
            'vectors' => [
                'size' => $dimension ?: (int) $this->settings->get('qdrant_vector_dimension', 1536),
                'distance' => 'Cosine',
            ],
        ]);
    }

    protected function client(?string $url = null, ?string $apiKey = null): PendingRequest
    {
        $apiKey ??= (string) $this->settings->get('qdrant_api_key', '');

        $request = Http::baseUrl($url ?: $this->url())
            ->timeout((int) $this->settings->get('qdrant_timeout', 10))
            ->acceptJson();

        if (filled($apiKey)) {
            $request = $request->withHeaders(['api-key' => $apiKey]);
        }

        return $request;
    }

    protected function url(): string
    {
        return rtrim((string) $this->settings->get('qdrant_url', 'http://localhost:6333'), '/');
    }

    protected function collection(): string
    {
        return (string) $this->settings->get('qdrant_collection', 'knowledge_base_chunks');
    }
}
