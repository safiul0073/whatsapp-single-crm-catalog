<?php

namespace App\Modules\Automations\Services;

use App\Models\User;
use App\Modules\AiSettings\Services\AiUsageLogger;
use App\Modules\Contacts\Models\ContactTag;
use App\Modules\Crm\Services\LeadAssignmentService;
use App\Modules\Crm\Services\PipelineService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Support\Str;
use Laravel\Ai\AnonymousAgent;
use Throwable;

class AutomationFlowGenerator
{
    public function __construct(
        protected AiUsageLogger $usageLogger,
        protected WorkspaceResolver $workspaces,
        protected PipelineService $pipelines,
        protected LeadAssignmentService $assignments,
    ) {}

    /**
     * @return array{name: string, description: string, flow: array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, string>>}, summary: array<int, string>, source: string}
     */
    public function generate(?User $user, string $prompt): array
    {
        $prompt = trim($prompt);
        $crmConfiguration = $this->crmConfiguration($user);

        return $this->fromAiProvider($prompt, $user, $crmConfiguration) ?? $this->fromLocalPlanner($prompt, $crmConfiguration);
    }

    protected function fromAiProvider(string $prompt, ?User $user, array $crmConfiguration): ?array
    {
        if (! $this->hasConfiguredTextProvider()) {
            return null;
        }

        $provider = (string) config('ai.default', 'openai');
        $model = data_get(config("ai.providers.{$provider}", []), 'models.text.default');

        try {
            $agent = new AnonymousAgent(
                instructions: $this->instructions($crmConfiguration),
                messages: [],
                tools: [],
            );

            $response = $this->usageLogger->measure(
                [
                    'feature' => 'automation_flow_generation',
                    'user' => $user,
                    'provider' => $provider,
                    'model' => $model,
                    'request' => $prompt,
                ],
                fn () => $agent->prompt($prompt, timeout: 20),
            );
            $payload = $this->extractJson((string) $response->text);

            if (! is_array($payload)) {
                return null;
            }

            $generated = $this->normalizeGeneratedPayload($payload, 'ai');

            return count($generated['flow']['nodes']) >= 2 ? $generated : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function hasConfiguredTextProvider(): bool
    {
        $provider = (string) config('ai.default', 'openai');
        $config = config("ai.providers.{$provider}", []);

        if (($config['driver'] ?? null) === 'ollama') {
            return filled($config['url'] ?? null);
        }

        return filled($config['key'] ?? null);
    }

    protected function instructions(array $crmConfiguration): string
    {
        $instructions = <<<'PROMPT'
You generate WhatsApp automation flow drafts as strict JSON only.
Return keys: name, description, nodes, edges, summary.
Available node kinds: message_received, keyword_matched, campaign_replied, button_clicked, tag_added, contact_created, no_reply_after_delay, conversation_opened, template_delivered, message_contains, contact_has_tag, contact_city, inside_business_hours, reply_matches, no_reply_elapsed, conversation_assignment, send_whatsapp_message, send_approved_template, add_contact_tag, remove_tag, assign_conversation, create_lead, update_lead_stage, create_task, mark_lead_won, mark_lead_lost, call_webhook, generate_ai_reply, notify_admin, mark_conversation_resolved, wait_duration, wait_until_time, wait_until_business_hour, customer_replied_yes, customer_booked_appointment, customer_paid, customer_became_lead, customer_unsubscribed, human_agent_joined.
Nodes need id, type, kind, label, x, y, data.detail. Edges need sourceNodeId, sourcePortId, targetNodeId, targetPortId.
Keep labels short. Make a practical flow from the user's prompt. Include at least one trigger and one action.
PROMPT;

        return $instructions."\nOnly use IDs from this workspace CRM configuration when an action needs a pipeline_id, stage_id, tag_id, or agent_id:\n".json_encode($crmConfiguration, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array{name: string, description: string, flow: array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, string>>}, summary: array<int, string>, source: string}
     */
    protected function fromLocalPlanner(string $prompt, array $crmConfiguration): array
    {
        $text = Str::lower($prompt);
        $nodes = [];
        $edges = [];

        $triggerKind = Str::contains($text, ['keyword', 'message contains']) ? 'keyword_matched' : 'message_received';
        $nodes[] = $this->node('trigger', $triggerKind, $this->triggerLabel($text), 120, 160, $this->triggerDetail($prompt, $text), [
            'keyword' => $triggerKind === 'keyword_matched' ? 'price' : '',
        ]);

        if (Str::contains($text, ['wait', 'delay', 'after ', 'later', 'hour', 'day', 'minute'])) {
            $nodes[] = $this->node('delay', 'wait_duration', 'Wait', 440, 160, $this->waitDetail($text), [
                'value' => Str::contains($text, ['hour']) ? 1 : 15,
                'unit' => Str::contains($text, ['hour']) ? 'hours' : 'minutes',
            ]);
        }

        if (Str::contains($text, ['question', 'ask', 'collect', 'capture', 'reply with', 'answer'])) {
            $nodes[] = $this->node('action', 'send_whatsapp_message', 'Ask question', 760, 110, 'Ask a focused question and wait for the customer reply.', [
                'body' => 'Can you share a little more detail?',
            ]);
        }

        if (Str::contains($text, ['button', 'buttons', 'option', 'choose', 'quick reply', 'yes or no'])) {
            $nodes[] = $this->node('condition', 'reply_matches', 'Reply matches', 1080, 190, 'Branch the customer based on their reply.', [
                'field' => 'last_reply',
                'operator' => 'equals',
                'value' => 'YES',
                'expression' => '{{last_reply}} = YES',
                'trueLabel' => 'Yes',
                'falseLabel' => 'Other',
            ]);
        } elseif (Str::contains($text, ['template', 'approved'])) {
            $nodes[] = $this->node('action', 'send_approved_template', 'Send template', 760, 220, 'Send an approved WhatsApp template.');
        } else {
            $nodes[] = $this->node('action', 'send_whatsapp_message', 'Send message', 760, 220, $this->messageDetail($prompt), [
                'body' => $this->messageDetail($prompt),
            ]);
        }

        if (Str::contains($text, ['if ', 'condition', 'vip', 'tag', 'segment', 'else', 'qualified'])) {
            $nodes[] = $this->node('condition', 'contact_has_tag', 'Condition', 1080, 190, 'Branch the customer based on their reply or profile.', [
                'field' => Str::contains($text, 'vip') ? 'tag' : 'last_reply',
                'operator' => Str::contains($text, 'vip') ? 'has_tag' : 'contains',
                'value' => Str::contains($text, 'vip') ? 'VIP' : 'yes',
                'expression' => Str::contains($text, 'vip') ? '{{contact.tag}} = VIP' : '{{last_reply}} contains yes',
                'trueLabel' => 'Matched',
                'falseLabel' => 'Not matched',
            ]);
        }

        if (Str::contains($text, ['ai', 'chatgpt', 'smart reply', 'answer automatically', 'bot'])) {
            $nodes[] = $this->node('action', 'generate_ai_reply', 'AI assistant', 1400, 110, 'Draft a helpful AI answer using the workspace knowledge base.');
        }

        if (Str::contains($text, ['agent', 'human', 'handoff', 'support team', 'sales team'])) {
            $nodes[] = $this->node('action', 'assign_conversation', 'Assign agent', 1400, 270, 'Hand the conversation to the right teammate.', [
                'agent_id' => data_get($crmConfiguration, 'agents.0.id'),
            ]);
        }

        if (Str::contains($text, ['lead', 'qualified'])) {
            $nodes[] = $this->node('action', 'create_lead', 'Create lead', 1720, 190, 'Create a lead from this contact.', [
                'pipeline_id' => data_get($crmConfiguration, 'pipelines.0.id'),
                'stage_id' => data_get($crmConfiguration, 'pipelines.0.stages.0.id'),
            ]);
        }

        $nodes[] = $this->node('end', 'customer_became_lead', 'Goal reached', 2040, 190, 'Stop the automation when the journey is complete.');

        foreach (array_values($nodes) as $index => $node) {
            if ($index === 0) {
                continue;
            }

            $previous = $nodes[$index - 1];
            $edges[] = $this->edge($previous, $node);
        }

        return $this->normalizeGeneratedPayload([
            'name' => $this->nameFromPrompt($prompt),
            'description' => Str::limit($prompt, 180, ''),
            'nodes' => $nodes,
            'edges' => $edges,
            'summary' => $this->summaryFor($nodes),
        ], 'guided');
    }

    protected function crmConfiguration(?User $user): array
    {
        if (! $user) {
            return ['pipelines' => [], 'tags' => [], 'agents' => []];
        }

        $workspace = $this->workspaces->current($user);
        $pipelines = $this->pipelines->pipelinesForWorkspace($workspace->id);

        return [
            'pipelines' => $pipelines->map(fn ($pipeline): array => [
                'id' => $pipeline->id,
                'name' => $pipeline->name,
                'stages' => $pipeline->stages->map(fn ($stage): array => ['id' => $stage->id, 'name' => $stage->name])->all(),
            ])->all(),
            'tags' => ContactTag::query()->where('workspace_id', $workspace->id)->orderBy('name')->get(['id', 'name'])->toArray(),
            'agents' => $this->assignments->assignableUsers($workspace->id)->map(fn ($agent): array => ['id' => $agent->id, 'name' => $agent->name])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{name: string, description: string, flow: array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, string>>}, summary: array<int, string>, source: string}
     */
    protected function normalizeGeneratedPayload(array $payload, string $source): array
    {
        $nodes = collect($payload['nodes'] ?? [])
            ->filter(fn ($node): bool => is_array($node))
            ->values()
            ->map(fn (array $node, int $index): array => $this->normalizeNode($node, $index))
            ->all();

        $edges = collect($payload['edges'] ?? [])
            ->filter(fn ($edge): bool => is_array($edge))
            ->values()
            ->map(fn (array $edge, int $index): array => $this->normalizeEdge($edge, $index))
            ->all();

        return [
            'name' => Str::limit((string) ($payload['name'] ?? 'AI automation'), 120, ''),
            'description' => Str::limit((string) ($payload['description'] ?? 'Generated from an AI prompt.'), 500, ''),
            'flow' => [
                'nodes' => $nodes,
                'edges' => $edges,
            ],
            'summary' => array_values(array_filter((array) ($payload['summary'] ?? $this->summaryFor($nodes)))),
            'source' => $source,
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    protected function normalizeNode(array $node, int $index): array
    {
        $kind = (string) ($node['kind'] ?? $node['type'] ?? 'send_message');
        $type = (string) ($node['type'] ?? $this->typeForKind($kind));
        $data = array_merge([
            'detail' => (string) ($node['data']['detail'] ?? $node['detail'] ?? ''),
            'favorite' => false,
            'tone' => $this->toneForKind($kind),
        ], (array) ($node['data'] ?? []));

        return [
            'id' => (string) ($node['id'] ?? 'ai_node_'.($index + 1)),
            'type' => $type,
            'kind' => $kind,
            'label' => (string) ($node['label'] ?? $this->labelForKind($kind)),
            'x' => (int) ($node['x'] ?? (120 + ($index * 320))),
            'y' => (int) ($node['y'] ?? 180),
            'data' => $data,
            'ports' => $this->portsFor($type, $kind, $data),
        ];
    }

    /**
     * @param  array<string, mixed>  $edge
     * @return array<string, string>
     */
    protected function normalizeEdge(array $edge, int $index): array
    {
        return [
            'id' => (string) ($edge['id'] ?? 'ai_edge_'.($index + 1)),
            'sourceNodeId' => (string) ($edge['sourceNodeId'] ?? $edge['source'] ?? ''),
            'sourcePortId' => (string) ($edge['sourcePortId'] ?? 'default'),
            'targetNodeId' => (string) ($edge['targetNodeId'] ?? $edge['target'] ?? ''),
            'targetPortId' => (string) ($edge['targetPortId'] ?? 'input'),
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $target
     * @return array<string, string>
     */
    protected function edge(array $source, array $target): array
    {
        return [
            'id' => 'ai_edge_'.Str::random(6),
            'sourceNodeId' => (string) $source['id'],
            'sourcePortId' => $source['kind'] === 'condition' ? 'true' : 'default',
            'targetNodeId' => (string) $target['id'],
            'targetPortId' => 'input',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    protected function portsFor(string $type, string $kind, array $data): array
    {
        $ports = [];

        if ($type !== 'trigger') {
            $ports[] = ['id' => 'input', 'label' => 'Input', 'direction' => 'input', 'y' => 92];
        }

        if ($kind === 'quick_replies') {
            foreach (($data['options'] ?? ['Yes', 'No']) as $index => $option) {
                $ports[] = ['id' => 'option_'.($index + 1), 'label' => (string) $option, 'direction' => 'output', 'y' => 120 + ($index * 36)];
            }

            return $ports;
        }

        if ($type === 'condition') {
            $ports[] = ['id' => 'true', 'label' => (string) ($data['trueLabel'] ?? 'Matched'), 'direction' => 'output', 'y' => 104, 'tone' => 'success'];
            $ports[] = ['id' => 'false', 'label' => (string) ($data['falseLabel'] ?? 'Not matched'), 'direction' => 'output', 'y' => 140, 'tone' => 'error'];

            return $ports;
        }

        if ($type !== 'end') {
            $ports[] = ['id' => 'default', 'label' => 'Next', 'direction' => 'output', 'y' => 92];
        }

        return $ports;
    }

    protected function node(string $type, string $kind, string $label, int $x, int $y, string $detail, array $data = []): array
    {
        $id = 'ai_'.$kind.'_'.Str::random(5);

        return [
            'id' => $id,
            'type' => $type,
            'kind' => $kind,
            'label' => $label,
            'x' => $x,
            'y' => $y,
            'data' => array_merge([
                'detail' => $detail,
                'favorite' => false,
                'tone' => $this->toneForKind($kind),
            ], $data),
        ];
    }

    protected function typeForKind(string $kind): string
    {
        return match ($kind) {
            'trigger', 'message_received', 'keyword_matched', 'campaign_replied', 'button_clicked', 'tag_added', 'contact_created', 'no_reply_after_delay', 'conversation_opened', 'template_delivered' => 'trigger',
            'condition', 'message_contains', 'contact_has_tag', 'contact_city', 'inside_business_hours', 'reply_matches', 'no_reply_elapsed', 'conversation_assignment' => 'condition',
            'wait', 'wait_duration', 'wait_until_time', 'wait_until_business_hour' => 'delay',
            'end', 'customer_replied_yes', 'customer_booked_appointment', 'customer_paid', 'customer_became_lead', 'customer_unsubscribed', 'human_agent_joined' => 'end',
            default => 'action',
        };
    }

    protected function labelForKind(string $kind): string
    {
        return Str::headline(str_replace('_', ' ', $kind));
    }

    protected function toneForKind(string $kind): string
    {
        return match ($kind) {
            'condition', 'message_contains', 'contact_has_tag', 'contact_city', 'inside_business_hours', 'reply_matches', 'no_reply_elapsed', 'conversation_assignment', 'generate_ai_reply' => 'purple',
            'wait', 'wait_duration', 'wait_until_time', 'wait_until_business_hour' => 'warning',
            'end', 'customer_replied_yes', 'customer_booked_appointment', 'customer_paid', 'customer_became_lead', 'customer_unsubscribed', 'human_agent_joined' => 'error',
            'send_approved_template', 'assign_agent', 'assign_conversation', 'update_lead_stage', 'notify_admin' => 'info',
            'create_task' => 'warning',
            'mark_lead_lost' => 'error',
            default => 'success',
        };
    }

    protected function triggerLabel(string $text): string
    {
        return match (true) {
            Str::contains($text, ['keyword', 'message contains']) => 'Keyword trigger',
            Str::contains($text, ['new contact', 'signup', 'lead']) => 'New lead',
            Str::contains($text, ['order', 'purchase', 'checkout']) => 'Order event',
            default => 'Trigger',
        };
    }

    protected function triggerDetail(string $prompt, string $text): string
    {
        if (Str::contains($text, ['keyword', 'message contains'])) {
            return 'Start when a customer message matches the requested keyword.';
        }

        return 'Start this automation from the customer event described in the prompt.';
    }

    protected function waitDetail(string $text): string
    {
        return match (true) {
            Str::contains($text, ['day', 'tomorrow']) => 'Wait 1 day before the next step.',
            Str::contains($text, ['hour']) => 'Wait 1 hour before the next step.',
            Str::contains($text, ['minute']) => 'Wait 15 minutes before the next step.',
            default => 'Wait before continuing the flow.',
        };
    }

    protected function messageDetail(string $prompt): string
    {
        return 'Send a WhatsApp message that matches this goal: '.Str::limit($prompt, 120, '');
    }

    protected function quickReplyPrompt(string $text): string
    {
        return Str::contains($text, ['yes or no']) ? 'Can we help you with this?' : 'Choose an option';
    }

    /**
     * @return array<int, string>
     */
    protected function quickReplyOptions(string $text): array
    {
        if (Str::contains($text, ['yes or no'])) {
            return ['Yes', 'No'];
        }

        return ['Interested', 'Need help', 'Not now'];
    }

    protected function nameFromPrompt(string $prompt): string
    {
        return Str::limit(Str::headline(Str::words($prompt, 5, '')), 80, '') ?: 'AI automation';
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, string>
     */
    protected function summaryFor(array $nodes): array
    {
        return collect($nodes)
            ->pluck('label')
            ->filter()
            ->map(fn (string $label): string => 'Adds '.$label)
            ->take(5)
            ->values()
            ->all();
    }

    protected function extractJson(string $text): ?array
    {
        $text = trim($text);

        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(?:json)?|```$/m', '', $text) ?: $text;
        }

        $decoded = json_decode(trim($text), true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : null;
    }
}
