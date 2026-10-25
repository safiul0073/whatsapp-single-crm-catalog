<?php

namespace App\Modules\KnowledgeBases\Services;

use App\Modules\KnowledgeBases\Models\KnowledgeBaseChunk;
use App\Modules\KnowledgeBases\Models\KnowledgeBaseSource;

/**
 * Copies already-embedded chunks into the active vector store.
 *
 * Uses the embeddings saved in the database, so no embedding API calls are made.
 */
class KnowledgeBaseVectorReindexer
{
    public function __construct(protected VectorStoreResolver $resolver) {}

    /**
     * @return array{provider: string, sources: int, chunks: int, failed: int}
     */
    public function reindex(?int $knowledgeBaseId = null): array
    {
        $store = $this->resolver->current();
        $summary = ['provider' => $store->providerLabel(), 'sources' => 0, 'chunks' => 0, 'failed' => 0];

        if (! $store->enabled()) {
            return $summary;
        }

        $sources = KnowledgeBaseSource::query()
            ->when($knowledgeBaseId, fn ($query) => $query->where('knowledge_base_id', $knowledgeBaseId))
            ->where('status', 'ready');

        foreach ($sources->lazyById(100) as $source) {
            $ok = true;
            $synced = 0;

            KnowledgeBaseChunk::query()
                ->with(['knowledgeBase', 'source'])
                ->where('source_id', $source->id)
                ->whereNotNull('embedding')
                ->chunkById(200, function ($chunks) use ($store, &$ok, &$synced): void {
                    if ($store->upsert($chunks)) {
                        $synced += $chunks->count();
                    } else {
                        $ok = false;
                    }
                });

            $summary['sources']++;
            $summary['chunks'] += $synced;

            if (! $ok) {
                $summary['failed']++;
            }

            $source->forceFill([
                'vector_status' => $synced === 0 ? 'fallback' : ($ok ? 'synced' : 'failed'),
                'vector_error' => $ok ? null : $store->providerLabel().' vector sync failed; database fallback remains active.',
            ])->saveQuietly();
        }

        return $summary;
    }
}
