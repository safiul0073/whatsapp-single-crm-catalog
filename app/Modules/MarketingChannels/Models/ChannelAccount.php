<?php

namespace App\Modules\MarketingChannels\Models;

use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ChannelAccount extends Model
{
    protected $fillable = [
        'workspace_id',
        'provider',
        'name',
        'status',
        'credentials',
        'webhook_verify_token',
        'webhook_code',
        'provider_account_id',
        'provider_phone_id',
        'provider_display_id',
        'settings',
        'connected_at',
        'last_synced_at',
    ];

    protected $hidden = ['credentials', 'webhook_verify_token'];

    protected static function booted(): void
    {
        static::creating(function (ChannelAccount $account): void {
            if (filled($account->webhook_code)) {
                return;
            }

            do {
                $code = Str::random(32);
            } while (static::query()->where('webhook_code', $code)->exists());

            $account->webhook_code = $code;
        });
    }

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'status' => ChannelAccountStatus::class,
            'settings' => 'array',
            'connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(ChannelWebhookEvent::class);
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials ?? [], $key, $default);
    }
}
