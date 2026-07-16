<?php

namespace App\Modules\Campaigns\Models;

use App\Modules\Automations\Models\Automation;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Crm\Models\CrmLead;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use App\Modules\Segments\Models\Segment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $fillable = [
        'workspace_id',
        'channel_account_id',
        'provider',
        'message_template_id',
        'automation_id',
        'segment_id',
        'uuid',
        'name',
        'type',
        'status',
        'audience_type',
        'audience_ids',
        'message_type',
        'message_subject',
        'message_body',
        'variables',
        'settings',
        'audience',
        'message_payload',
        'scheduled_at',
        'queued_at',
        'started_at',
        'completed_at',
        'send_rate_per_minute',
        'total_recipients',
        'queued_count',
        'sending_count',
        'sent_count',
        'delivered_count',
        'opened_count',
        'read_count',
        'clicked_count',
        'replied_count',
        'failed_count',
        'skipped_count',
        'skipped_opt_out_count',
        'skipped_invalid_count',
        'skipped_policy_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'audience' => 'array',
            'audience_ids' => 'array',
            'message_payload' => 'array',
            'variables' => 'array',
            'settings' => 'array',
            'scheduled_at' => 'datetime',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function crmLeads(): HasMany
    {
        return $this->hasMany(CrmLead::class);
    }

    public function channelAccount(): BelongsTo
    {
        return $this->belongsTo(ChannelAccount::class);
    }

    public function messageTemplate(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class);
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }
}
