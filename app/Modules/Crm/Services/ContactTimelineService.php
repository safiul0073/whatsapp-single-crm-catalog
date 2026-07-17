<?php

namespace App\Modules\Crm\Services;

use App\Modules\Campaigns\Models\CampaignRecipient;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Crm\Enums\CrmLeadStatus;
use App\Modules\Crm\Enums\CrmTaskStatus;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\CrmLead;
use App\Modules\Crm\Models\CrmTask;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;

class ContactTimelineService
{
    public function __construct(protected PipelineService $pipelines, protected LeadAssignmentService $assignments) {}

    public function sidebar(int $workspaceId, int $conversationId): array
    {
        $conversation = Conversation::query()
            ->with(['contact.tags', 'assignee'])
            ->where('workspace_id', $workspaceId)
            ->findOrFail($conversationId);
        $contact = $conversation->contact;

        if (! $contact) {
            return ['contact' => null, 'current_lead' => null, 'leads' => [], 'tasks' => [], 'timeline' => [], 'campaign_history' => [], 'pipelines' => [], 'agents' => []];
        }

        $leads = CrmLead::query()
            ->with(['pipeline', 'stage', 'assignee'])
            ->where('workspace_id', $workspaceId)
            ->where('contact_id', $contact->id)
            ->latest('updated_at')
            ->get();
        $currentLead = $leads->first(fn (CrmLead $lead): bool => $lead->status === CrmLeadStatus::Open);
        $tasks = CrmTask::query()
            ->with('assignee')
            ->where('workspace_id', $workspaceId)
            ->where('contact_id', $contact->id)
            ->where('status', CrmTaskStatus::Pending->value)
            ->orderBy('due_at')
            ->get();

        return [
            'contact' => $this->contactPayload($contact),
            'current_lead' => $currentLead ? $this->leadPayload($currentLead) : null,
            'leads' => $leads->map(fn (CrmLead $lead): array => $this->leadPayload($lead))->values()->all(),
            'tasks' => $tasks->map(fn (CrmTask $task): array => $this->taskPayload($task))->values()->all(),
            'timeline' => $this->timeline($workspaceId, $contact->id),
            'campaign_history' => $this->campaignHistory($workspaceId, $contact->id),
            'pipelines' => $this->pipelines->pipelinesForWorkspace($workspaceId)->map(fn ($pipeline): array => [
                'id' => $pipeline->id,
                'name' => $pipeline->name,
                'is_default' => $pipeline->is_default,
                'stages' => $pipeline->stages->map(fn ($stage): array => ['id' => $stage->id, 'name' => $stage->name, 'color' => $stage->color])->all(),
            ])->all(),
            'agents' => $this->assignments->assignableUsers($workspaceId)->map(fn ($user): array => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email])->all(),
        ];
    }

    protected function timeline(int $workspaceId, int $contactId): array
    {
        $activities = CrmActivity::query()
            ->with('creator')
            ->where('workspace_id', $workspaceId)
            ->where('contact_id', $contactId)
            ->latest()
            ->limit(30)
            ->get()
            ->toBase()
            ->map(fn (CrmActivity $activity): array => [
                'id' => 'activity-'.$activity->id,
                'type' => $activity->type->value,
                'title' => $activity->title,
                'description' => $activity->description,
                'actor' => $activity->creator?->name,
                'occurred_at' => $activity->created_at?->toIso8601String(),
            ]);
        $messages = Message::query()
            ->where('workspace_id', $workspaceId)
            ->where('contact_id', $contactId)
            ->latest()
            ->limit(20)
            ->get()
            ->toBase()
            ->map(fn (Message $message): array => [
                'id' => 'message-'.$message->id,
                'type' => 'message_'.$message->direction,
                'title' => $message->direction === 'inbound' ? __('Customer message') : __('Agent message'),
                'description' => $message->body,
                'actor' => null,
                'occurred_at' => $message->created_at?->toIso8601String(),
            ]);

        return $activities->merge($messages)
            ->sortByDesc('occurred_at')
            ->take(30)
            ->values()
            ->all();
    }

    protected function campaignHistory(int $workspaceId, int $contactId): array
    {
        return CampaignRecipient::query()
            ->with('campaign')
            ->where('workspace_id', $workspaceId)
            ->where('contact_id', $contactId)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (CampaignRecipient $recipient): array => [
                'id' => $recipient->id,
                'campaign_id' => $recipient->campaign_id,
                'name' => $recipient->campaign?->name,
                'status' => $recipient->status->value,
                'sent_at' => $recipient->sent_at?->toIso8601String(),
                'replied_at' => $recipient->replied_at?->toIso8601String(),
            ])->all();
    }

    protected function contactPayload(Contact $contact): array
    {
        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'phone' => $contact->phone,
            'email' => $contact->email,
            'city' => $contact->city,
            'country' => $contact->country,
            'source' => $contact->source?->value,
            'tags' => $contact->tags->map(fn ($tag): array => ['id' => $tag->id, 'name' => $tag->name, 'color' => $tag->color])->all(),
        ];
    }

    protected function leadPayload(CrmLead $lead): array
    {
        return [
            'id' => $lead->id,
            'title' => $lead->title,
            'value' => $lead->value,
            'source' => $lead->source->value,
            'status' => $lead->status->value,
            'pipeline_id' => $lead->pipeline_id,
            'pipeline' => $lead->pipeline?->name,
            'stage_id' => $lead->stage_id,
            'stage' => $lead->stage?->name,
            'stage_color' => $lead->stage?->color,
            'assigned_to' => $lead->assigned_to,
            'assignee' => $lead->assignee?->name,
            'next_follow_up_at' => $lead->next_follow_up_at?->toIso8601String(),
            'lost_reason' => $lead->lost_reason,
        ];
    }

    protected function taskPayload(CrmTask $task): array
    {
        return [
            'id' => $task->id,
            'lead_id' => $task->lead_id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status->value,
            'priority' => $task->priority->value,
            'assigned_to' => $task->assigned_to,
            'assignee' => $task->assignee?->name,
            'due_at' => $task->due_at?->toIso8601String(),
            'overdue' => $task->due_at?->isPast() ?? false,
        ];
    }
}
