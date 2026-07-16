<?php

namespace App\Modules\Campaigns\Models;

use App\Modules\Campaigns\Enums\CampaignRecipientStatus;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactProviderIdentity;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignRecipient extends Model
{
    protected $fillable = [
        'workspace_id',
        'campaign_id',
        'contact_id',
        'contact_channel_id',
        'channel_account_id',
        'provider',
        'uuid',
        'to',
        'recipient_address',
        'status',
        'provider_message_id',
        'payload',
        'error_code',
        'error_message',
        'queued_at',
        'sending_at',
        'sent_at',
        'delivered_at',
        'opened_at',
        'read_at',
        'clicked_at',
        'replied_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => CampaignRecipientStatus::class,
            'queued_at' => 'datetime',
            'sending_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'opened_at' => 'datetime',
            'read_at' => 'datetime',
            'clicked_at' => 'datetime',
            'replied_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function contactChannel(): BelongsTo
    {
        return $this->belongsTo(ContactProviderIdentity::class, 'contact_channel_id');
    }

    public function channelAccount(): BelongsTo
    {
        return $this->belongsTo(ChannelAccount::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            CampaignRecipientStatus::Delivered,
            CampaignRecipientStatus::Read,
            CampaignRecipientStatus::Replied,
            CampaignRecipientStatus::Failed,
            CampaignRecipientStatus::SkippedOptOut,
            CampaignRecipientStatus::SkippedBlocked,
            CampaignRecipientStatus::SkippedInvalidPhone,
            CampaignRecipientStatus::SkippedInvalid,
            CampaignRecipientStatus::SkippedPolicy,
        ], true);
    }
}
