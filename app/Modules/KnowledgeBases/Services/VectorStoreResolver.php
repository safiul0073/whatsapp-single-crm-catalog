<?php

namespace App\Modules\KnowledgeBases\Services;

use App\Modules\AiSettings\Services\AiSettingsService;
use App\Modules\KnowledgeBases\Contracts\VectorStoreService;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the vector store implementation for a provider key.
 */
class VectorStoreResolver
{
    public const PROVIDERS = [
        'json_file' => JsonFileVectorStoreService::class,
        'qdrant' => QdrantVectorStoreService::class,
    ];

    public const DEFAULT_PROVIDER = 'json_file';

    public function __construct(
        protected Container $container,
        protected AiSettingsService $settings,
    ) {}

    public function currentProvider(): string
    {
        $provider = (string) $this->settings->get('vector_database_provider', self::DEFAULT_PROVIDER);

        return array_key_exists($provider, self::PROVIDERS) ? $provider : self::DEFAULT_PROVIDER;
    }

    public function current(): VectorStoreService
    {
        return $this->for($this->currentProvider());
    }

    public function for(?string $provider): VectorStoreService
    {
        $class = self::PROVIDERS[$provider] ?? self::PROVIDERS[self::DEFAULT_PROVIDER];

        return $this->container->make($class);
    }
}
