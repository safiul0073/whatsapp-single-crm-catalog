<?php

namespace App\Modules\Crm\Jobs;

use App\Modules\Crm\Enums\CrmTaskStatus;
use App\Modules\Crm\Models\CrmTask;
use App\Modules\SystemNotifications\Services\SystemNotificationService;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class SendCrmTaskRemindersJob implements ShouldQueue
{
    use Queueable;

    public function handle(SystemNotificationService $notifications): void
    {
        Workspace::query()->select('id')->eachById(function (Workspace $workspace) use ($notifications): void {
            CrmTask::query()
                ->with(['assignee', 'lead'])
                ->where('workspace_id', $workspace->id)
                ->where('status', CrmTaskStatus::Pending->value)
                ->whereNull('reminded_at')
                ->where('due_at', '<=', now())
                ->orderBy('id')
                ->each(function (CrmTask $task) use ($notifications): void {
                    DB::transaction(function () use ($task, $notifications): void {
                        $claimed = CrmTask::query()
                            ->where('workspace_id', $task->workspace_id)
                            ->whereKey($task->id)
                            ->whereNull('reminded_at')
                            ->where('status', CrmTaskStatus::Pending->value)
                            ->update(['reminded_at' => now()]);

                        if ($claimed !== 1 || ! $task->assignee) {
                            return;
                        }

                        $notifications->send($task->assignee, [
                            'title' => __('CRM follow-up due'),
                            'body' => $task->title,
                            'icon' => 'ph-calendar-check',
                            'url' => route('user.crm.index', array_filter(['pipeline' => $task->lead?->pipeline_id])),
                            'type' => 'warning',
                        ], 'crm_task_due');
                    });
                });
        });
    }
}
