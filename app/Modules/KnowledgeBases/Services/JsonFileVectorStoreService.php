<?php

namespace App\Modules\KnowledgeBases\Services;

use App\Modules\AiSettings\Services\AiSettingsService;
use App\Modules\KnowledgeBases\Contracts\VectorStoreService;
use App\Modules\KnowledgeBases\Models\KnowledgeBaseChunk;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use SplPriorityQueue;
use Throwable;

/**
 * Free vector store that keeps embeddings in JSON files on local disk.
 *
 * Layout: storage/app/knowledge-bases/vectors/{knowledge_base_id}/source_{source_id}.json
 * Search is a brute-force cosine similarity scan, which is fine for small and
 * medium knowledge bases. Use Qdrant for very large ones.
 */
class JsonFileVectorStoreService implements VectorStoreService
{
    /**
     * Above this many chunks per knowledge base, Qdrant is recommended.
     */
    public const RECOMMENDED_MAX_CHUNKS = 20000;

    protected const PRECISION = 6;

    public function __construct(protected AiSettingsService $settings) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('vector_database_enabled', false)
            && $this->settings->get('vector_database_provider', 'json_file') === 'json_file';
    }

    public function providerLabel(): string
    {
        return 'JSON file';
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
                ->groupBy(fn (KnowledgeBaseChunk $chunk): string => $chunk->knowledge_base_id.':'.$chunk->source_id);

            foreach ($grouped as $sourceChunks) {
                $first = $sourceChunks->first();
                $filePath = $this->sourceFile((int) $first->knowledge_base_id, (int) $first->source_id);

                $this->withLockedFile($filePath, function (array $existing) use ($sourceChunks): array {
                    $byChunkId = collect($existing)->keyBy('chunk_id');

                    foreach ($sourceChunks as $chunk) {
                        $vectorId = $chunk->vector_id ?: (string) Str::uuid();

                        if ($chunk->vector_id !== $vectorId) {
                            $chunk->forceFill(['vector_id' => $vectorId])->saveQuietly();
                        }

                        $embedding = array_map(
                            fn ($value): float => round((float) $value, self::PRECISION),
                            array_values($chunk->embedding)
                        );

                        $byChunkId[$chunk->id] = [
                            'chunk_id' => $chunk->id,
                            'vector_id' => $vectorId,
                            'norm' => $this->magnitude($embedding),
                            'embedding' => $embedding,
                        ];
                    }

                    return $byChunkId->values()->all();
                });
            }

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public function deleteForSource(int $sourceId): void
    {
        $baseDirectory = $this->baseDirectory();

        if (! File::isDirectory($baseDirectory)) {
            return;
        }

        try {
            // Scan every knowledge base folder so we also clean up after chunks
            // that have already been removed from the database.
            foreach (File::directories($baseDirectory) as $kbDirectory) {
                $filePath = $kbDirectory.'/source_'.$sourceId.'.json';

                File::delete([$filePath, $filePath.'.lock']);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Remove every stored vector for a knowledge base (used before a full re-index).
     */
    public function flushKnowledgeBase(int $knowledgeBaseId): void
    {
        File::deleteDirectory($this->knowledgeBaseDirectory($knowledgeBaseId));
    }

    /**
     * @param  array<int, int>  $knowledgeBaseIds
     * @param  array<float>  $embedding
     * @return Collection<int, KnowledgeBaseChunk>
     */
    public function search(array $knowledgeBaseIds, array $embedding, int $limit): Collection
    {
        if (! $this->enabled() || $knowledgeBaseIds === [] || $embedding === [] || $limit < 1) {
            return collect();
        }

        try {
            $query = array_map('floatval', array_values($embedding));
            $queryNorm = $this->magnitude($query);

            if ($queryNorm === 0.0) {
                return collect();
            }

            $threshold = (float) $this->settings->get('qdrant_score_threshold', 0.2);

            // Min-heap of the best $limit matches (SplPriorityQueue is a max-heap, so negate).
            $heap = new SplPriorityQueue;
            $heap->setExtractFlags(SplPriorityQueue::EXTR_BOTH);

            foreach (array_unique($knowledgeBaseIds) as $knowledgeBaseId) {
                $directory = $this->knowledgeBaseDirectory((int) $knowledgeBaseId);

                if (! File::isDirectory($directory)) {
                    continue;
                }

                foreach (File::glob($directory.'/source_*.json') as $file) {
                    foreach ($this->decode((string) @file_get_contents($file)) as $vector) {
                        $score = $this->score($query, $queryNorm, $vector);

                        if ($score < $threshold) {
                            continue;
                        }

                        if ($heap->count() < $limit) {
                            $heap->insert((int) $vector['chunk_id'], -$score);
                        } elseif ($score > -$heap->top()['priority']) {
                            $heap->extract();
                            $heap->insert((int) $vector['chunk_id'], -$score);
                        }
                    }
                }
            }

            if ($heap->isEmpty()) {
                return collect();
            }

            $scores = [];

            foreach (clone $heap as $item) {
                $scores[$item['data']] = -$item['priority'];
            }

            return KnowledgeBaseChunk::query()
                ->with(['knowledgeBase', 'source'])
                ->whereIn('id', array_keys($scores))
                ->get()
                ->each(fn (KnowledgeBaseChunk $chunk) => $chunk->setAttribute('score', $scores[$chunk->id] ?? null))
                ->sortByDesc(fn (KnowledgeBaseChunk $chunk): float => (float) $chunk->getAttribute('score'))
                ->values();
        } catch (Throwable $exception) {
            report($exception);

            return collect();
        }
    }

    /**
     * Number of vectors stored for a knowledge base.
     */
    public function countForKnowledgeBase(int $knowledgeBaseId): int
    {
        $directory = $this->knowledgeBaseDirectory($knowledgeBaseId);

        if (! File::isDirectory($directory)) {
            return 0;
        }

        $count = 0;

        foreach (File::glob($directory.'/source_*.json') as $file) {
            $count += count($this->decode((string) @file_get_contents($file)));
        }

        return $count;
    }

    /**
     * @param  array<int, float>  $query
     * @param  array<string, mixed>  $vector
     */
    protected function score(array $query, float $queryNorm, array $vector): float
    {
        $stored = $vector['embedding'] ?? [];

        if (! is_array($stored) || $stored === []) {
            return 0.0;
        }

        $norm = (float) ($vector['norm'] ?? $this->magnitude($stored));

        if ($norm === 0.0) {
            return 0.0;
        }

        $dot = 0.0;
        $count = min(count($query), count($stored));

        for ($index = 0; $index < $count; $index++) {
            $dot += $query[$index] * $stored[$index];
        }

        return $dot / ($queryNorm * $norm);
    }

    /**
     * @param  array<int, float|int>  $vector
     */
    protected function magnitude(array $vector): float
    {
        $sum = 0.0;

        foreach ($vector as $value) {
            $sum += $value * $value;
        }

        return sqrt($sum);
    }

    /**
     * Read-modify-write a source file under an exclusive lock, writing atomically.
     *
     * @param  callable(array<int, array<string, mixed>>): array<int, array<string, mixed>>  $callback
     */
    protected function withLockedFile(string $filePath, callable $callback): void
    {
        File::ensureDirectoryExists(dirname($filePath));

        $lock = fopen($filePath.'.lock', 'c');

        if ($lock === false) {
            throw new RuntimeException('Unable to open lock file for '.$filePath);
        }

        try {
            flock($lock, LOCK_EX);

            $existing = File::exists($filePath) ? $this->decode(File::get($filePath)) : [];
            $data = $callback($existing);

            $temporary = $filePath.'.'.Str::random(8).'.tmp';
            File::put($temporary, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));

            if (! @rename($temporary, $filePath)) {
                @unlink($temporary);

                throw new RuntimeException('Unable to write vector file '.$filePath);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function decode(string $contents): array
    {
        if ($contents === '') {
            return [];
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function baseDirectory(): string
    {
        return storage_path('app/knowledge-bases/vectors');
    }

    protected function knowledgeBaseDirectory(int $knowledgeBaseId): string
    {
        return $this->baseDirectory().'/'.$knowledgeBaseId;
    }

    protected function sourceFile(int $knowledgeBaseId, int $sourceId): string
    {
        return $this->knowledgeBaseDirectory($knowledgeBaseId).'/source_'.$sourceId.'.json';
    }
}
