<?php

namespace App\Modules\KnowledgeBases\Contracts;

use App\Modules\KnowledgeBases\Models\KnowledgeBaseChunk;
use Illuminate\Support\Collection;

interface VectorStoreService
{
    public function enabled(): bool;

    /**
     * @return array{ok: bool, message: string}
     */
    public function testConnection(array $settings = []): array;

    /**
     * @param  Collection<int, KnowledgeBaseChunk>  $chunks
     */
    public function upsert(Collection $chunks): bool;

    public function deleteForSource(int $sourceId): void;

    /**
     * @param  array<int, int>  $knowledgeBaseIds
     * @return Collection<int, KnowledgeBaseChunk>
     */
    public function search(array $knowledgeBaseIds, array $embedding, int $limit): Collection;
}
