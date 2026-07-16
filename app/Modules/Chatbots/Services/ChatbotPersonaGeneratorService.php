<?php

namespace App\Modules\Chatbots\Services;

use App\Modules\AiSettings\Services\AiSettingsService;
use App\Modules\AiSettings\Services\AiUsageLogger;
use App\Modules\KnowledgeBases\Models\KnowledgeBase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\AnonymousAgent;
use Throwable;

class ChatbotPersonaGeneratorService
{
    public function __construct(
        protected AiUsageLogger $usageLogger,
        protected AiSettingsService $settings,
    ) {}

    /**
     * @param  Collection<int, KnowledgeBase>  $knowledgeBases
     * @return array{persona: string, provider: string, model: ?string}
     */
    public function generate(array $data, Collection $knowledgeBases = new Collection, ?int $workspaceId = null): array
    {
        $provider = $this->settings->textProvider();
        $model = $this->settings->textModel();

        if (! app()->runningUnitTests() && ! $this->settings->hasConfiguredProvider($provider)) {
            throw ValidationException::withMessages([
                'ai' => __('Platform AI is not configured for text generation. Enable a text provider in AI Settings first.'),
            ]);
        }

        if (app()->runningUnitTests()) {
            return [
                'persona' => $this->testPersona($data, $knowledgeBases),
                'provider' => $provider,
                'model' => $model,
            ];
        }

        try {
            $agent = new AnonymousAgent(
                instructions: $this->instructions(),
                messages: [],
                tools: [],
            );
            $prompt = $this->prompt($data, $knowledgeBases);

            $response = $this->usageLogger->measure(
                [
                    'feature' => 'chatbot_persona_generation',
                    'workspace_id' => $workspaceId,
                    'user_id' => auth()->id(),
                    'provider' => $provider,
                    'model' => $model,
                    'request' => $prompt,
                    'metadata' => [
                        'knowledge_base_count' => $knowledgeBases->count(),
                    ],
                ],
                fn () => $agent->prompt(
                    prompt: $prompt,
                    provider: $provider,
                    model: $model,
                    timeout: 20,
                ),
            );

            $persona = trim((string) $response->text);

            if ($persona === '') {
                throw ValidationException::withMessages([
                    'ai' => __('AI could not generate chatbot instructions. Please try again.'),
                ]);
            }

            return [
                'persona' => Str::limit($persona, 5000, ''),
                'provider' => $provider,
                'model' => $model,
            ];
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Chatbot persona generation failed.', [
                'provider' => $provider,
                'model' => $model,
                'exception' => $exception::class,
                'message' => Str::limit($exception->getMessage(), 220, ''),
            ]);

            throw ValidationException::withMessages([
                'ai' => __('AI could not generate chatbot instructions. Check the platform AI settings and try again.'),
            ]);
        }
    }

    protected function instructions(): string
    {
        return <<<'PROMPT'
You write production-ready persona and operating instructions for a customer support AI chatbot.
Return only the text that should be saved in the persona/instructions field.
Include tone, responsibilities, boundaries, knowledge-base usage, escalation behavior, and concise response rules.
Do not include markdown headings that refer to this generation task.
PROMPT;
    }

    /**
     * @param  Collection<int, KnowledgeBase>  $knowledgeBases
     */
    protected function prompt(array $data, Collection $knowledgeBases): string
    {
        $knowledgeBaseNames = $knowledgeBases
            ->pluck('name')
            ->filter()
            ->values()
            ->implode(', ');

        return trim(sprintf(
            "Bot name: %s\nKnowledge bases: %s\nGreeting: %s\nExisting draft or special instruction: %s",
            filled($data['name'] ?? null) ? (string) $data['name'] : 'New support chatbot',
            $knowledgeBaseNames !== '' ? $knowledgeBaseNames : 'None selected',
            filled($data['greeting'] ?? null) ? (string) $data['greeting'] : 'Not provided',
            filled($data['instruction'] ?? null) ? Str::limit(trim((string) $data['instruction']), 1000, '') : 'Create a complete first draft.'
        ));
    }

    /**
     * @param  Collection<int, KnowledgeBase>  $knowledgeBases
     */
    protected function testPersona(array $data, Collection $knowledgeBases): string
    {
        $name = filled($data['name'] ?? null) ? (string) $data['name'] : 'Support Assistant';
        $knowledgeBaseNames = $knowledgeBases->pluck('name')->filter()->values()->implode(', ');

        $persona = "{$name} is a helpful, concise customer support chatbot powered by platform AI.";

        if ($knowledgeBaseNames !== '') {
            $persona .= " Use these knowledge bases when answering: {$knowledgeBaseNames}.";
        }

        if (filled($data['greeting'] ?? null)) {
            $persona .= ' Start conversations warmly and align with this greeting: '.$this->sentence((string) $data['greeting'], 160).'.';
        }

        if (filled($data['instruction'] ?? null)) {
            $persona .= ' Follow this extra direction: '.$this->sentence((string) $data['instruction'], 180).'.';
        }

        return $persona.' Escalate to a human when the answer is uncertain, sensitive, or outside the available context.';
    }

    protected function sentence(string $value, int $limit): string
    {
        return rtrim(Str::limit(trim($value), $limit, ''), " \t\n\r\0\x0B.!?");
    }
}
