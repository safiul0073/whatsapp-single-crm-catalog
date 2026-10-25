<?php

namespace App\Modules\KnowledgeBases\Providers;

use App\Modules\KnowledgeBases\Console\ProvisionDefaultKnowledgeBaseCommand;
use App\Modules\KnowledgeBases\Console\ReindexKnowledgeBaseVectorsCommand;
use App\Modules\KnowledgeBases\Contracts\VectorStoreService;
use App\Modules\KnowledgeBases\Services\DefaultKnowledgeBaseService;
use App\Modules\KnowledgeBases\Services\JsonFileVectorStoreService;
use App\Modules\KnowledgeBases\Services\KnowledgeBaseChunker;
use App\Modules\KnowledgeBases\Services\KnowledgeBaseEmbeddingService;
use App\Modules\KnowledgeBases\Services\KnowledgeBaseExtractionService;
use App\Modules\KnowledgeBases\Services\KnowledgeBaseIndexingService;
use App\Modules\KnowledgeBases\Services\KnowledgeBaseSearchService;
use App\Modules\KnowledgeBases\Services\KnowledgeBaseService;
use App\Modules\KnowledgeBases\Services\KnowledgeBaseVectorReindexer;
use App\Modules\KnowledgeBases\Services\QdrantVectorStoreService;
use App\Modules\KnowledgeBases\Services\VectorStoreResolver;
use App\Modules\Shared\Support\BasePanelModuleProvider;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Facades\Schema;
use Throwable;

class KnowledgeBasesServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        $this->app->singleton(KnowledgeBaseService::class);
        $this->app->singleton(KnowledgeBaseExtractionService::class);
        $this->app->singleton(KnowledgeBaseChunker::class);
        $this->app->singleton(KnowledgeBaseEmbeddingService::class);
        // Not singletons: they depend on the active vector store, which can change
        // at runtime and must be re-resolved in long-running queue workers.
        $this->app->bind(KnowledgeBaseIndexingService::class);
        $this->app->bind(KnowledgeBaseSearchService::class);
        $this->app->bind(KnowledgeBaseVectorReindexer::class);
        $this->app->singleton(QdrantVectorStoreService::class);
        $this->app->singleton(JsonFileVectorStoreService::class);

        $this->app->singleton(VectorStoreResolver::class);
        $this->app->bind(VectorStoreService::class, fn ($app): VectorStoreService => $app->make(VectorStoreResolver::class)->current());

        if ($this->app->runningInConsole()) {
            $this->commands([ReindexKnowledgeBaseVectorsCommand::class, ProvisionDefaultKnowledgeBaseCommand::class]);
        }
    }

    protected function bootModule(array $module): void
    {
        Workspace::created(function (Workspace $workspace): void {
            if (! config('knowledge-bases.auto_provision') || ! Schema::hasTable('knowledge_bases') || ! Schema::hasTable('chatbots')) {
                return;
            }

            try {
                app(DefaultKnowledgeBaseService::class)->provision($workspace);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
