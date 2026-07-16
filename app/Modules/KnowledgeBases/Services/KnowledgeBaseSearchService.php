<?php

namespace App\Modules\KnowledgeBases\Services;

use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\KnowledgeBases\Models\KnowledgeBaseChunk;
use Illuminate\Support\Str;

class KnowledgeBaseSearchService
{
    public function __construct(
        protected KnowledgeBaseEmbeddingService $embeddings,
        protected QdrantVectorStoreService $vectors,
    ) {}

    public function search(Chatbot $chatbot, string $query, int $limit = 5): KnowledgeBaseSearchResult
    {
        $chatbot->loadMissing('knowledgeBases');
        $knowledgeBaseIds = $chatbot->knowledgeBases->pluck('id')->all();

        if ($knowledgeBaseIds === [] || blank($query)) {
            return new KnowledgeBaseSearchResult(collect());
        }

        $limit = (int) min(max($limit, 1), 25);
        $terms = $this->terms($query);
        $queryEmbedding = $this->embeddings->embed($query, $chatbot->workspace_id);

        if ($this->vectors->enabled() && $queryEmbedding !== null) {
            $matches = $this->vectors->search($knowledgeBaseIds, $queryEmbedding, $limit);

            if ($matches->isNotEmpty()) {
                return new KnowledgeBaseSearchResult($matches, 'qdrant');
            }
        }

        $matches = KnowledgeBaseChunk::query()
            ->with(['knowledgeBase', 'source'])
            ->whereIn('knowledge_base_id', $knowledgeBaseIds)
            ->get()
            ->map(function (KnowledgeBaseChunk $chunk) use ($queryEmbedding, $terms): KnowledgeBaseChunk {
                $score = $queryEmbedding !== null && is_array($chunk->embedding)
                    ? $this->cosineSimilarity($queryEmbedding, $chunk->embedding)
                    : $this->keywordScore($chunk->content, $terms);

                $chunk->setAttribute('score', $score);

                return $chunk;
            })
            ->filter(fn (KnowledgeBaseChunk $chunk): bool => (float) $chunk->getAttribute('score') > 0)
            ->sortByDesc(fn (KnowledgeBaseChunk $chunk): float => (float) $chunk->getAttribute('score'))
            ->take($limit)
            ->values();

        return new KnowledgeBaseSearchResult($matches, 'database_fallback');
    }

    /**
     * @return array<int, string>
     */
    protected function terms(string $query): array
    {
        return Str::of($query)
            ->lower()
            ->replaceMatches('/[^\pL\pN\s]+/u', ' ')
            ->explode(' ')
            ->map(fn (string $term): string => trim($term))
            ->filter(fn (string $term): bool => mb_strlen($term) > 2)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $terms
     */
    protected function keywordScore(string $content, array $terms): float
    {
        $content = Str::lower($content);

        return collect($terms)->sum(fn (string $term): int => Str::substrCount($content, $term));
    }

    /**
     * @param  array<float>  $left
     * @param  array<float>  $right
     */
    protected function cosineSimilarity(array $left, array $right): float
    {
        $dot = 0.0;
        $leftMagnitude = 0.0;
        $rightMagnitude = 0.0;
        $count = min(count($left), count($right));

        for ($index = 0; $index < $count; $index++) {
            $leftValue = (float) $left[$index];
            $rightValue = (float) $right[$index];
            $dot += $leftValue * $rightValue;
            $leftMagnitude += $leftValue * $leftValue;
            $rightMagnitude += $rightValue * $rightValue;
        }

        if ($leftMagnitude === 0.0 || $rightMagnitude === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($leftMagnitude) * sqrt($rightMagnitude));
    }
}
