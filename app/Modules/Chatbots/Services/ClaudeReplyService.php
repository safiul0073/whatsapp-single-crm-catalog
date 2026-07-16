<?php

namespace App\Modules\Chatbots\Services;

use App\Modules\AiSettings\Services\AiSettingsService;
use App\Modules\AiSettings\Services\AiUsageLogger;
use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\KnowledgeBases\Models\KnowledgeBaseChunk;
use App\Modules\KnowledgeBases\Services\KnowledgeBaseSearchResult;
use App\Modules\KnowledgeBases\Services\KnowledgeBaseSearchService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\AnonymousAgent;
use Throwable;

class ClaudeReplyService
{
    public function __construct(
        protected AiSettingsService $settings,
        protected KnowledgeBaseSearchService $knowledgeBases,
        protected AiUsageLogger $usageLogger,
    ) {}

    /**
     * @return array{provider: ?string, model: ?string, reply: string, confidence: float, handoff: bool, context: array<string, mixed>, input: string}
     */
    public function draftReply(string $message, array $context = []): array
    {
        $chatbot = $context['chatbot'] ?? null;

        if (! $chatbot instanceof Chatbot) {
            return $this->fallback($message, $context, null, null, 'No chatbot was selected for this test.');
        }

        $chatbot->loadMissing('knowledgeBases');
        $provider = $this->settings->textProvider();
        $model = $this->settings->textModel();
        $search = $this->knowledgeBases->search($chatbot, $message);

        if (! app()->runningUnitTests() && ! $this->settings->hasConfiguredProvider($provider)) {
            return $this->fallback($message, $context, $provider, $model, 'Platform AI is not configured for text generation.');
        }

        if ($this->isGreeting($message)) {
            return [
                'provider' => $provider,
                'model' => $model,
                'reply' => filled($chatbot->greeting) ? $chatbot->greeting : 'Hi, how can I help you?',
                'confidence' => 0.7,
                'handoff' => false,
                'context' => $this->contextWithSearch($context, $search),
                'input' => $message,
            ];
        }

        if ($chatbot->fallback_only_knowledge_base && $chatbot->knowledgeBases->isNotEmpty() && $search->isEmpty()) {
            return $this->fallback($message, $this->contextWithSearch($context, $search), $provider, $model, data_get($chatbot->handoff_rules, 'message') ?: 'I could not find this in the selected knowledge base. I will connect you with a team member.');
        }

        if (app()->runningUnitTests()) {
            $knowledgeReply = $search->isNotEmpty()
                ? 'Test reply generated from knowledge context: '.Str::limit($search->first()->content, 220, '')
                : 'Test reply generated from the saved chatbot configuration.';

            return [
                'provider' => $provider,
                'model' => $model,
                'reply' => $knowledgeReply,
                'confidence' => 0.76,
                'handoff' => false,
                'context' => $this->contextWithSearch($context, $search),
                'input' => $message,
            ];
        }

        try {
            $agent = new AnonymousAgent(
                instructions: $this->instructions($chatbot, $search->chunks),
                messages: [],
                tools: [],
            );

            $response = $this->usageLogger->measure(
                [
                    'feature' => 'chatbot_reply',
                    'workspace_id' => $chatbot->workspace_id,
                    'provider' => $provider,
                    'model' => $model,
                    'request' => $message,
                    'metadata' => [
                        'chatbot_id' => $chatbot->id,
                        'source' => 'platform',
                        'knowledge_context_count' => $search->count(),
                        'search_mode' => $search->mode,
                    ],
                ],
                fn () => $agent->prompt(
                    prompt: $message,
                    provider: $provider,
                    model: $model,
                    timeout: 20,
                ),
            );

            return [
                'provider' => $provider,
                'model' => $model,
                'reply' => trim((string) $response->text),
                'confidence' => 0.8,
                'handoff' => false,
                'context' => $this->contextWithSearch($context, $search),
                'input' => $message,
            ];
        } catch (Throwable $exception) {
            Log::warning('Chatbot AI reply generation failed.', [
                'chatbot_id' => $chatbot->id,
                'provider' => $provider,
                'model' => $model,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $this->fallback($message, $context, $provider, $model, 'Platform AI could not generate a reply. Check AI Settings and try again.');
        }
    }

    /**
     * @param  Collection<int, KnowledgeBaseChunk>  $matches
     */
    protected function instructions(Chatbot $chatbot, Collection $matches): string
    {
        $knowledgeBases = $chatbot->knowledgeBases
            ->map(fn ($knowledgeBase): string => '- '.$knowledgeBase->name.($knowledgeBase->description ? ': '.$knowledgeBase->description : ''))
            ->implode(PHP_EOL);
        $knowledgeContext = $matches
            ->map(fn (KnowledgeBaseChunk $chunk): string => "- {$chunk->source?->title}: {$chunk->content}")
            ->implode(PHP_EOL);

        $handoffMessage = data_get($chatbot->handoff_rules, 'message') ?: 'I will connect you with a team member.';

        return trim(<<<PROMPT
You are {$chatbot->name}, a workspace chatbot.

Persona and rules:
{$chatbot->persona}

Greeting:
{$chatbot->greeting}

Available knowledge bases:
{$knowledgeBases}

Knowledge context:
{$knowledgeContext}

Keep replies concise and helpful. If the customer asks for a human or the answer is outside your knowledge, reply with: {$handoffMessage}
PROMPT);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{provider: ?string, model: ?string, reply: string, confidence: float, handoff: bool, context: array<string, mixed>, input: string}
     */
    protected function fallback(string $message, array $context, ?string $provider, ?string $model, string $reply): array
    {
        return [
            'provider' => $provider,
            'model' => $model,
            'reply' => Str::limit($reply, 500, ''),
            'confidence' => 0.0,
            'handoff' => true,
            'context' => $context,
            'input' => $message,
        ];
    }

    protected function isGreeting(string $message): bool
    {
        $normalized = Str::of($message)
            ->lower()
            ->replaceMatches('/[^\pL\pN\s]+/u', ' ')
            ->squish()
            ->toString();

        return in_array($normalized, [
            'hi',
            'hello',
            'hey',
            'hi there',
            'hello there',
            'hey there',
            'good morning',
            'good afternoon',
            'good evening',
            'salam',
            'assalamualaikum',
            'thank you',
            'thanks',
        ], true);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function contextWithSearch(array $context, KnowledgeBaseSearchResult $search): array
    {
        return array_merge($context, [
            'knowledge_context_count' => $search->count(),
            'search_mode' => $search->mode,
            'sources_used' => $search->sourcesUsed(),
        ]);
    }
}
