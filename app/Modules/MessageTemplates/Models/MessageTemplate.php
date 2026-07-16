<?php

namespace App\Modules\MessageTemplates\Models;

use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MessageTemplates\Enums\MessageTemplateStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageTemplate extends Model
{
    protected $fillable = [
        'workspace_id',
        'provider',
        'channel_account_id',
        'provider_template_id',
        'name',
        'language',
        'category',
        'status',
        'subject',
        'body',
        'components',
        'buttons',
        'variables',
        'rejection_reason',
        'submission_payload',
    ];

    protected function casts(): array
    {
        return [
            'status' => MessageTemplateStatus::class,
            'components' => 'array',
            'buttons' => 'array',
            'variables' => 'array',
            'submission_payload' => 'array',
        ];
    }

    public function channelAccount(): BelongsTo
    {
        return $this->belongsTo(ChannelAccount::class);
    }

    public function isApproved(): bool
    {
        return $this->status === MessageTemplateStatus::Approved;
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(MessageTemplateSubmission::class);
    }

    public function approvedForWaba(string $providerAccountId): bool
    {
        return $this->submissions()
            ->where('provider_account_id', $providerAccountId)
            ->where('status', MessageTemplateStatus::Approved->value)
            ->exists();
    }
}
