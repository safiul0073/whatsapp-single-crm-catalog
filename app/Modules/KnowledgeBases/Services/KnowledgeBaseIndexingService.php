<?php

namespace App\Modules\KnowledgeBases\Services;

use App\Modules\KnowledgeBases\Models\KnowledgeBaseSource;
use Throwable;

class KnowledgeBaseIndexingService
{
    public function __construct(
        protected KnowledgeBaseService $knowledgeBases,
        protected KnowledgeBaseExtractionService $extractor,
        protected KnowledgeBaseChunker $chunker,
        protected KnowledgeBaseEmbeddingService $embeddings,
        protected QdrantVectorStoreService $vectors,
    ) {}

    public function index(KnowledgeBaseSource $source): void
    {
        $source->loadMissing('knowledgeBase');
        $source->update([
            'status' => 'indexing',
            'error' => null,
            'vector_error' => null,
            'vector_status' => 'pending',
        ]);

        try {
            $result = $this->extractor->extract($source);
            $text = $result->text;

            if (blank($text)) {
                throw new \RuntimeException('No readable text was found in this source.');
            }

            $this->vectors->deleteForSource($source->id);
            $source->chunks()->delete();

            $chunks = $this->chunker->split($text);
            $tokenCount = 0;

            foreach ($chunks as $position => $chunk) {
                $chunkTokenCount = str_word_count($chunk);
                $tokenCount += $chunkTokenCount;

                $source->chunks()->create([
                    'knowledge_base_id' => $source->knowledge_base_id,
                    'content' => $chunk,
                    'embedding' => $this->embeddings->embed($chunk, $source->knowledgeBase?->workspace_id),
                    'token_count' => $chunkTokenCount,
                    'position' => $position,
                    'metadata' => [
                        'source_title' => $source->title,
                        ...$result->metadata,
                    ],
                ]);
            }

            $source->load('chunks.knowledgeBase', 'chunks.source');
            $vectorStatus = 'fallback';
            $vectorError = null;

            if ($this->vectors->enabled()) {
                $vectorStatus = $this->vectors->upsert($source->chunks) ? 'synced' : 'failed';
                $vectorError = $vectorStatus === 'failed' ? 'Qdrant vector sync failed; database fallback remains active.' : null;
            }

            $source->update([
                'status' => 'ready',
                'error' => null,
                'token_count' => $tokenCount,
                'chunks_count' => count($chunks),
                'checksum' => hash('sha256', $text),
                'vector_status' => $vectorStatus,
                'vector_error' => $vectorError,
                'metadata' => array_merge((array) $source->metadata, $result->metadata),
                'last_indexed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $source->update([
                'status' => 'error',
                'error' => $exception->getMessage(),
                'vector_status' => 'failed',
                'vector_error' => $exception->getMessage(),
            ]);
        } finally {
            $this->knowledgeBases->refreshStats($source->knowledgeBase);
        }
    }
}
