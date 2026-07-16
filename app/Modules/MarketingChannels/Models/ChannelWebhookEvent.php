<?php

namespace App\Modules\MarketingChannels\Models;

use App\Modules\MarketingChannels\Enums\ChannelWebhookEventStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelWebhookEvent extends Model
{
    protected $fillable = [
        'channel_account_id',
        'workspace_id',
        'provider',
        'event_type',
        'provider_event_id',
        'payload_hash',
        'payload',
        'headers',
        'processed_at',
        'failed_at',
        'status',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'headers' => 'array',
            'status' => ChannelWebhookEventStatus::class,
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function channelAccount(): BelongsTo
    {
        return $this->belongsTo(ChannelAccount::class);
    }
}
