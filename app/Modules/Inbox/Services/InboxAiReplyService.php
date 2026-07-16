<?php

namespace App\Modules\Inbox\Services;

use App\Modules\AiSettings\Services\AiUsageLogger;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\AnonymousAgent;
use Throwable;

class InboxAiReplyService
{
    public function __construct(protected AiUsageLogger $usageLogger) {}

    /**
     * @return array{reply: string, provider: string, model: ?string}
     */
    public function draft(Conversation $conversation, ?string $instruction = null): array
    {
        $provider = $this->platformTextProvider();
        $model = $this->platformTextModel($provider);

        if (! $this->hasConfiguredTextProvider($provider)) {
            throw ValidationException::withMessages([
                'ai' => __('Platform AI is not configured for text replies. Enable a text provider in AI Settings first.'),
            ]);
        }

        if (app()->runningUnitTests()) {
            return [
                'reply' => $this->testDraft($conversation, $instruction),
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
            $prompt = $this->prompt($conversation, $instruction);

            $response = $this->usageLogger->measure(
                [
                    'feature' => 'inbox_ai_reply',
                    'workspace_id' => $conversation->workspace_id,
                    'user_id' => auth()->id(),
                    'provider' => $provider,
                    'model' => $model,
                    'request' => $prompt,
                    'metadata' => [
                        'conversation_id' => $conversation->id,
                    ],
                ],
                fn () => $agent->prompt(
                    prompt: $prompt,
                    provider: $provider,
                    model: $model,
                    timeout: 20,
                ),
            );

            $reply = trim((string) $response->text);

            if ($reply === '') {
                throw ValidationException::withMessages([
                    'ai' => __('AI could not generate a reply. Please try again.'),
                ]);
            }

            return [
                'reply' => Str::limit($reply, 4000, ''),
                'provider' => $provider,
                'model' => $model,
            ];
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Inbox AI reply generation failed.', [
                'conversation_id' => $conversation->id,
                'provider' => $provider,
                'model' => $model,
                'exception' => $exception::class,
                'message' => Str::limit($exception->getMessage(), 220, ''),
            ]);

            throw ValidationException::withMessages([
                'ai' => __('AI could not generate a reply. Check the platform AI settings and try again.'),
            ]);
        }
    }

    protected function hasConfiguredTextProvider(string $provider): bool
    {
        $config = config("ai.providers.{$provider}", []);

        if (($config['driver'] ?? null) === 'ollama') {
            return filled($config['url'] ?? null);
        }

        return filled($config['key'] ?? null);
    }

    protected function platformTextProvider(): string
    {
        $provider = filled(ai_setting('ai_default_text_provider'))
            ? (string) ai_setting('ai_default_text_provider')
            : (string) config('ai.default', 'openai');

        return match ($provider) {
            'azure-openai' => 'azure',
            'elevenlabs' => 'eleven',
            default => $provider,
        };
    }

    protected function platformTextModel(string $provider): ?string
    {
        if (filled(ai_setting('ai_default_text_model'))) {
            return (string) ai_setting('ai_default_text_model');
        }

        return match ($provider) {
            'anthropic' => 'claude-sonnet-4-20250514',
            'gemini' => 'gemini-3-flash',
            'groq' => 'llama-3.3-70b-versatile',
            'xai' => 'grok-3-mini',
            'deepseek' => 'deepseek-chat',
            'mistral' => 'mistral-large-latest',
            'ollama' => 'llama3.1',
            'openrouter' => 'openai/gpt-4o-mini',
            default => 'gpt-4o',
        };
    }

    protected function instructions(): string
    {
        return <<<'PROMPT'
You draft concise customer support replies for an inbox agent.
Use the conversation context only. Do not invent order numbers, policy promises, prices, or private data.
Write in the same language as the customer when clear.
Return only the message body the agent can review and send.
PROMPT;
    }

    protected function prompt(Conversation $conversation, ?string $instruction): string
    {
        $conversation->loadMissing(['contact', 'channelAccount']);
        $messages = $this->recentMessages($conversation)
            ->map(fn (Message $message): string => sprintf(
                '[%s] %s: %s',
                optional($message->created_at)->format('Y-m-d H:i'),
                $message->direction === 'inbound' ? 'Customer' : 'Agent',
                Str::limit(trim((string) $message->body), 1000, '')
            ))
            ->implode(PHP_EOL);

        $extraInstruction = filled($instruction)
            ? PHP_EOL.'Agent instruction: '.Str::limit(trim((string) $instruction), 500, '')
            : '';

        return trim(sprintf(
            "Contact: %s\nChannel: %s\nConversation:\n%s%s",
            $conversation->contact?->name ?: $conversation->contact?->phone ?: 'Unknown contact',
            $this->providerLabel((string) $conversation->provider),
            $messages !== '' ? $messages : 'No messages yet.',
            $extraInstruction
        ));
    }

    /**
     * @return Collection<int, Message>
     */
    protected function recentMessages(Conversation $conversation): Collection
    {
        return Message::query()
            ->where('workspace_id', $conversation->workspace_id)
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->reverse()
            ->values();
    }

    protected function testDraft(Conversation $conversation, ?string $instruction): string
    {
        $latestInbound = Message::query()
            ->where('workspace_id', $conversation->workspace_id)
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'inbound')
            ->latest('id')
            ->value('body');

        $reply = 'AI draft reply for '.($conversation->contact?->name ?: 'this customer');

        if (filled($latestInbound)) {
            $reply .= ': '.Str::limit((string) $latestInbound, 120, '');
        }

        if (filled($instruction)) {
            $reply .= ' (Instruction: '.Str::limit(trim((string) $instruction), 120, '').')';
        }

        return $reply;
    }

    protected function providerLabel(string $provider): string
    {
        return match ($provider) {
            'website_widget' => 'Website widget',
            default => Str::headline($provider),
        };
    }
}
