<?php

namespace App\Modules\KnowledgeBases\Jobs;

use App\Modules\KnowledgeBases\Services\KnowledgeBaseVectorReindexer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReindexKnowledgeBaseVectorsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public function __construct(public ?int $knowledgeBaseId = null) {}

    public function uniqueId(): string
    {
        return (string) ($this->knowledgeBaseId ?? 'all');
    }

    public function handle(KnowledgeBaseVectorReindexer $reindexer): void
    {
        $reindexer->reindex($this->knowledgeBaseId);
    }
}
