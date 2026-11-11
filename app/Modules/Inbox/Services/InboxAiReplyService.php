<?php

namespace App\Modules\Inbox\Services;

use App\Modules\AiSettings\Services\AiTextGenerator;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class InboxAiReplyService
{
    public function __construct(protected AiTextGenerator $textGenerator) {}

    public function isAvailable(): bool
    {
        return $this->configuredProvider() !== null;
    }

    public function configuredProvider(): ?string
    {
        return $this->textGenerator->primaryProvider();
    }

    /**
     * @return array{reply: string, provider: string, model: ?string}
     */
    public function draft(Conversation $conversation, ?string $instruction = null): array
    {
        $provider = $this->configuredProvider();

        if ($provider === null) {
            throw ValidationException::withMessages([
                'ai' => __('Platform AI is not configured for text replies. Enable a text provider in AI Settings first.'),
            ]);
        }

        if (app()->runningUnitTests()) {
            return [
                'reply' => $this->testDraft($conversation, $instruction),
                'provider' => $provider,
                'model' => $this->textGenerator->primaryModel(),
            ];
        }

        $model = null;

        try {
            $result = $this->textGenerator->prompt(
                feature: 'inbox_ai_reply',
                instructions: $this->instructions(),
                prompt: $this->prompt($conversation, $instruction),
                context: [
                    'workspace_id' => $conversation->workspace_id,
                    'user_id' => auth()->id(),
                    'metadata' => [
                        'conversation_id' => $conversation->id,
                    ],
                ],
            );
            $provider = $result['provider'];
            $model = $result['model'];

            $reply = trim((string) $result['response']->text);

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
