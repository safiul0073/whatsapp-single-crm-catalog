<?php

namespace App\Modules\KnowledgeBases\Providers;

use App\Modules\AiSettings\Services\AiSettingsService;
use App\Modules\KnowledgeBases\Contracts\VectorStoreService;
use App\Modules\KnowledgeBases\Services\JsonFileVectorStoreService;
use App\Modules\KnowledgeBases\Services\KnowledgeBaseChunker;
use App\Modules\KnowledgeBases\Services\KnowledgeBaseEmbeddingService;
use App\Modules\KnowledgeBases\Services\KnowledgeBaseExtractionService;
use App\Modules\KnowledgeBases\Services\KnowledgeBaseIndexingService;
use App\Modules\KnowledgeBases\Services\KnowledgeBaseSearchService;
use App\Modules\KnowledgeBases\Services\KnowledgeBaseService;
use App\Modules\KnowledgeBases\Services\QdrantVectorStoreService;
use App\Modules\Shared\Support\BasePanelModuleProvider;

class KnowledgeBasesServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        $this->app->singleton(KnowledgeBaseService::class);
        $this->app->singleton(KnowledgeBaseExtractionService::class);
        $this->app->singleton(KnowledgeBaseChunker::class);
        $this->app->singleton(KnowledgeBaseEmbeddingService::class);
        $this->app->singleton(KnowledgeBaseIndexingService::class);
        $this->app->singleton(KnowledgeBaseSearchService::class);
        $this->app->singleton(QdrantVectorStoreService::class);
        $this->app->singleton(JsonFileVectorStoreService::class);

        $this->app->singleton(VectorStoreService::class, function ($app): VectorStoreService {
            $provider = $app->make(AiSettingsService::class)->get('vector_database_provider', 'qdrant');

            return match ($provider) {
                'json_file' => $app->make(JsonFileVectorStoreService::class),
                default => $app->make(QdrantVectorStoreService::class),
            };
        });
    }
}
