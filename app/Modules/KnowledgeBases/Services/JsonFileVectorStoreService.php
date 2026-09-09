<?php

namespace App\Modules\KnowledgeBases\Services;

use App\Modules\AiSettings\Services\AiSettingsService;
use App\Modules\KnowledgeBases\Contracts\VectorStoreService;
use App\Modules\KnowledgeBases\Models\KnowledgeBaseChunk;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class JsonFileVectorStoreService implements VectorStoreService
{
    public function __construct(protected AiSettingsService $settings) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('vector_database_enabled', false)
            && $this->settings->get('vector_database_provider', 'qdrant') === 'json_file';
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function testConnection(array $settings = []): array
    {
        $directory = $this->baseDirectory();

        try {
            File::ensureDirectoryExists($directory);

            if (! is_writable($directory)) {
                return ['ok' => false, 'message' => 'Vector storage directory is not writable: '.$directory];
            }

            return ['ok' => true, 'message' => 'JSON file vector store is ready at '.$directory];
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => 'JSON file vector store failed: '.$exception->getMessage()];
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

        try {
            $grouped = $chunks
                ->filter(fn (KnowledgeBaseChunk $chunk): bool => is_array($chunk->embedding) && $chunk->embedding !== [])
                ->groupBy('source_id');

            foreach ($grouped as $sourceId => $sourceChunks) {
                $knowledgeBaseId = $sourceChunks->first()->knowledge_base_id;
                $directory = $this->sourceDirectory($knowledgeBaseId);
                File::ensureDirectoryExists($directory);

                $existing = $this->readSourceFile($knowledgeBaseId, (int) $sourceId);
                $existingByChunkId = collect($existing)->keyBy('chunk_id');

                foreach ($sourceChunks as $chunk) {
                    $vectorId = $chunk->vector_id ?: (string) Str::uuid();
                    $chunk->forceFill(['vector_id' => $vectorId])->save();

                    $existingByChunkId[$chunk->id] = [
                        'chunk_id' => $chunk->id,
                        'vector_id' => $vectorId,
                        'embedding' => $chunk->embedding,
                        'payload' => [
                            'workspace_id' => $chunk->knowledgeBase?->workspace_id,
                            'knowledge_base_id' => $chunk->knowledge_base_id,
                            'source_id' => $chunk->source_id,
                            'source_title' => $chunk->source?->title,
                            'source_type' => $chunk->source?->type,
                        ],
                    ];
                }

                $filePath = $directory.'/source_'.$sourceId.'.json';
                File::put($filePath, json_encode($existingByChunkId->values()->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            return true;
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
            $knowledgeBaseIds = KnowledgeBaseChunk::query()
                ->where('source_id', $sourceId)
                ->distinct()
                ->pluck('knowledge_base_id')
                ->all();

            foreach ($knowledgeBaseIds as $knowledgeBaseId) {
                $filePath = $this->sourceDirectory($knowledgeBaseId).'/source_'.$sourceId.'.json';

                if (File::exists($filePath)) {
                    File::delete($filePath);
                }
            }

            if ($knowledgeBaseIds === []) {
                $baseDirectory = $this->baseDirectory();

                if (! File::isDirectory($baseDirectory)) {
                    return;
                }

                foreach (File::directories($baseDirectory) as $kbDirectory) {
                    $filePath = $kbDirectory.'/source_'.$sourceId.'.json';

                    if (File::exists($filePath)) {
                        File::delete($filePath);
                    }
                }
            }
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
            $scoreThreshold = (float) $this->settings->get('qdrant_score_threshold', 0.2);
            $allMatches = collect();

            foreach ($knowledgeBaseIds as $knowledgeBaseId) {
                $directory = $this->sourceDirectory($knowledgeBaseId);

                if (! File::isDirectory($directory)) {
                    continue;
                }

                foreach (File::files($directory) as $file) {
                    if ($file->getExtension() !== 'json') {
                        continue;
                    }

                    $vectors = json_decode($file->getContents(), true) ?: [];

                    foreach ($vectors as $vector) {
                        $score = $this->cosineSimilarity($embedding, $vector['embedding'] ?? []);

                        if ($score >= $scoreThreshold) {
                            $allMatches->push([
                                'chunk_id' => $vector['chunk_id'],
                                'score' => $score,
                            ]);
                        }
                    }
                }
            }

            if ($allMatches->isEmpty()) {
                return collect();
            }

            $topMatches = $allMatches->sortByDesc('score')->take($limit);
            $chunkIds = $topMatches->pluck('chunk_id')->all();
            $scores = $topMatches->pluck('score', 'chunk_id');

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

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function readSourceFile(int $knowledgeBaseId, int $sourceId): array
    {
        $filePath = $this->sourceDirectory($knowledgeBaseId).'/source_'.$sourceId.'.json';

        if (! File::exists($filePath)) {
            return [];
        }

        return json_decode(File::get($filePath), true) ?: [];
    }

    protected function baseDirectory(): string
    {
        return storage_path('app/knowledge-bases/vectors');
    }

    protected function sourceDirectory(int $knowledgeBaseId): string
    {
        return $this->baseDirectory().'/'.$knowledgeBaseId;
    }
}
