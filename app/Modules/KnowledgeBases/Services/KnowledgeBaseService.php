<?php

namespace App\Modules\KnowledgeBases\Services;

use App\Models\User;
use App\Modules\KnowledgeBases\Jobs\IndexKnowledgeBaseSourceJob;
use App\Modules\KnowledgeBases\Models\KnowledgeBase;
use App\Modules\KnowledgeBases\Models\KnowledgeBaseSource;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;

class KnowledgeBaseService
{
    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected QdrantVectorStoreService $vectors,
    ) {}

    public function listForUser(?User $user, array $filters = []): LengthAwarePaginator
    {
        $workspace = $this->workspaces->current($user);
        $status = (string) ($filters['status'] ?? 'all');
        $search = trim((string) ($filters['q'] ?? ''));

        return KnowledgeBase::query()
            ->where('workspace_id', $workspace->id)
            ->withCount('chatbots')
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(12)
            ->withQueryString();
    }

    /**
     * @return array{total: int, sources: int, chunks: int, indexing: int, failed: int, tokens: int}
     */
    public function statsForUser(?User $user): array
    {
        $workspace = $this->workspaces->current($user);
        $query = KnowledgeBase::query()->where('workspace_id', $workspace->id);

        return [
            'total' => (clone $query)->count(),
            'sources' => (int) (clone $query)->sum('sources_count'),
            'chunks' => (int) (clone $query)->sum('chunks_count'),
            'indexing' => (clone $query)->where('status', 'indexing')->count(),
            'failed' => (clone $query)->where('status', 'error')->count(),
            'tokens' => (int) KnowledgeBaseSource::query()
                ->whereHas('knowledgeBase', fn (Builder $query) => $query->where('workspace_id', $workspace->id))
                ->sum('token_count'),
        ];
    }

    public function create(?User $user, array $data): KnowledgeBase
    {
        $workspace = $this->workspaces->current($user);

        return KnowledgeBase::query()->create([
            'workspace_id' => $workspace->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => 'ready',
            'visibility' => $data['visibility'] ?? 'workspace',
            'settings' => [],
        ]);
    }

    public function update(?User $user, KnowledgeBase $knowledgeBase, array $data): KnowledgeBase
    {
        $knowledgeBase = $this->forUser($user, $knowledgeBase);
        $knowledgeBase->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'visibility' => $data['visibility'] ?? 'workspace',
        ]);

        return $knowledgeBase->fresh();
    }

    public function delete(?User $user, KnowledgeBase $knowledgeBase): void
    {
        $knowledgeBase = $this->forUser($user, $knowledgeBase)->load('sources');
        $knowledgeBase->sources->each(fn (KnowledgeBaseSource $source) => $this->vectors->deleteForSource($source->id));
        $knowledgeBase->delete();
    }

    public function addSource(?User $user, KnowledgeBase $knowledgeBase, array $data, ?UploadedFile $file = null): KnowledgeBaseSource
    {
        $knowledgeBase = $this->forUser($user, $knowledgeBase);
        $type = (string) $data['type'];
        $metadata = [];
        $filePath = null;
        $content = $data['content'] ?? null;

        if ($type === 'qa') {
            $content = "Question: {$data['question']}\nAnswer: {$data['answer']}";
            $metadata = ['question' => $data['question'], 'answer' => $data['answer']];
        }

        if ($type === 'file' && $file) {
            $filePath = $file->store('knowledge-bases/'.$knowledgeBase->id, 'local');
            $metadata = [
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ];
        }

        if ($type === 'sitemap') {
            $metadata = ['crawl_limit' => (int) ($data['crawl_limit'] ?? 10)];
        }

        $source = $knowledgeBase->sources()->create([
            'type' => $type,
            'title' => $data['title'],
            'url' => $data['url'] ?? null,
            'file_path' => $filePath,
            'content' => $content,
            'status' => 'pending',
            'metadata' => $metadata,
        ]);

        $this->refreshStats($knowledgeBase);
        IndexKnowledgeBaseSourceJob::dispatch($source->id);

        return $source->fresh(['knowledgeBase']);
    }

    public function deleteSource(?User $user, KnowledgeBaseSource $source): void
    {
        $source = $this->sourceForUser($user, $source);
        $this->vectors->deleteForSource($source->id);
        $source->delete();
        $this->refreshStats($source->knowledgeBase);
    }

    public function reindexSource(?User $user, KnowledgeBaseSource $source): KnowledgeBaseSource
    {
        $source = $this->sourceForUser($user, $source);
        IndexKnowledgeBaseSourceJob::dispatch($source->id);

        return $source->fresh();
    }

    public function forUser(?User $user, KnowledgeBase $knowledgeBase): KnowledgeBase
    {
        $workspace = $this->workspaces->current($user);

        abort_unless($knowledgeBase->workspace_id === $workspace->id, 404);

        return $knowledgeBase;
    }

    public function sourceForUser(?User $user, KnowledgeBaseSource $source): KnowledgeBaseSource
    {
        $workspace = $this->workspaces->current($user);
        $source->loadMissing('knowledgeBase');

        abort_unless($source->knowledgeBase?->workspace_id === $workspace->id, 404);

        return $source;
    }

    public function refreshStats(KnowledgeBase $knowledgeBase): void
    {
        $sourcesCount = $knowledgeBase->sources()->count();
        $chunksCount = $knowledgeBase->chunks()->count();
        $hasIndexing = $knowledgeBase->sources()->whereIn('status', ['pending', 'indexing'])->exists();
        $hasError = $knowledgeBase->sources()->where('status', 'error')->exists();

        $knowledgeBase->update([
            'sources_count' => $sourcesCount,
            'chunks_count' => $chunksCount,
            'status' => $hasIndexing ? 'indexing' : ($hasError ? 'error' : 'ready'),
            'last_indexed_at' => $knowledgeBase->sources()->max('last_indexed_at'),
        ]);
    }
}
