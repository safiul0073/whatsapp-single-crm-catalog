<?php

namespace App\Modules\Chatbots\Services;

use App\Models\User;
use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ChatbotService
{
    public function __construct(protected WorkspaceResolver $workspaces) {}

    public function listForUser(?User $user, array $filters = []): LengthAwarePaginator
    {
        $workspace = $this->workspaces->current($user);
        $status = $filters['status'] ?? 'all';
        $search = trim((string) ($filters['q'] ?? ''));

        return Chatbot::query()
            ->where('workspace_id', $workspace->id)
            ->with('knowledgeBases')
            ->withCount('knowledgeBases')
            ->when($status === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($status === 'paused', fn (Builder $query) => $query->where('is_active', false))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('persona', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(12)
            ->withQueryString();
    }

    /**
     * @return array{total: int, active: int, paused: int}
     */
    public function statsForUser(?User $user): array
    {
        $workspace = $this->workspaces->current($user);
        $query = Chatbot::query()->where('workspace_id', $workspace->id);

        return [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('is_active', true)->count(),
            'paused' => (clone $query)->where('is_active', false)->count(),
        ];
    }

    public function create(?User $user, array $data): Chatbot
    {
        $workspace = $this->workspaces->current($user);

        $chatbot = Chatbot::query()->create([
            'workspace_id' => $workspace->id,
            ...$this->payload($data),
        ]);

        $chatbot->knowledgeBases()->sync($data['knowledge_bases'] ?? []);

        return $chatbot->fresh('knowledgeBases');
    }

    public function update(?User $user, Chatbot $chatbot, array $data): Chatbot
    {
        $chatbot = $this->forUser($user, $chatbot);
        $chatbot->update($this->payload($data));
        $chatbot->knowledgeBases()->sync($data['knowledge_bases'] ?? []);

        return $chatbot->fresh('knowledgeBases');
    }

    public function toggle(?User $user, Chatbot $chatbot): Chatbot
    {
        $chatbot = $this->forUser($user, $chatbot);
        $chatbot->update(['is_active' => ! $chatbot->is_active]);

        return $chatbot->fresh();
    }

    public function delete(?User $user, Chatbot $chatbot): void
    {
        $this->forUser($user, $chatbot)->delete();
    }

    public function forUser(?User $user, Chatbot $chatbot): Chatbot
    {
        $workspace = $this->workspaces->current($user);

        abort_unless($chatbot->workspace_id === $workspace->id, 404);

        return $chatbot;
    }

    protected function payload(array $data): array
    {
        return [
            'name' => $data['name'],
            'persona' => trim((string) $data['persona']),
            'greeting' => filled($data['greeting'] ?? null) ? $data['greeting'] : null,
            'temperature' => (float) ($data['temperature'] ?? 0.4),
            'max_tokens' => (int) ($data['max_tokens'] ?? 512),
            'fallback_only_knowledge_base' => (bool) ($data['fallback_only_knowledge_base'] ?? false),
            'confidence_threshold' => (float) ($data['confidence_threshold'] ?? 0.7),
            'handoff_rules' => [
                'on_request' => (bool) ($data['handoff_on_request'] ?? false),
                'on_unsure' => (bool) ($data['handoff_on_unsure'] ?? false),
                'off_hours' => (bool) ($data['handoff_off_hours'] ?? false),
                'message' => $data['handoff_message'] ?? null,
            ],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }
}
