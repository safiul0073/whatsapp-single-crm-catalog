<?php

namespace App\Modules\KnowledgeBases\Jobs;

use App\Modules\KnowledgeBases\Models\KnowledgeBaseSource;
use App\Modules\KnowledgeBases\Services\KnowledgeBaseIndexingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IndexKnowledgeBaseSourceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $sourceId) {}

    public function handle(KnowledgeBaseIndexingService $indexer): void
    {
        $source = KnowledgeBaseSource::query()->with('knowledgeBase')->find($this->sourceId);

        if (! $source) {
            return;
        }

        $indexer->index($source);
    }
}
