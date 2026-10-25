<?php

namespace App\Modules\KnowledgeBases\Console;

use App\Modules\KnowledgeBases\Services\KnowledgeBaseVectorReindexer;
use App\Modules\KnowledgeBases\Services\VectorStoreResolver;
use Illuminate\Console\Command;

class ReindexKnowledgeBaseVectorsCommand extends Command
{
    protected $signature = 'knowledge-base:reindex-vectors {--kb= : Only re-index this knowledge base ID}';

    protected $description = 'Copy stored knowledge base embeddings into the active vector store (JSON file or Qdrant)';

    public function handle(KnowledgeBaseVectorReindexer $reindexer, VectorStoreResolver $resolver): int
    {
        $store = $resolver->current();

        if (! $store->enabled()) {
            $this->warn('Vector search is disabled. Enable it in Admin → AI Settings → Vector Database first.');

            return self::FAILURE;
        }

        $knowledgeBaseId = $this->option('kb') !== null ? (int) $this->option('kb') : null;
        $result = $reindexer->reindex($knowledgeBaseId);

        $this->info(sprintf(
            'Re-indexed %d chunks from %d sources into %s (%d failed).',
            $result['chunks'],
            $result['sources'],
            $result['provider'],
            $result['failed'],
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
