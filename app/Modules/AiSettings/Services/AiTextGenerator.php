<?php

namespace App\Modules\AiSettings\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Exceptions\FailoverableException;
use Laravel\Ai\Responses\AgentResponse;
use RuntimeException;
use Throwable;

/**
 * Runs text prompts against the platform AI providers, moving to the next
 * configured provider whenever one is rate limited, out of quota or failing.
 */
class AiTextGenerator
{
    protected const COOLDOWN_CACHE_PREFIX = 'ai_provider_cooldown:';

    /**
     * Keyed text providers tried after the configured fallback order. Ollama is
     * excluded because it always has a default URL; list it explicitly to use it.
     *
     * @var list<string>
     */
    protected const TEXT_PROVIDERS = ['gemini', 'groq', 'openrouter', 'mistral', 'openai', 'anthropic', 'deepseek', 'xai', 'azure'];

    public function __construct(
        protected AiSettingsService $settings,
        protected AiUsageLogger $usageLogger,
    ) {}

    public function isAvailable(): bool
    {
        return $this->configuredProviders() !== [];
    }

    /**
     * The provider that will be tried first, if any.
     */
    public function primaryProvider(): ?string
    {
        return array_key_first($this->providerChain()) ?? array_key_first($this->configuredProviders());
    }

    public function primaryModel(): ?string
    {
        $provider = $this->primaryProvider();

        return $provider === null ? null : $this->configuredProviders()[$provider];
    }

    /**
     * @param  array<string, mixed>  $context  Extra usage-log fields (workspace_id, user_id, user, metadata).
     * @return array{response: AgentResponse, provider: string, model: string}
     *
     * @throws Throwable The last provider error when every provider failed.
     */
    public function prompt(string $feature, string $instructions, string $prompt, array $context = [], int $timeout = 20): array
    {
        $chain = $this->providerChain() ?: $this->configuredProviders();

        if ($chain === []) {
            throw new RuntimeException('No AI text provider is configured or available.');
        }

        $lastException = null;

        foreach ($chain as $provider => $model) {
            try {
                $response = $this->usageLogger->measure(
                    array_merge($context, [
                        'feature' => $feature,
                        'provider' => $provider,
                        'model' => $model,
                        'request' => $prompt,
                    ]),
                    fn (): AgentResponse => (new AnonymousAgent(
                        instructions: $instructions,
                        messages: [],
                        tools: [],
                    ))->prompt(
                        prompt: $prompt,
                        provider: $provider,
                        model: $model,
                        timeout: $timeout,
                    ),
                );

                return ['response' => $response, 'provider' => $provider, 'model' => $model];
            } catch (ValidationException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                $lastException = $exception;

                if ($exception instanceof FailoverableException) {
                    $this->coolDown($provider);
                }

                Log::warning('AI provider failed, trying next provider.', [
                    'feature' => $feature,
                    'provider' => $provider,
                    'model' => $model,
                    'exception' => $exception::class,
                    'message' => Str::limit($exception->getMessage(), 220, ''),
                ]);
            }
        }

        throw $lastException;
    }

    /**
     * Ordered provider => model list to try: default provider first, then the
     * fallback order, skipping unconfigured providers and those cooling down.
     *
     * @return array<string, string>
     */
    public function providerChain(): array
    {
        $chain = [];

        foreach ($this->configuredProviders() as $provider => $model) {
            if (! Cache::has(self::COOLDOWN_CACHE_PREFIX.$provider)) {
                $chain[$provider] = $model;
            }
        }

        return $chain;
    }

    /**
     * @return array<string, string>
     */
    protected function configuredProviders(): array
    {
        $primary = $this->settings->textProvider();
        $candidates = array_merge([$primary], $this->fallbackOrder(), self::TEXT_PROVIDERS);
        $providers = [];

        foreach ($candidates as $provider) {
            if (isset($providers[$provider]) || ! $this->settings->hasConfiguredProvider($provider)) {
                continue;
            }

            $providers[$provider] = $this->modelFor($provider, $provider === $primary);
        }

        return $providers;
    }

    /**
     * @return list<string>
     */
    protected function fallbackOrder(): array
    {
        $order = (string) $this->settings->get('ai_text_failover_order', '');

        return collect(explode(',', $order))
            ->map(fn (string $provider): string => trim(strtolower($provider)))
            ->filter()
            ->map(fn (string $provider): string => match ($provider) {
                'azure-openai' => 'azure',
                default => $provider,
            })
            ->values()
            ->all();
    }

    protected function modelFor(string $provider, bool $isPrimary): string
    {
        $slug = $provider === 'azure' ? 'azure_openai' : $provider;
        $providerModel = $this->settings->get("{$slug}_text_model");

        if (filled($providerModel)) {
            return (string) $providerModel;
        }

        if ($isPrimary && filled($this->settings->textModel())) {
            return (string) $this->settings->textModel();
        }

        return $this->defaultModelFor($provider);
    }

    public function defaultModelFor(string $provider): string
    {
        return match ($provider) {
            'anthropic' => 'claude-sonnet-4-20250514',
            'gemini' => 'gemini-3-flash-preview',
            'groq' => 'llama-3.3-70b-versatile',
            'xai' => 'grok-3-mini',
            'deepseek' => 'deepseek-chat',
            'mistral' => 'mistral-small-latest',
            'ollama' => 'llama3.1',
            'openrouter' => 'meta-llama/llama-3.3-70b-instruct:free',
            default => 'gpt-4o',
        };
    }

    protected function coolDown(string $provider): void
    {
        $minutes = (int) $this->settings->get('ai_failover_cooldown_minutes', 10);

        if ($minutes > 0) {
            Cache::put(self::COOLDOWN_CACHE_PREFIX.$provider, true, now()->addMinutes($minutes));
        }
    }
}
