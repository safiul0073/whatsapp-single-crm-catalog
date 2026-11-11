<?php

use App\Modules\AiSettings\Models\AiSetting;
use App\Modules\AiSettings\Models\AiUsageLog;
use App\Modules\AiSettings\Services\AiTextGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Exceptions\RateLimitedException;

uses(RefreshDatabase::class);

/**
 * @param  array<string, string>  $settings
 * @param  list<string>  $providersWithKeys
 */
function configureAiFailover(array $settings, array $providersWithKeys): void
{
    Cache::flush();

    $providers = [];
    foreach ($providersWithKeys as $provider) {
        $providers[$provider] = ['driver' => $provider, 'key' => "{$provider}-test-key"];
    }

    config(['ai.default' => 'openai', 'ai.providers' => $providers]);

    foreach ($settings as $key => $value) {
        AiSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    Cache::forget('ai_settings_cache');
}

/**
 * @param  list<string>  $calls
 */
function fakeAiProviders(Closure $handler, array &$calls = []): void
{
    AnonymousAgent::fake(function (string $prompt, $attachments, $provider, string $model) use ($handler, &$calls): string {
        $calls[] = $provider->name();

        return $handler($provider->name(), $model);
    });
}

it('fails over to the next provider when the default provider is rate limited', function (): void {
    configureAiFailover([
        'ai_default_text_provider' => 'gemini',
        'ai_text_failover_order' => 'groq',
        'groq_text_model' => 'llama-test',
    ], ['gemini', 'groq']);

    $calls = [];
    fakeAiProviders(function (string $provider): string {
        if ($provider === 'gemini') {
            throw RateLimitedException::forProvider('gemini', 429);
        }

        return "answer from {$provider}";
    }, $calls);

    $result = app(AiTextGenerator::class)->prompt('inbox_ai_reply', 'Be brief.', 'Hello');

    expect($result['provider'])->toBe('groq')
        ->and($result['model'])->toBe('llama-test')
        ->and((string) $result['response']->text)->toBe('answer from groq')
        ->and($calls)->toBe(['gemini', 'groq']);

    expect(AiUsageLog::query()->orderBy('id')->get(['provider', 'status'])->toArray())->toBe([
        ['provider' => 'gemini', 'status' => 'failed'],
        ['provider' => 'groq', 'status' => 'success'],
    ]);
});

it('skips a rate limited provider while it is cooling down', function (): void {
    configureAiFailover([
        'ai_default_text_provider' => 'gemini',
        'ai_text_failover_order' => 'groq',
        'ai_failover_cooldown_minutes' => '10',
    ], ['gemini', 'groq']);

    $calls = [];
    fakeAiProviders(function (string $provider): string {
        if ($provider === 'gemini') {
            throw RateLimitedException::forProvider('gemini', 429);
        }

        return 'ok';
    }, $calls);

    $generator = app(AiTextGenerator::class);
    $generator->prompt('inbox_ai_reply', 'Be brief.', 'First');
    $generator->prompt('inbox_ai_reply', 'Be brief.', 'Second');

    expect($calls)->toBe(['gemini', 'groq', 'groq'])
        ->and(array_keys($generator->providerChain()))->toBe(['groq']);
});

it('fails over on non rate limit provider errors without cooling the provider down', function (): void {
    configureAiFailover([
        'ai_default_text_provider' => 'gemini',
        'ai_text_failover_order' => 'groq',
    ], ['gemini', 'groq']);

    $calls = [];
    fakeAiProviders(function (string $provider): string {
        if ($provider === 'gemini') {
            throw new RuntimeException('Connection timed out');
        }

        return 'ok';
    }, $calls);

    $generator = app(AiTextGenerator::class);
    $result = $generator->prompt('inbox_ai_reply', 'Be brief.', 'Hello');

    expect($result['provider'])->toBe('groq')
        ->and($calls)->toBe(['gemini', 'groq'])
        ->and(array_keys($generator->providerChain()))->toBe(['gemini', 'groq']);
});

it('throws the last error when every provider fails', function (): void {
    configureAiFailover([
        'ai_default_text_provider' => 'gemini',
        'ai_text_failover_order' => 'groq',
    ], ['gemini', 'groq']);

    fakeAiProviders(function (string $provider): string {
        throw RateLimitedException::forProvider($provider, 429);
    });

    app(AiTextGenerator::class)->prompt('inbox_ai_reply', 'Be brief.', 'Hello');
})->throws(RateLimitedException::class, 'groq');

it('builds the chain from the default provider, the fallback order and any other configured provider', function (): void {
    configureAiFailover([
        'ai_default_text_provider' => 'mistral',
        'ai_text_failover_order' => 'openrouter, groq, gemini',
        'ai_default_text_model' => 'mistral-custom',
    ], ['mistral', 'groq', 'openai']);

    $chain = app(AiTextGenerator::class)->providerChain();

    expect($chain)->toBe([
        'mistral' => 'mistral-custom',
        'groq' => 'llama-3.3-70b-versatile',
        'openai' => 'gpt-4o',
    ]);
});

it('reports no provider when none has credentials', function (): void {
    configureAiFailover(['ai_text_failover_order' => 'gemini,groq'], []);

    $generator = app(AiTextGenerator::class);

    expect($generator->isAvailable())->toBeFalse()
        ->and($generator->primaryProvider())->toBeNull();
});

it('only uses ollama when it is the default or listed in the fallback order', function (): void {
    configureAiFailover([], []);
    config(['ai.providers.ollama' => ['driver' => 'ollama', 'url' => 'http://localhost:11434']]);

    expect(app(AiTextGenerator::class)->isAvailable())->toBeFalse();

    AiSetting::query()->updateOrCreate(['key' => 'ai_text_failover_order'], ['value' => 'ollama']);
    Cache::forget('ai_settings_cache');

    expect(array_keys(app(AiTextGenerator::class)->providerChain()))->toBe(['ollama']);
});
