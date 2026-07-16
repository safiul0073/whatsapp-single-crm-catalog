<?php

namespace App\Modules\Automations\Services;

use App\Modules\Automations\Jobs\RunAutomationStepJob;
use App\Modules\Automations\Models\Automation;
use App\Modules\Automations\Models\AutomationRun;
use App\Modules\Automations\Models\AutomationStepLog;
use App\Modules\Campaigns\Services\TemplateVariableMapper;
use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Chatbots\Services\ClaudeReplyService;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactTag;
use App\Modules\Crm\Enums\CrmLeadSource;
use App\Modules\Crm\Models\CrmLead;
use App\Modules\Crm\Services\CRMLeadService;
use App\Modules\Crm\Services\LeadAssignmentService;
use App\Modules\Crm\Services\TaskService;
use App\Modules\Inbox\Enums\ConversationStatus;
use App\Modules\Inbox\Enums\MessageStatus;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use App\Modules\PlansSubscriptions\Services\SubscriptionAccessService;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class AutomationRunner
{
    public function __construct(
        protected ChannelManager $channels,
        protected TemplateVariableMapper $variables,
        protected CRMLeadService $crmLeads,
        protected TaskService $crmTasks,
        protected LeadAssignmentService $leadAssignments,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function execute(int $automationId, int $runId, string $nodeId, array $context = []): void
    {
        $workspaceId = (int) ($context['workspace_id'] ?? 0);

        if ($workspaceId < 1) {
            return;
        }

        $automation = Automation::query()->where('workspace_id', $workspaceId)->find($automationId);
        $run = AutomationRun::query()
            ->where('workspace_id', $workspaceId)
            ->where('automation_id', $automationId)
            ->find($runId);

        if (! $automation || ! $run || $run->status !== 'running') {
            return;
        }

        if (! app(SubscriptionAccessService::class)->canUseServices((int) $automation->workspace_id)) {
            $this->failRun($run, 'subscription_expired');

            return;
        }

        $node = $this->nodeById($automation, $nodeId);

        if (! $node) {
            $this->failRun($run, "Automation node [{$nodeId}] was not found.");

            return;
        }

        $log = AutomationStepLog::query()->create([
            'automation_run_id' => $run->id,
            'automation_id' => $automation->id,
            'node_id' => $nodeId,
            'node_type' => $node['type'] ?? null,
            'node_kind' => $node['kind'] ?? null,
            'status' => 'running',
            'input' => $context,
            'started_at' => now(),
        ]);

        try {
            $result = $this->executeNode($automation, $run, $node, $context);
            $selectedPort = $result['port'] ?? 'default';

            $log->update([
                'status' => $result['status'] ?? 'completed',
                'selected_port' => $selectedPort,
                'output' => $result,
                'scheduled_until' => $result['scheduled_until'] ?? null,
                'completed_at' => ($result['status'] ?? 'completed') === 'scheduled' ? null : now(),
            ]);

            if (($result['terminal'] ?? false) === true) {
                $this->completeRun($run, $result);

                return;
            }

            if (($result['status'] ?? null) === 'scheduled') {
                return;
            }

            $nextNodes = $this->nextNodes($automation, $nodeId, (string) $selectedPort);

            foreach ($nextNodes as $nextNode) {
                RunAutomationStepJob::dispatch($automation->id, array_merge($context, $result['context'] ?? []), $run->id, (string) $nextNode['id']);
            }

            if ($nextNodes === []) {
                $this->completeRun($run, ['reason' => 'Flow reached the end of a branch.']);
            }
        } catch (Throwable $exception) {
            $log->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'failed_at' => now(),
            ]);

            $this->failRun($run, $exception->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function executeNode(Automation $automation, AutomationRun $run, array $node, array $context): array
    {
        return match ($node['type'] ?? 'action') {
            'condition' => $this->executeCondition($node, $context),
            'delay' => $this->executeDelay($automation, $run, $node, $context),
            'end' => $this->executeEnd($node, $context),
            default => $this->executeAction($automation, $node, $context),
        };
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function executeAction(Automation $automation, array $node, array $context): array
    {
        return match ($node['kind'] ?? 'send_message') {
            'send_whatsapp_message', 'send_message' => $this->sendText($automation, $node, $context),
            'send_approved_template', 'send_template' => $this->sendTemplate($automation, $node, $context),
            'add_tag', 'add_contact_tag', 'tag_contact' => $this->syncTag($automation, $node, $context, true),
            'remove_tag' => $this->syncTag($automation, $node, $context, false),
            'assign_agent', 'assign_conversation' => $this->assignAgent($automation, $node, $context),
            'create_lead' => $this->createLead($automation, $node, $context),
            'update_lead_stage' => $this->updateLeadStage($automation, $node, $context),
            'create_task' => $this->createTask($automation, $node, $context),
            'mark_lead_won' => $this->markLead($automation, $node, $context, true),
            'mark_lead_lost' => $this->markLead($automation, $node, $context, false),
            'call_webhook', 'webhook' => $this->callWebhook($node, $context),
            'generate_chatbot_reply', 'generate_ai_reply', 'ai_assistant' => $this->generateChatbotReply($automation, $node, $context),
            'notify_admin' => $this->notifyAdmin($node, $context),
            'mark_conversation_resolved' => $this->markResolved($context),
            default => throw new \RuntimeException('Unsupported automation action ['.($node['kind'] ?? 'unknown').'].'),
        };
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function executeCondition(array $node, array $context): array
    {
        $matched = $this->conditionMatches($node, $context);

        return [
            'status' => 'completed',
            'port' => $matched ? 'true' : 'false',
            'matched' => $matched,
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function executeDelay(Automation $automation, AutomationRun $run, array $node, array $context): array
    {
        $scheduledAt = now()->addSeconds($this->delaySeconds($node));

        foreach ($this->nextNodes($automation, (string) $node['id']) as $nextNode) {
            RunAutomationStepJob::dispatch($automation->id, $context, $run->id, (string) $nextNode['id'])->delay($scheduledAt);
        }

        return [
            'status' => 'scheduled',
            'port' => 'default',
            'scheduled_until' => $scheduledAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function executeEnd(array $node, array $context): array
    {
        return [
            'status' => 'completed',
            'terminal' => true,
            'reason' => data_get($node, 'data.reason') ?: data_get($node, 'data.detail') ?: 'Goal reached.',
            'matched' => $this->goalMatches($node, $context),
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function sendText(Automation $automation, array $node, array $context): array
    {
        $body = trim($this->renderText((string) (data_get($node, 'data.body') ?: data_get($node, 'data.message') ?: data_get($node, 'data.detail')), $context));

        if ($body === '') {
            throw new \RuntimeException('Send message action requires a message body.');
        }

        return $this->sendProviderMessage($automation, $context, ['type' => 'text', 'body' => $body]);
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function sendTemplate(Automation $automation, array $node, array $context): array
    {
        $template = $this->templateFor($automation, $node);

        if (! $template) {
            throw new \RuntimeException('Send template action requires an approved template.');
        }

        return $this->sendProviderMessage($automation, $context, [
            'type' => 'template',
            'template_name' => $template->name,
            'language' => $template->language ?? 'en_US',
            'components' => data_get($node, 'data.components', []),
            'meta_payload' => [
                'messaging_product' => 'whatsapp',
                'to' => preg_replace('/\D+/', '', (string) $this->recipientFor($context)),
                'type' => 'template',
                'template' => array_filter([
                    'name' => $template->name,
                    'language' => ['code' => $template->language ?? 'en_US'],
                    'components' => data_get($node, 'data.components', []),
                ], fn (mixed $value): bool => $value !== []),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function sendProviderMessage(Automation $automation, array $context, array $payload): array
    {
        $account = $this->channelAccountFor($automation->workspace_id, $context);
        $recipient = $this->recipientFor($context);

        if (! $account || blank($recipient)) {
            throw new \RuntimeException('A connected channel and recipient are required to send an automation message.');
        }

        $result = $this->channels->sendMessage($account, ['to' => $recipient], $payload);
        $conversation = $this->conversationFor($context);
        $contact = $this->contactFor($context);

        Message::query()->create([
            'workspace_id' => $automation->workspace_id,
            'channel_account_id' => $account->id,
            'provider' => $account->provider,
            'conversation_id' => $conversation?->id,
            'contact_id' => $contact?->id,
            'direction' => 'outbound',
            'type' => $payload['type'] ?? 'text',
            'body' => $payload['body'] ?? null,
            'payload' => array_merge($payload, ['response' => $result['response'] ?? null]),
            'status' => ($result['status'] ?? null) ?: (($result['ok'] ?? false) ? MessageStatus::Sent->value : MessageStatus::Failed->value),
            'provider_message_id' => $result['provider_message_id'] ?? null,
            'whatsapp_message_id' => $account->provider === 'whatsapp' ? ($result['provider_message_id'] ?? null) : null,
        ]);

        if (! ($result['ok'] ?? false)) {
            throw new \RuntimeException($result['error'] ?? data_get($result, 'response.error.message', 'Automation message failed.'));
        }

        return [
            'status' => 'completed',
            'port' => 'default',
            'provider_message_id' => $result['provider_message_id'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function syncTag(Automation $automation, array $node, array $context, bool $attach): array
    {
        $contact = $this->contactFor($context);

        if (! $contact) {
            throw new \RuntimeException('Tag action requires a contact.');
        }

        $tag = $this->tagFor($automation->workspace_id, $node);

        if (! $tag) {
            throw new \RuntimeException('Tag action requires a tag.');
        }

        $attach
            ? $contact->tags()->syncWithoutDetaching([$tag->id])
            : $contact->tags()->detach($tag->id);

        return ['status' => 'completed', 'port' => 'default', 'tag_id' => $tag->id];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function assignAgent(Automation $automation, array $node, array $context): array
    {
        $agentId = (int) (data_get($node, 'data.agent_id') ?: data_get($node, 'data.user_id'));
        $conversation = $this->conversationFor($context);
        $contact = $this->contactFor($context);
        $lead = null;

        if ($agentId < 1) {
            throw new \RuntimeException('Assign agent action requires a valid user.');
        }

        $this->leadAssignments->ensureAssignable((int) $automation->workspace_id, $agentId);
        if ($conversation) {
            $lead = $this->leadAssignments->assignConversation((int) $automation->workspace_id, $conversation->id, $agentId);
        } elseif ($lead = $this->crmLeadFor($automation, $node, $context)) {
            $lead = $this->leadAssignments->assign((int) $automation->workspace_id, $lead->id, $agentId);
        } else {
            $contact?->update(['assigned_to' => $agentId]);
        }

        return ['status' => 'completed', 'port' => 'default', 'assigned_to' => $agentId, 'lead_id' => $lead?->id];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function createLead(Automation $automation, array $node, array $context): array
    {
        $contact = $this->contactFor($context);

        if (! $contact) {
            throw new \RuntimeException('Create lead action requires a contact.');
        }

        $lead = $this->crmLeads->createOrUpdate((int) $automation->workspace_id, $contact->id, [
            'conversation_id' => $this->conversationFor($context)?->id,
            'pipeline_id' => data_get($node, 'data.pipeline_id'),
            'stage_id' => data_get($node, 'data.stage_id'),
            'title' => data_get($node, 'data.title'),
            'value' => data_get($node, 'data.value'),
            'source' => CrmLeadSource::Automation->value,
            'assigned_to' => data_get($node, 'data.assigned_to'),
        ]);

        if (filled(data_get($node, 'data.notes'))) {
            $this->crmLeads->addNote((int) $automation->workspace_id, $lead->id, (string) data_get($node, 'data.notes'));
        }

        return ['status' => 'completed', 'port' => 'default', 'lead_id' => $lead->id, 'context' => ['crm_lead_id' => $lead->id]];
    }

    protected function updateLeadStage(Automation $automation, array $node, array $context): array
    {
        $lead = $this->crmLeadFor($automation, $node, $context);
        $stageId = (int) data_get($node, 'data.stage_id');
        if (! $lead || $stageId < 1) {
            throw new \RuntimeException('Update lead stage requires an open CRM lead and stage.');
        }

        $lead = $this->crmLeads->moveStage((int) $automation->workspace_id, $lead->id, $stageId);

        return ['status' => 'completed', 'port' => 'default', 'lead_id' => $lead->id, 'stage_id' => $lead->stage_id];
    }

    protected function createTask(Automation $automation, array $node, array $context): array
    {
        $contact = $this->contactFor($context);
        if (! $contact) {
            throw new \RuntimeException('Create task requires a contact.');
        }

        $lead = $this->crmLeadFor($automation, $node, $context);
        $dueAt = filled(data_get($node, 'data.due_at'))
            ? Carbon::parse((string) data_get($node, 'data.due_at'))
            : now()->addMinutes(max(1, (int) data_get($node, 'data.due_in_minutes', 60)));
        $task = $this->crmTasks->create((int) $automation->workspace_id, [
            'lead_id' => $lead?->id,
            'contact_id' => $contact->id,
            'assigned_to' => data_get($node, 'data.assigned_to') ?: $lead?->assigned_to,
            'title' => data_get($node, 'data.title', 'Follow up'),
            'description' => data_get($node, 'data.description'),
            'priority' => data_get($node, 'data.priority', 'normal'),
            'due_at' => $dueAt,
        ]);

        return ['status' => 'completed', 'port' => 'default', 'task_id' => $task->id, 'lead_id' => $lead?->id];
    }

    protected function markLead(Automation $automation, array $node, array $context, bool $won): array
    {
        $lead = $this->crmLeadFor($automation, $node, $context);
        if (! $lead) {
            throw new \RuntimeException('Lead status action requires an open CRM lead.');
        }

        $lead = $won
            ? $this->crmLeads->markWon((int) $automation->workspace_id, $lead->id)
            : $this->crmLeads->markLost((int) $automation->workspace_id, $lead->id, data_get($node, 'data.lost_reason'));

        return ['status' => 'completed', 'port' => 'default', 'lead_id' => $lead->id, 'lead_status' => $lead->status->value];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function callWebhook(array $node, array $context): array
    {
        $url = (string) data_get($node, 'data.url');

        if (! Str::startsWith($url, ['https://', 'http://'])) {
            throw new \RuntimeException('Webhook action requires a valid URL.');
        }

        $response = Http::timeout(10)->post($url, [
            'context' => $context,
            'node' => Arr::only($node, ['id', 'type', 'kind', 'label']),
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Webhook action returned HTTP '.$response->status().'.');
        }

        return [
            'status' => 'completed',
            'port' => 'default',
            'http_status' => $response->status(),
            'response' => $response->json() ?? $response->body(),
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function generateChatbotReply(Automation $automation, array $node, array $context): array
    {
        if (! class_exists(ClaudeReplyService::class)) {
            throw new \RuntimeException('AI reply service is not available.');
        }

        $chatbot = $this->chatbotFor($automation, $node);

        if (! $chatbot) {
            throw new \RuntimeException('Chatbot reply action requires an active workspace chatbot.');
        }

        $message = trim($this->renderText((string) ($context['body'] ?? data_get($node, 'data.prompt', '')), $context));
        $draft = app(ClaudeReplyService::class)->draftReply($message, array_merge($context, [
            'chatbot' => $chatbot,
            'automation_id' => $automation->id,
            'automation_node_id' => $node['id'] ?? null,
        ]));
        $reply = (string) ($draft['reply'] ?? '');

        if ($reply === '') {
            throw new \RuntimeException('Chatbot did not return a message.');
        }

        $result = $this->sendProviderMessage($automation, $context, [
            'type' => 'text',
            'body' => $reply,
            'chatbot_id' => $chatbot->id,
            'chatbot_reply' => [
                'provider' => $draft['provider'] ?? null,
                'model' => $draft['model'] ?? null,
                'confidence' => $draft['confidence'] ?? null,
                'handoff' => $draft['handoff'] ?? false,
                'knowledge_context_count' => data_get($draft, 'context.knowledge_context_count', 0),
                'search_mode' => data_get($draft, 'context.search_mode'),
                'sources_used' => data_get($draft, 'context.sources_used', []),
            ],
        ]);

        return array_merge($result, [
            'chatbot_id' => $chatbot->id,
            'chatbot_name' => $chatbot->name,
            'provider' => $draft['provider'] ?? null,
            'model' => $draft['model'] ?? null,
            'confidence' => $draft['confidence'] ?? null,
            'handoff' => $draft['handoff'] ?? false,
            'search_mode' => data_get($draft, 'context.search_mode'),
            'knowledge_context_count' => data_get($draft, 'context.knowledge_context_count', 0),
            'sources_used' => data_get($draft, 'context.sources_used', []),
        ]);
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function notifyAdmin(array $node, array $context): array
    {
        return [
            'status' => 'completed',
            'port' => 'default',
            'notification' => $this->renderText((string) (data_get($node, 'data.message') ?: data_get($node, 'data.detail')), $context),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function markResolved(array $context): array
    {
        $conversation = $this->conversationFor($context);
        $conversation?->update(['status' => ConversationStatus::Resolved->value]);

        return ['status' => 'completed', 'port' => 'default', 'conversation_id' => $conversation?->id];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $context
     */
    protected function conditionMatches(array $node, array $context): bool
    {
        $field = (string) (data_get($node, 'data.field') ?: data_get($node, 'data.condition') ?: 'message_body');
        $operator = (string) (data_get($node, 'data.operator') ?: 'contains');
        $expected = data_get($node, 'data.value') ?: data_get($node, 'data.expected');
        $actual = $this->contextValue($field, $context);

        return match ($operator) {
            'equals', '=' => Str::lower((string) $actual) === Str::lower((string) $expected),
            'not_equals', '!=' => Str::lower((string) $actual) !== Str::lower((string) $expected),
            'has_tag' => $this->contactHasTag($context, (string) $expected),
            'inside_business_hours' => (int) now()->format('G') >= 9 && (int) now()->format('G') < 17,
            'not_replied_for' => $this->notRepliedFor($context, (int) $expected),
            'is_empty' => blank($actual),
            'is_not_empty' => filled($actual),
            default => Str::contains(Str::lower((string) $actual), Str::lower((string) $expected)),
        };
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $context
     */
    protected function goalMatches(array $node, array $context): bool
    {
        $goal = (string) ($node['kind'] ?? data_get($node, 'data.goal') ?? 'end');

        return match ($goal) {
            'customer_replied_yes', 'replied_yes' => Str::contains(Str::lower((string) ($context['body'] ?? '')), 'yes'),
            'customer_became_lead', 'became_lead' => Lead::query()->where('contact_id', $context['contact_id'] ?? 0)->exists(),
            'customer_unsubscribed', 'unsubscribed' => $this->contactFor($context)?->isOptedOut() ?? false,
            'human_agent_joined' => filled($this->conversationFor($context)?->assigned_to),
            default => true,
        };
    }

    /**
     * @param  array<string, mixed>  $node
     */
    protected function delaySeconds(array $node): int
    {
        $unit = (string) data_get($node, 'data.unit', 'minutes');
        $value = max(1, (int) (data_get($node, 'data.value') ?: data_get($node, 'data.duration') ?: 5));

        return match ($unit) {
            'seconds' => $value,
            'hours' => $value * 3600,
            'days' => $value * 86400,
            default => $value * 60,
        };
    }

    public function completeRun(AutomationRun $run, array $result = []): void
    {
        if ($run->status !== 'running') {
            return;
        }

        $run->update([
            'status' => 'completed',
            'result' => $result,
            'completed_at' => now(),
        ]);

        $run->automation?->increment('completed_runs_count');
    }

    public function failRun(AutomationRun $run, string $error): void
    {
        if ($run->status !== 'running') {
            return;
        }

        $run->update([
            'status' => 'failed',
            'error' => $error,
            'failed_at' => now(),
        ]);

        $run->automation?->increment('failed_runs_count');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function entryNodes(Automation $automation): array
    {
        return collect($automation->nodes ?? [])
            ->filter(fn (array $node): bool => ($node['type'] ?? null) !== 'trigger')
            ->filter(fn (array $node): bool => ! $this->hasIncomingEdge($automation, (string) ($node['id'] ?? '')))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function nextNodes(Automation $automation, string $nodeId, string $sourcePort = 'default'): array
    {
        $targetIds = collect($automation->edges ?? [])
            ->filter(fn (array $edge): bool => ($edge['sourceNodeId'] ?? null) === $nodeId && ($edge['sourcePortId'] ?? 'default') === $sourcePort)
            ->pluck('targetNodeId')
            ->filter()
            ->all();

        if ($targetIds === [] && $sourcePort !== 'default') {
            $targetIds = collect($automation->edges ?? [])
                ->filter(fn (array $edge): bool => ($edge['sourceNodeId'] ?? null) === $nodeId && ($edge['sourcePortId'] ?? null) === 'default')
                ->pluck('targetNodeId')
                ->filter()
                ->all();
        }

        return collect($automation->nodes ?? [])
            ->filter(fn (array $node): bool => in_array($node['id'] ?? null, $targetIds, true))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function nodeById(Automation $automation, string $nodeId): ?array
    {
        return Arr::first($automation->nodes ?? [], fn (array $node): bool => ($node['id'] ?? null) === $nodeId);
    }

    protected function hasIncomingEdge(Automation $automation, string $nodeId): bool
    {
        return collect($automation->edges ?? [])->contains(fn (array $edge): bool => ($edge['targetNodeId'] ?? null) === $nodeId);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function contactFor(array $context): ?Contact
    {
        return isset($context['contact_id'], $context['workspace_id'])
            ? Contact::query()->where('workspace_id', $context['workspace_id'])->find($context['contact_id'])
            : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function conversationFor(array $context): ?Conversation
    {
        return isset($context['conversation_id'], $context['workspace_id'])
            ? Conversation::query()->where('workspace_id', $context['workspace_id'])->find($context['conversation_id'])
            : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function recipientFor(array $context): ?string
    {
        return $context['recipient'] ?? $this->contactFor($context)?->phone;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function channelAccountFor(int $workspaceId, array $context): ?ChannelAccount
    {
        if (isset($context['channel_account_id'])) {
            $account = ChannelAccount::query()->where('workspace_id', $workspaceId)->find($context['channel_account_id']);

            if ($account) {
                return $account;
            }
        }

        $conversation = $this->conversationFor($context);

        if ($conversation?->channelAccount) {
            return $conversation->channelAccount;
        }

        return ChannelAccount::query()
            ->where('workspace_id', $workspaceId)
            ->where('provider', $context['provider'] ?? 'whatsapp')
            ->where('status', ChannelAccountStatus::Connected->value)
            ->latest('connected_at')
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $node
     */
    protected function templateFor(Automation $automation, array $node): ?MessageTemplate
    {
        $templateId = data_get($node, 'data.template_id') ?: data_get($node, 'data.message_template_id');

        return MessageTemplate::query()
            ->where('workspace_id', $automation->workspace_id)
            ->where('provider', 'whatsapp')
            ->when($templateId, fn ($query) => $query->whereKey($templateId))
            ->when(! $templateId, fn ($query) => $query->where('name', data_get($node, 'data.template_name')))
            ->first();
    }

    /**
     * @param  array<string, mixed>  $node
     */
    protected function tagFor(int $workspaceId, array $node): ?ContactTag
    {
        $tagId = data_get($node, 'data.tag_id');
        $tagName = data_get($node, 'data.tag_name') ?: data_get($node, 'data.value');

        if ($tagId) {
            return ContactTag::query()->where('workspace_id', $workspaceId)->find($tagId);
        }

        if (blank($tagName)) {
            return null;
        }

        return ContactTag::query()->firstOrCreate(
            ['workspace_id' => $workspaceId, 'slug' => Str::slug((string) $tagName)],
            ['name' => (string) $tagName, 'color' => '#22c55e']
        );
    }

    protected function crmLeadFor(Automation $automation, array $node, array $context): ?CrmLead
    {
        $leadId = $context['crm_lead_id'] ?? data_get($node, 'data.lead_id');

        return CrmLead::query()
            ->where('workspace_id', $automation->workspace_id)
            ->where('status', 'open')
            ->when($leadId, fn ($query) => $query->whereKey($leadId))
            ->when(! $leadId, fn ($query) => $query
                ->where('contact_id', $context['contact_id'] ?? 0)
                ->when(data_get($node, 'data.pipeline_id'), fn ($pipelineQuery) => $pipelineQuery->where('pipeline_id', data_get($node, 'data.pipeline_id')))
                ->latest('updated_at'))
            ->first();
    }

    /**
     * @param  array<string, mixed>  $node
     */
    protected function chatbotFor(Automation $automation, array $node): ?Chatbot
    {
        $chatbotId = data_get($node, 'data.chatbot_id') ?: data_get($node, 'data.chatbot');

        return Chatbot::query()
            ->where('workspace_id', $automation->workspace_id)
            ->where('is_active', true)
            ->when($chatbotId, fn ($query) => $query->whereKey($chatbotId))
            ->with('knowledgeBases')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function renderText(string $text, array $context): string
    {
        $contact = $this->contactFor($context);

        if (! $contact) {
            return $text;
        }

        return $this->variables->map($text, $contact, []);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function contextValue(string $field, array $context): mixed
    {
        return match ($field) {
            'message', 'message_body', 'last_reply', 'reply' => $context['body'] ?? null,
            'city' => $this->contactFor($context)?->city,
            'country' => $this->contactFor($context)?->country,
            'assigned_to' => $this->conversationFor($context)?->assigned_to,
            'conversation_status' => $this->conversationFor($context)?->status?->value,
            default => data_get($context, $field),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function contactHasTag(array $context, string $tag): bool
    {
        $contact = $this->contactFor($context);

        if (! $contact || blank($tag)) {
            return false;
        }

        return $contact->tags()->where(function ($query) use ($tag): void {
            $query->where('contact_tags.id', $tag)
                ->orWhere('contact_tags.name', $tag)
                ->orWhere('contact_tags.slug', Str::slug($tag));
        })->exists();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function notRepliedFor(array $context, int $minutes): bool
    {
        $conversation = $this->conversationFor($context);

        if (! $conversation) {
            return false;
        }

        $lastInbound = Message::query()
            ->where('workspace_id', $conversation->workspace_id)
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'inbound')
            ->latest('id')
            ->first();

        return $lastInbound === null || $lastInbound->created_at?->lte(now()->subMinutes(max(1, $minutes)));
    }
}
