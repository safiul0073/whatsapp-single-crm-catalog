<?php

namespace App\Modules\Crm\Enums;

enum CrmActivityType: string
{
    case LeadCreated = 'lead_created';
    case StageChanged = 'stage_changed';
    case Assigned = 'assigned';
    case Note = 'note';
    case TaskCreated = 'task_created';
    case TaskCompleted = 'task_completed';
    case TaskCancelled = 'task_cancelled';
    case CampaignReply = 'campaign_reply';
    case Won = 'won';
    case Lost = 'lost';
}
