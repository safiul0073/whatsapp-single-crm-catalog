<?php

namespace App\Modules\KnowledgeBases\Services;

use App\Modules\AiSettings\Services\AiSettingsService;
use App\Modules\AiSettings\Services\AiUsageLogger;
use Laravel\Ai\Embeddings;
use Throwable;

class KnowledgeBaseEmbeddingService
{
    public function __construct(
        protected AiSettingsService $settings,
        protected AiUsageLogger $usageLogger,
    ) {}

    /**
     * @return array<float>|null
     */
    public function embed(string $text, ?int $workspaceId = null): ?array
    {
        if (blank($text)) {
            return null;
        }

        if (app()->runningUnitTests()) {
            return $this->fakeEmbedding($text);
        }

        $provider = $this->settings->embeddingsProvider();

        if (! $provider || ! $this->settings->hasConfiguredProvider($provider)) {
            return null;
        }

        try {
            $model = $this->settings->embeddingsModel();
            $dimensions = (int) $this->settings->get('qdrant_vector_dimension', 1536);

            $response = $this->usageLogger->measure(
                [
                    'feature' => 'knowledge_base_embedding',
                    'workspace_id' => $workspaceId,
                    'provider' => $provider,
                    'model' => $model,
                    'request' => $text,
                    'metadata' => [
                        'source' => 'platform',
                        'dimensions' => $dimensions,
                    ],
                ],
                fn () => Embeddings::for([$text])
                    ->dimensions($dimensions)
                    ->timeout(20)
                    ->generate($provider, $model),
            );

            return $response->embeddings[0] ?? null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<float>
     */
    protected function fakeEmbedding(string $text): array
    {
        $hash = hash('sha256', $text);

        return collect(str_split(substr($hash, 0, 24), 2))
            ->map(fn (string $chunk): float => hexdec($chunk) / 255)
            ->all();
    }
}
