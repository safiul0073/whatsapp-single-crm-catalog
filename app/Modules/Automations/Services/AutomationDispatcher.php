<?php

namespace App\Modules\Automations\Services;

use App\Modules\Automations\Jobs\RunAutomationStepJob;
use App\Modules\Automations\Models\Automation;
use App\Modules\Automations\Models\AutomationRun;
use App\Modules\PlansSubscriptions\Services\SubscriptionAccessService;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AutomationDispatcher
{
    public function __construct(protected AutomationRunner $runner) {}

    /**
     * @param  array<string, mixed>  $event
     * @return array<int, AutomationRun>
     */
    public function dispatch(array $event): array
    {
        $workspaceId = (int) ($event['workspace_id'] ?? 0);

        if ($workspaceId < 1) {
            return [];
        }

        if (! app(SubscriptionAccessService::class)->canUseServices($workspaceId)) {
            return [];
        }

        $runs = [];

        Automation::query()
            ->where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->get()
            ->each(function (Automation $automation) use ($event, &$runs): void {
                foreach ($this->matchingTriggers($automation, $event) as $trigger) {
                    $runs[] = $this->startRun($automation, $trigger, $event);
                }
            });

        return $runs;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function startAutomation(Automation $automation, array $context, ?string $triggerNodeId = null): AutomationRun
    {
        $trigger = $triggerNodeId
            ? $this->runner->nodeById($automation, $triggerNodeId)
            : $this->firstTrigger($automation);

        app(SubscriptionAccessService::class)->assertActiveForUse((int) $automation->workspace_id);

        return $this->startRun($automation, $trigger, array_merge($context, [
            'workspace_id' => $automation->workspace_id,
            'type' => $context['type'] ?? 'manual',
        ]));
    }

    /**
     * @param  array<string, mixed>|null  $trigger
     * @param  array<string, mixed>  $event
     */
    protected function startRun(Automation $automation, ?array $trigger, array $event): AutomationRun
    {
        $eventKey = $this->eventKey($event);

        if ($eventKey !== null && $trigger) {
            $existing = AutomationRun::query()
                ->where('automation_id', $automation->id)
                ->where('trigger_node_id', $trigger['id'] ?? null)
                ->where('event_key', $eventKey)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $run = AutomationRun::query()->create([
            'workspace_id' => $automation->workspace_id,
            'automation_id' => $automation->id,
            'status' => 'running',
            'trigger_type' => (string) ($event['type'] ?? 'manual'),
            'trigger_node_id' => $trigger['id'] ?? null,
            'event_key' => $eventKey,
            'contact_id' => $event['contact_id'] ?? null,
            'conversation_id' => $event['conversation_id'] ?? null,
            'campaign_id' => $event['campaign_id'] ?? null,
            'campaign_recipient_id' => $event['campaign_recipient_id'] ?? null,
            'message_id' => $event['message_id'] ?? null,
            'context' => $event,
            'started_at' => now(),
        ]);

        $automation->increment('runs_count');
        $automation->forceFill(['last_run_at' => now()])->save();

        $nextNodes = $trigger
            ? $this->runner->nextNodes($automation, (string) $trigger['id'])
            : $this->runner->entryNodes($automation);

        foreach ($nextNodes as $node) {
            RunAutomationStepJob::dispatch($automation->id, $event, $run->id, (string) $node['id']);
        }

        if ($nextNodes === []) {
            $this->runner->completeRun($run, ['reason' => 'No connected automation steps.']);
        }

        return $run;
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<int, array<string, mixed>>
     */
    protected function matchingTriggers(Automation $automation, array $event): array
    {
        return collect($automation->nodes ?? [])
            ->filter(fn (array $node): bool => ($node['type'] ?? null) === 'trigger')
            ->filter(fn (array $node): bool => $this->triggerMatches($node, $event))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $event
     */
    protected function triggerMatches(array $node, array $event): bool
    {
        $kind = $this->triggerKind($node);
        $eventType = (string) ($event['type'] ?? '');

        if ($kind === 'trigger') {
            return $this->genericTriggerMatches($node, $event);
        }

        if ($kind === 'keyword_matched') {
            return $eventType === 'message_received' && $this->keywordMatches($node, $event);
        }

        if ($kind === 'template_delivered') {
            return $eventType === 'message_status' && ($event['status'] ?? null) === 'delivered';
        }

        return $kind === $eventType;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $event
     */
    protected function genericTriggerMatches(array $node, array $event): bool
    {
        $configured = data_get($node, 'data.event')
            ?: data_get($node, 'data.trigger')
            ?: data_get($node, 'config.event');

        if (blank($configured) || $configured === 'any') {
            return true;
        }

        return $configured === ($event['type'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $event
     */
    protected function keywordMatches(array $node, array $event): bool
    {
        $keyword = data_get($node, 'data.keyword')
            ?: data_get($node, 'data.value')
            ?: data_get($node, 'config.keyword');

        if (blank($keyword)) {
            return filled($event['body'] ?? null);
        }

        return Str::contains(Str::lower((string) ($event['body'] ?? '')), Str::lower((string) $keyword));
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function firstTrigger(Automation $automation): ?array
    {
        return Arr::first($automation->nodes ?? [], fn (array $node): bool => ($node['type'] ?? null) === 'trigger');
    }

    /**
     * @param  array<string, mixed>  $node
     */
    protected function triggerKind(array $node): string
    {
        return (string) ($node['kind'] ?? data_get($node, 'data.event') ?? 'trigger');
    }

    /**
     * @param  array<string, mixed>  $event
     */
    protected function eventKey(array $event): ?string
    {
        if (filled($event['event_key'] ?? null)) {
            return (string) $event['event_key'];
        }

        $parts = array_filter([
            $event['type'] ?? null,
            $event['workspace_id'] ?? null,
            $event['message_id'] ?? null,
            $event['campaign_recipient_id'] ?? null,
            $event['contact_id'] ?? null,
            $event['status'] ?? null,
            $event['body'] ?? null,
        ], fn (mixed $value): bool => filled($value));

        return $parts === [] ? null : sha1(json_encode($parts));
    }
}
