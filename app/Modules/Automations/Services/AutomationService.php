<?php

namespace App\Modules\Automations\Services;

use App\Models\User;
use App\Modules\Automations\Models\Automation;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AutomationService
{
    public function __construct(protected WorkspaceResolver $workspaces) {}

    public function listForUser(?User $user): LengthAwarePaginator
    {
        $workspace = $this->workspaces->current($user);

        return Automation::query()
            ->where('workspace_id', $workspace->id)
            ->latest()
            ->paginate(12);
    }

    public function statsForUser(?User $user): array
    {
        $workspace = $this->workspaces->current($user);
        $query = Automation::query()->where('workspace_id', $workspace->id);

        return [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('is_active', true)->count(),
            'runs' => (clone $query)->sum('runs_count'),
            'messages' => (clone $query)->sum('completed_runs_count'),
            'completion' => $this->completionRate(
                (clone $query)->sum('runs_count'),
                (clone $query)->sum('completed_runs_count'),
            ),
        ];
    }

    public function create(?User $user, array $data): Automation
    {
        $workspace = $this->workspaces->current($user);
        $payload = $this->payload($data);

        return Automation::query()->create([
            'workspace_id' => $workspace->id,
            ...$payload,
        ]);
    }

    public function update(?User $user, Automation $automation, array $data): Automation
    {
        $automation = $this->forUser($user, $automation);
        $automation->update($this->payload($data));

        return $automation->fresh();
    }

    public function toggle(?User $user, Automation $automation): Automation
    {
        $automation = $this->forUser($user, $automation);
        $automation->update(['is_active' => ! $automation->is_active]);

        return $automation->fresh();
    }

    public function delete(?User $user, Automation $automation): void
    {
        $this->forUser($user, $automation)->delete();
    }

    public function forUser(?User $user, Automation $automation): Automation
    {
        $workspace = $this->workspaces->current($user);

        abort_unless($automation->workspace_id === $workspace->id, 404);

        return $automation;
    }

    public function blankFlow(): array
    {
        return [
            'nodes' => [],
            'edges' => [],
        ];
    }

    protected function payload(array $data): array
    {
        $nodes = $data['nodes'];
        $edges = $data['edges'];

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'trigger' => $this->firstTrigger($nodes),
            'nodes' => $nodes,
            'edges' => $edges,
            'is_active' => (bool) ($data['activate'] ?? false),
        ];
    }

    protected function firstTrigger(array $nodes): ?array
    {
        foreach ($nodes as $node) {
            if (($node['type'] ?? null) === 'trigger') {
                return [
                    'id' => $node['id'] ?? null,
                    'type' => 'trigger',
                    'kind' => $node['kind'] ?? 'trigger',
                    'label' => $node['label'] ?? 'Trigger',
                    'config' => $node['data'] ?? ($node['config'] ?? []),
                ];
            }
        }

        return null;
    }

    protected function completionRate(int $runs, int $completed): int
    {
        if ($runs < 1) {
            return 0;
        }

        return (int) round(($completed / $runs) * 100);
    }
}
