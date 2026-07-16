<?php

namespace App\Modules\Crm\Services;

use App\Models\User;
use App\Modules\Crm\Enums\CrmActivityType;
use App\Modules\Crm\Models\CrmLead;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Workspaces\Enums\WorkspaceMemberStatus;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class LeadAssignmentService
{
    public function __construct(protected CRMLeadService $leads) {}

    public function assign(int $workspaceId, int $leadId, int $userId, ?User $actor = null): CrmLead
    {
        return DB::transaction(function () use ($workspaceId, $leadId, $userId, $actor): CrmLead {
            $this->ensureAssignable($workspaceId, $userId);
            $lead = $this->leads->leadForWorkspace($workspaceId, $leadId, true);
            $lead->update(['assigned_to' => $userId]);
            $lead->contact()->update(['assigned_to' => $userId]);
            if ($lead->conversation_id) {
                Conversation::query()->where('workspace_id', $workspaceId)->whereKey($lead->conversation_id)->update(['assigned_to' => $userId]);
            }
            $this->leads->record($lead, CrmActivityType::Assigned, __('Lead assigned'), null, $actor, ['assigned_to' => $userId]);

            return $lead->fresh(['assignee', 'contact', 'conversation']);
        });
    }

    public function assignConversation(int $workspaceId, int $conversationId, int $userId, ?User $actor = null): ?CrmLead
    {
        $this->ensureAssignable($workspaceId, $userId);
        $conversation = Conversation::query()->where('workspace_id', $workspaceId)->findOrFail($conversationId);
        $conversation->update(['assigned_to' => $userId]);
        $conversation->contact?->update(['assigned_to' => $userId]);
        $lead = CrmLead::query()->where('workspace_id', $workspaceId)->where('conversation_id', $conversation->id)->where('status', 'open')->latest()->first();

        return $lead ? $this->assign($workspaceId, $lead->id, $userId, $actor) : null;
    }

    public function assignableUsers(int $workspaceId): Collection
    {
        $workspace = Workspace::query()->findOrFail($workspaceId);
        $ids = $workspace->activeMembers()->pluck('users.id')->push($workspace->owner_id)->filter()->unique();

        return User::query()->whereIn('id', $ids)->orderBy('first_name')->orderBy('last_name')->get();
    }

    public function ensureAssignable(int $workspaceId, int $userId): User
    {
        $workspace = Workspace::query()->findOrFail($workspaceId);

        return User::query()
            ->whereKey($userId)
            ->where(function ($query) use ($workspace): void {
                $query->whereKey($workspace->owner_id)
                    ->orWhereHas('workspaces', fn ($memberQuery) => $memberQuery
                        ->where('workspaces.id', $workspace->id)
                        ->where('workspace_members.status', WorkspaceMemberStatus::Active->value));
            })
            ->firstOrFail();
    }

    public function defaultAssigneeId(int $workspaceId): int
    {
        return (int) Workspace::query()->findOrFail($workspaceId)->owner_id;
    }
}
