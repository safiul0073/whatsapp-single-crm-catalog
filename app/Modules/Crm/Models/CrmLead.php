<?php

namespace App\Modules\Crm\Models;

use App\Models\User;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Crm\Enums\CrmLeadSource;
use App\Modules\Crm\Enums\CrmLeadStatus;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmLead extends Model
{
    use HasFactory;

    protected $fillable = ['workspace_id', 'contact_id', 'conversation_id', 'campaign_id', 'pipeline_id', 'stage_id', 'title', 'value', 'source', 'status', 'assigned_to', 'next_follow_up_at', 'won_at', 'lost_at', 'lost_reason'];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'source' => CrmLeadSource::class,
            'status' => CrmLeadStatus::class,
            'next_follow_up_at' => 'datetime',
            'won_at' => 'datetime',
            'lost_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
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

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(CrmPipeline::class, 'pipeline_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmStage::class, 'stage_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'lead_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CrmTask::class, 'lead_id');
    }
}
