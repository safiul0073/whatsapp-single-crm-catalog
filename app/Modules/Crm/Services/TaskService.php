<?php

namespace App\Modules\Crm\Services;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Crm\Enums\CrmActivityType;
use App\Modules\Crm\Enums\CrmTaskPriority;
use App\Modules\Crm\Enums\CrmTaskStatus;
use App\Modules\Crm\Models\CrmLead;
use App\Modules\Crm\Models\CrmTask;
use Illuminate\Support\Facades\DB;

class TaskService
{
    public function __construct(protected CRMLeadService $leads, protected LeadAssignmentService $assignments) {}

    public function create(int $workspaceId, array $data, ?User $actor = null): CrmTask
    {
        return DB::transaction(function () use ($workspaceId, $data, $actor): CrmTask {
            $lead = filled($data['lead_id'] ?? null) ? $this->leads->leadForWorkspace($workspaceId, (int) $data['lead_id']) : null;
            $contactId = $lead?->contact_id ?? (int) ($data['contact_id'] ?? 0);
            $contact = Contact::query()->where('workspace_id', $workspaceId)->findOrFail($contactId);
            $assignedTo = (int) ($data['assigned_to'] ?? $lead?->assigned_to ?? $contact->assigned_to ?? $actor?->id ?? 0);
            if ($assignedTo < 1) {
                $assignedTo = $this->assignments->defaultAssigneeId($workspaceId);
            }
            $this->assignments->ensureAssignable($workspaceId, $assignedTo);

            $task = CrmTask::query()->create([
                'workspace_id' => $workspaceId,
                'lead_id' => $lead?->id,
                'contact_id' => $contact->id,
                'assigned_to' => $assignedTo,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => CrmTaskStatus::Pending->value,
                'priority' => $data['priority'] ?? CrmTaskPriority::Normal->value,
                'due_at' => $data['due_at'],
            ]);

            if ($lead) {
                $this->leads->record($lead, CrmActivityType::TaskCreated, __('Task created'), $task->title, $actor, ['task_id' => $task->id, 'due_at' => $task->due_at?->toIso8601String()]);
                $this->syncNextFollowUp($lead);
            }

            return $task->fresh(['lead', 'contact', 'assignee']);
        });
    }

    public function updateStatus(int $workspaceId, int $taskId, CrmTaskStatus $status, ?User $actor = null): CrmTask
    {
        return DB::transaction(function () use ($workspaceId, $taskId, $status, $actor): CrmTask {
            $task = CrmTask::query()->where('workspace_id', $workspaceId)->findOrFail($taskId);
            $task->update([
                'status' => $status->value,
                'completed_at' => $status === CrmTaskStatus::Completed ? now() : null,
            ]);

            if ($task->lead_id) {
                $lead = $this->leads->leadForWorkspace($workspaceId, $task->lead_id);
                if (in_array($status, [CrmTaskStatus::Completed, CrmTaskStatus::Cancelled], true)) {
                    $activityType = $status === CrmTaskStatus::Completed ? CrmActivityType::TaskCompleted : CrmActivityType::TaskCancelled;
                    $activityTitle = $status === CrmTaskStatus::Completed ? __('Task completed') : __('Task cancelled');
                    $this->leads->record($lead, $activityType, $activityTitle, $task->title, $actor, ['task_id' => $task->id]);
                }
                $this->syncNextFollowUp($lead);
            }

            return $task->fresh(['lead', 'contact', 'assignee']);
        });
    }

    public function syncNextFollowUp(CrmLead $lead): void
    {
        $next = CrmTask::query()
            ->where('workspace_id', $lead->workspace_id)
            ->where('lead_id', $lead->id)
            ->where('status', CrmTaskStatus::Pending->value)
            ->min('due_at');

        $lead->update(['next_follow_up_at' => $next]);
    }
}
