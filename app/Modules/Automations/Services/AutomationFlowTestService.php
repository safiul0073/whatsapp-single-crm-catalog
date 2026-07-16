<?php

namespace App\Modules\Automations\Services;

use App\Models\User;
use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Chatbots\Services\ClaudeReplyService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AutomationFlowTestService
{
    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected ClaudeReplyService $replies,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<string, mixed>
     */
    public function test(?User $user, array $nodes, array $edges, string $message): array
    {
        $workspace = $this->workspaces->current($user);
        $context = [
            'workspace_id' => $workspace->id,
            'type' => 'test_run',
            'body' => $message,
            'provider' => 'test',
        ];

        $current = $this->firstNode($nodes);
        $steps = [];
        $completed = false;

        for ($guard = 0; $current && $guard < 30; $guard++) {
            $result = $this->executeNode((int) $workspace->id, $current, $context);
            $steps[] = [
                'node_id' => $current['id'] ?? null,
                'label' => $current['label'] ?? Str::headline((string) ($current['kind'] ?? $current['type'] ?? 'step')),
                'type' => $current['type'] ?? null,
                'kind' => $current['kind'] ?? null,
                ...$result,
            ];

            if (($current['type'] ?? null) === 'end' || ($result['terminal'] ?? false)) {
                $completed = true;
                break;
            }

            $next = $this->nextNode($nodes, $edges, (string) ($current['id'] ?? ''), (string) ($result['port'] ?? 'default'));

            if (! $next) {
                $completed = true;
                break;
            }

            $current = $next;
        }

        return [
            'status' => $completed ? 'completed' : 'stopped',
            'completed' => $completed,
            'message' => $completed ? 'Test flow completed.' : 'Test flow stopped before reaching an end.',
            'input' => $context,
            'steps' => $steps,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     */
    protected function firstNode(array $nodes): ?array
    {
        return Arr::first($nodes, fn (array $node): bool => ($node['type'] ?? null) === 'trigger')
            ?: Arr::first($nodes);
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     */
    protected function nextNode(array $nodes, array $edges, string $nodeId, string $port): ?array
    {
        $edge = Arr::first($edges, fn (array $edge): bool => ($edge['sourceNodeId'] ?? null) === $nodeId && ($edge['sourcePortId'] ?? 'default') === $port)
            ?: Arr::first($edges, fn (array $edge): bool => ($edge['sourceNodeId'] ?? null) === $nodeId && ($edge['sourcePortId'] ?? null) === 'default');

        if (! $edge) {
            return null;
        }

        return Arr::first($nodes, fn (array $node): bool => ($node['id'] ?? null) === ($edge['targetNodeId'] ?? null));
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function executeNode(int $workspaceId, array $node, array $context): array
    {
        return match ($node['type'] ?? 'action') {
            'trigger' => [
                'status' => 'completed',
                'port' => 'default',
                'summary' => 'Trigger matched for the sample message.',
                'output' => ['event' => $node['kind'] ?? data_get($node, 'data.event')],
            ],
            'condition' => $this->conditionResult($node, $context),
            'delay' => [
                'status' => 'skipped',
                'port' => 'default',
                'summary' => 'Delay skipped during test run.',
                'output' => ['duration' => data_get($node, 'data.value'), 'unit' => data_get($node, 'data.unit')],
            ],
            'end' => [
                'status' => 'completed',
                'port' => 'default',
                'terminal' => true,
                'summary' => data_get($node, 'data.reason') ?: 'End reached.',
                'output' => [],
            ],
            default => $this->actionResult($workspaceId, $node, $context),
        };
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function conditionResult(array $node, array $context): array
    {
        $operator = (string) (data_get($node, 'data.operator') ?: 'contains');
        $expected = (string) data_get($node, 'data.value', '');
        $actual = (string) ($context['body'] ?? '');

        $matched = match ($operator) {
            'equals', '=' => Str::lower($actual) === Str::lower($expected),
            'not_equals', '!=' => Str::lower($actual) !== Str::lower($expected),
            'is_empty' => blank($actual),
            'is_not_empty' => filled($actual),
            'inside_business_hours' => true,
            default => Str::contains(Str::lower($actual), Str::lower($expected)),
        };

        return [
            'status' => 'completed',
            'port' => $matched ? 'true' : 'false',
            'summary' => $matched ? 'Condition matched.' : 'Condition did not match.',
            'output' => ['actual' => $actual, 'operator' => $operator, 'expected' => $expected, 'matched' => $matched],
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function actionResult(int $workspaceId, array $node, array $context): array
    {
        $kind = (string) ($node['kind'] ?? 'send_message');

        if (in_array($kind, ['generate_chatbot_reply', 'generate_ai_reply', 'ai_assistant'], true)) {
            return $this->chatbotResult($workspaceId, $node, $context);
        }

        $summary = match ($kind) {
            'send_whatsapp_message', 'send_message' => 'Would send message: '.Str::limit((string) data_get($node, 'data.body', data_get($node, 'data.message', '')), 120),
            'send_approved_template', 'send_template' => 'Would send approved template.',
            'add_tag', 'tag_contact' => 'Would add contact tag.',
            'remove_tag' => 'Would remove contact tag.',
            'assign_agent' => 'Would assign the conversation.',
            'create_lead' => 'Would create or update a lead.',
            'call_webhook', 'webhook' => 'Would call webhook: '.data_get($node, 'data.url'),
            'notify_admin' => 'Would create an internal notification.',
            'mark_conversation_resolved' => 'Would mark the conversation resolved.',
            default => 'Would run action.',
        };

        return [
            'status' => 'completed',
            'port' => 'default',
            'summary' => $summary,
            'output' => ['dry_run' => true],
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function chatbotResult(int $workspaceId, array $node, array $context): array
    {
        $chatbotId = data_get($node, 'data.chatbot_id');
        $chatbot = Chatbot::query()
            ->where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->when($chatbotId, fn ($query) => $query->whereKey($chatbotId))
            ->with('knowledgeBases')
            ->first();

        if (! $chatbot) {
            return [
                'status' => 'failed',
                'port' => 'default',
                'summary' => 'No active workspace chatbot was found for this step.',
                'output' => [],
            ];
        }

        $draft = $this->replies->draftReply((string) ($context['body'] ?? ''), array_merge($context, ['chatbot' => $chatbot]));

        return [
            'status' => 'completed',
            'port' => 'default',
            'summary' => 'Chatbot preview: '.Str::limit((string) ($draft['reply'] ?? ''), 160),
            'output' => [
                'chatbot_id' => $chatbot->id,
                'chatbot_name' => $chatbot->name,
                'provider' => $draft['provider'] ?? null,
                'model' => $draft['model'] ?? null,
                'handoff' => $draft['handoff'] ?? false,
                'reply' => $draft['reply'] ?? null,
                'search_mode' => data_get($draft, 'context.search_mode'),
                'knowledge_context_count' => data_get($draft, 'context.knowledge_context_count', 0),
            ],
        ];
    }
}
