<?php

namespace App\Modules\Automations\Http\Requests;

use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Contacts\Models\ContactTag;
use App\Modules\Crm\Enums\CrmTaskPriority;
use App\Modules\Crm\Models\CrmPipeline;
use App\Modules\Crm\Models\CrmStage;
use App\Modules\Crm\Services\LeadAssignmentService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveAutomationRequest extends FormRequest
{
    private const TYPES = ['trigger', 'condition', 'action', 'delay', 'end'];

    private const KINDS = [
        'trigger', 'message_received', 'keyword_matched', 'campaign_replied', 'button_clicked', 'tag_added', 'contact_created', 'no_reply_after_delay', 'conversation_opened', 'template_delivered',
        'condition', 'message_contains', 'contact_has_tag', 'contact_city', 'inside_business_hours', 'reply_matches', 'no_reply_elapsed', 'conversation_assignment',
        'send_message', 'send_template', 'tag_contact', 'webhook', 'ai_assistant', 'assign_agent',
        'send_whatsapp_message', 'send_approved_template', 'add_tag', 'add_contact_tag', 'remove_tag', 'create_lead', 'update_lead_stage', 'create_task', 'assign_conversation', 'mark_lead_won', 'mark_lead_lost', 'call_webhook', 'generate_chatbot_reply', 'generate_ai_reply', 'notify_admin', 'mark_conversation_resolved',
        'wait', 'wait_duration', 'wait_until_time', 'wait_until_business_hour',
        'end', 'customer_replied_yes', 'customer_booked_appointment', 'customer_paid', 'customer_became_lead', 'customer_unsubscribed', 'human_agent_joined',
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'nodes' => ['required', 'json'],
            'edges' => ['required', 'json'],
            'activate' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $nodes = $this->decodedJson('nodes');

        if (! is_array($nodes)) {
            return;
        }

        $this->merge([
            'nodes' => json_encode($this->normalizeNodePorts($nodes)),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $nodes = $this->decodedJson('nodes');
            $edges = $this->decodedJson('edges');

            if (! is_array($nodes)) {
                $validator->errors()->add('nodes', 'The automation nodes are invalid.');

                return;
            }

            if (! is_array($edges)) {
                $validator->errors()->add('edges', 'The automation connections are invalid.');

                return;
            }

            $nodeIds = [];
            $portsByNode = [];
            $hasTrigger = false;
            $hasAction = false;

            foreach ($nodes as $index => $node) {
                if (! is_array($node) || empty($node['id']) || empty($node['type'])) {
                    $validator->errors()->add('nodes', "Node {$index} is missing required data.");

                    continue;
                }

                $nodeId = (string) $node['id'];
                $type = (string) $node['type'];
                $kind = (string) ($node['kind'] ?? $type);
                $nodeIds[] = $nodeId;
                $hasTrigger = $hasTrigger || $type === 'trigger';
                $hasAction = $hasAction || $type === 'action';

                if (! in_array($type, self::TYPES, true)) {
                    $validator->errors()->add('nodes', "Node {$index} uses an unsupported block type.");
                }

                if (! in_array($kind, self::KINDS, true)) {
                    $validator->errors()->add('nodes', "Node {$index} uses an unsupported block kind.");
                }

                $this->validateNodeConfig($validator, $node, $index);

                $portsByNode[$nodeId] = [];

                foreach (($node['ports'] ?? []) as $port) {
                    if (! is_array($port) || empty($port['id']) || empty($port['direction'])) {
                        $validator->errors()->add('nodes', "Node {$index} has an invalid port.");

                        continue;
                    }

                    $portsByNode[$nodeId][(string) $port['id']] = (string) $port['direction'];
                }

                if ($type === 'condition') {
                    foreach (['true', 'false'] as $requiredPort) {
                        if (($portsByNode[$nodeId][$requiredPort] ?? null) !== 'output') {
                            $validator->errors()->add('nodes', "Condition node {$index} must have {$requiredPort} output port.");
                        }
                    }
                }
            }

            $nodeIds = array_unique($nodeIds);
            $pairs = [];
            $targetPorts = [];

            foreach ($edges as $index => $edge) {
                $source = (string) ($edge['sourceNodeId'] ?? $edge['source'] ?? '');
                $sourcePort = (string) ($edge['sourcePortId'] ?? '');
                $target = (string) ($edge['targetNodeId'] ?? $edge['target'] ?? '');
                $targetPort = (string) ($edge['targetPortId'] ?? '');

                if ($source === '' || $sourcePort === '' || $target === '' || $targetPort === '') {
                    $validator->errors()->add('edges', "Connection {$index} is missing a source, target, or port.");

                    continue;
                }

                if ($source === $target) {
                    $validator->errors()->add('edges', 'A node cannot connect to itself.');
                }

                if (! in_array($source, $nodeIds, true) || ! in_array($target, $nodeIds, true)) {
                    $validator->errors()->add('edges', 'Connections must use existing nodes.');
                }

                if (($portsByNode[$source][$sourcePort] ?? null) !== 'output') {
                    $validator->errors()->add('edges', "Connection {$index} must start from an output port.");
                }

                if (($portsByNode[$target][$targetPort] ?? null) !== 'input') {
                    $validator->errors()->add('edges', "Connection {$index} must end at an input port.");
                }

                $pair = "{$source}:{$sourcePort}:{$target}:{$targetPort}";
                if (in_array($pair, $pairs, true)) {
                    $validator->errors()->add('edges', 'Duplicate connections are not allowed.');
                }

                $pairs[] = $pair;

                $targetPair = "{$target}:{$targetPort}";
                if (in_array($targetPair, $targetPorts, true)) {
                    $validator->errors()->add('edges', 'An input port can only have one incoming connection.');
                }

                $targetPorts[] = $targetPair;
            }

            if ($this->boolean('activate') && (! $hasTrigger || ! $hasAction)) {
                $validator->errors()->add('activate', 'Activation requires at least one trigger and one action step.');
            }
        });
    }

    public function decodedJson(string $key): mixed
    {
        $decoded = json_decode((string) $this->input($key), true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    /**
     * @param  array<int, mixed>  $nodes
     * @return array<int, mixed>
     */
    protected function normalizeNodePorts(array $nodes): array
    {
        return array_map(function (mixed $node): mixed {
            if (! is_array($node)) {
                return $node;
            }

            $type = (string) ($node['type'] ?? '');
            $ports = collect($node['ports'] ?? [])
                ->filter(fn (mixed $port): bool => is_array($port) && filled($port['id'] ?? null) && filled($port['direction'] ?? null))
                ->keyBy(fn (array $port): string => (string) $port['id']);

            if ($type !== 'trigger' && ! $ports->has('input')) {
                $ports->put('input', ['id' => 'input', 'label' => 'Input', 'direction' => 'input']);
            }

            if ($type === 'condition') {
                foreach ([
                    'true' => ['label' => data_get($node, 'data.trueLabel', 'Matched'), 'tone' => 'success'],
                    'false' => ['label' => data_get($node, 'data.falseLabel', 'Not matched'), 'tone' => 'error'],
                ] as $id => $defaults) {
                    $ports->put($id, array_merge([
                        'id' => $id,
                        'direction' => 'output',
                    ], $defaults, (array) $ports->get($id, [])));
                }
            }

            $node['ports'] = $ports->values()->all();

            return $node;
        }, $nodes);
    }

    protected function validateNodeConfig(Validator $validator, array $node, int $index): void
    {
        $data = (array) ($node['data'] ?? $node['config'] ?? []);
        $kind = (string) ($node['kind'] ?? $node['type'] ?? '');

        if ($kind === 'keyword_matched' && blank($data['keyword'] ?? $data['value'] ?? null)) {
            $validator->errors()->add('nodes', "Keyword trigger node {$index} requires a keyword.");
        }

        if (($node['type'] ?? null) === 'condition' && blank($data['operator'] ?? null)) {
            $validator->errors()->add('nodes', "Condition node {$index} requires an operator.");
        }

        if (in_array($kind, ['send_whatsapp_message', 'send_message'], true) && blank($data['body'] ?? $data['message'] ?? null)) {
            $validator->errors()->add('nodes', "Send message node {$index} requires a body.");
        }

        if (in_array($kind, ['send_approved_template', 'send_template'], true) && blank($data['template_id'] ?? $data['message_template_id'] ?? $data['template_name'] ?? null)) {
            $validator->errors()->add('nodes', "Send template node {$index} requires a template.");
        }

        if (in_array($kind, ['add_tag', 'add_contact_tag', 'remove_tag', 'tag_contact'], true) && blank($data['tag_id'] ?? $data['tag_name'] ?? $data['value'] ?? null)) {
            $validator->errors()->add('nodes', "Tag node {$index} requires a tag.");
        }

        if (in_array($kind, ['assign_agent', 'assign_conversation'], true) && blank($data['agent_id'] ?? $data['user_id'] ?? null)) {
            $validator->errors()->add('nodes', "Assign agent node {$index} requires a user.");
        }

        $workspace = app(WorkspaceResolver::class)->current($this->user());
        if ($workspace && filled($data['tag_id'] ?? null) && in_array($kind, ['add_tag', 'add_contact_tag', 'remove_tag', 'tag_contact'], true)) {
            if (! ContactTag::query()->where('workspace_id', $workspace->id)->whereKey($data['tag_id'])->exists()) {
                $validator->errors()->add('nodes', "Tag node {$index} must use a tag from this workspace.");
            }
        }

        $agentId = in_array($kind, ['create_lead', 'create_task'], true)
            ? ($data['assigned_to'] ?? null)
            : ($data['agent_id'] ?? $data['user_id'] ?? null);

        if ($workspace && filled($agentId) && in_array($kind, ['assign_agent', 'assign_conversation', 'create_lead', 'create_task'], true)) {
            try {
                app(LeadAssignmentService::class)->ensureAssignable($workspace->id, (int) $agentId);
            } catch (\Throwable) {
                $validator->errors()->add('nodes', "Assignment node {$index} must use an active workspace member.");
            }
        }

        if ($workspace && filled($data['pipeline_id'] ?? null) && in_array($kind, ['create_lead', 'update_lead_stage'], true)) {
            if (! CrmPipeline::query()->where('workspace_id', $workspace->id)->whereKey($data['pipeline_id'])->exists()) {
                $validator->errors()->add('nodes', "CRM node {$index} must use a pipeline from this workspace.");
            }
        }

        if ($workspace && filled($data['stage_id'] ?? null) && in_array($kind, ['create_lead', 'update_lead_stage'], true)) {
            $stage = CrmStage::query()->where('workspace_id', $workspace->id)->find($data['stage_id']);
            if (! $stage || (filled($data['pipeline_id'] ?? null) && (int) $stage->pipeline_id !== (int) $data['pipeline_id'])) {
                $validator->errors()->add('nodes', "CRM node {$index} must use a stage from the selected workspace pipeline.");
            }
        }

        if ($kind === 'update_lead_stage' && blank($data['stage_id'] ?? null)) {
            $validator->errors()->add('nodes', "Move lead stage node {$index} requires a stage.");
        }

        if ($kind === 'create_task') {
            if (blank($data['title'] ?? null)) {
                $validator->errors()->add('nodes', "Create task node {$index} requires a title.");
            }
            if ((int) ($data['due_in_minutes'] ?? 0) < 1 && blank($data['due_at'] ?? null)) {
                $validator->errors()->add('nodes', "Create task node {$index} requires a due time.");
            }
            if (filled($data['priority'] ?? null) && ! in_array($data['priority'], CrmTaskPriority::values(), true)) {
                $validator->errors()->add('nodes', "Create task node {$index} has an invalid priority.");
            }
        }

        if (in_array($kind, ['call_webhook', 'webhook'], true) && blank($data['url'] ?? null)) {
            $validator->errors()->add('nodes', "Webhook node {$index} requires a URL.");
        }

        if (in_array($kind, ['generate_chatbot_reply', 'generate_ai_reply', 'ai_assistant'], true) && filled($data['chatbot_id'] ?? null)) {
            $chatbotValidator = validator([
                'chatbot_id' => $data['chatbot_id'],
            ], [
                'chatbot_id' => [
                    Rule::exists(Chatbot::class, 'id')->where('workspace_id', $workspace->id),
                ],
            ]);

            if ($chatbotValidator->fails()) {
                $validator->errors()->add('nodes', "Chatbot reply node {$index} must use a chatbot from this workspace.");
            }
        }

        if (($node['type'] ?? null) === 'delay' && (int) ($data['value'] ?? $data['duration'] ?? 0) < 1) {
            $validator->errors()->add('nodes', "Delay node {$index} requires a wait duration.");
        }
    }
}
