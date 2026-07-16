<?php

namespace App\Modules\KnowledgeBases\Services;

use App\Modules\KnowledgeBases\Models\KnowledgeBaseChunk;
use Illuminate\Support\Collection;

class KnowledgeBaseSearchResult
{
    /**
     * @param  Collection<int, KnowledgeBaseChunk>  $chunks
     */
    public function __construct(
        public Collection $chunks,
        public string $mode = 'database_fallback',
        public ?string $error = null,
    ) {}

    public function count(): int
    {
        return $this->chunks->count();
    }

    public function isEmpty(): bool
    {
        return $this->chunks->isEmpty();
    }

    public function isNotEmpty(): bool
    {
        return $this->chunks->isNotEmpty();
    }

    public function first(): ?KnowledgeBaseChunk
    {
        return $this->chunks->first();
    }

    /**
     * @return array<int, array{id: int|null, title: string|null, type: string|null}>
     */
    public function sourcesUsed(): array
    {
        return $this->chunks
            ->map(fn (KnowledgeBaseChunk $chunk): array => [
                'id' => $chunk->source?->id,
                'title' => $chunk->source?->title,
                'type' => $chunk->source?->type,
            ])
            ->unique('id')
            ->values()
            ->all();
    }
}
