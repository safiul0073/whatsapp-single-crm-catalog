<?php

namespace App\Modules\Automations\Models;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\CampaignRecipient;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationRun extends Model
{
    protected $fillable = [
        'workspace_id',
        'automation_id',
        'status',
        'trigger_type',
        'trigger_node_id',
        'event_key',
        'contact_id',
        'conversation_id',
        'campaign_id',
        'campaign_recipient_id',
        'message_id',
        'context',
        'result',
        'error',
        'started_at',
        'completed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'result' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function campaignRecipient(): BelongsTo
    {
        return $this->belongsTo(CampaignRecipient::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function stepLogs(): HasMany
    {
        return $this->hasMany(AutomationStepLog::class);
    }
}
