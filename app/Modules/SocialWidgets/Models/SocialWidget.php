<?php

namespace App\Modules\SocialWidgets\Models;

use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\SocialWidgets\Enums\SocialWidgetLayout;
use App\Modules\SocialWidgets\Support\SocialWidgetSettings;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SocialWidget extends Model
{
    protected $fillable = [
        'workspace_id',
        'channel_account_id',
        'provider',
        'name',
        'layout',
        'public_token',
        'settings',
        'status',
        'published_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (SocialWidget $widget): void {
            $widget->public_token ??= Str::random(40);
        });
    }

    protected function casts(): array
    {
        return [
            'layout' => SocialWidgetLayout::class,
            'settings' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function channelAccount(): BelongsTo
    {
        return $this->belongsTo(ChannelAccount::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function mergedSettings(): array
    {
        return SocialWidgetSettings::merge($this->settings);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
