<?php

namespace App\Modules\MessageTemplates\Models;

use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MessageTemplates\Enums\MessageTemplateStatus;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageTemplateSubmission extends Model
{
    protected $fillable = [
        'workspace_id',
        'message_template_id',
        'channel_account_id',
        'provider',
        'provider_account_id',
        'whatsapp_template_id',
        'status',
        'submission_payload',
        'meta_response',
        'submitted_at',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MessageTemplateStatus::class,
            'submission_payload' => 'array',
            'meta_response' => 'array',
            'submitted_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'message_template_id');
    }

    public function channelAccount(): BelongsTo
    {
        return $this->belongsTo(ChannelAccount::class);
    }

    public function metaErrorTitle(): ?string
    {
        return data_get($this->meta_response, 'error.error_user_title')
            ?: data_get($this->meta_response, 'error.message');
    }

    public function metaErrorMessage(): ?string
    {
        return data_get($this->meta_response, 'error.error_user_msg')
            ?: data_get($this->meta_response, 'error.error_data.details')
            ?: data_get($this->meta_response, 'error.message');
    }
}
