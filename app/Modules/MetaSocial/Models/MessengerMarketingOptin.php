<?php

namespace App\Modules\MetaSocial\Models;

use App\Modules\Contacts\Models\Contact;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Messenger customer's consent to receive marketing messages from a Page. Meta issues one
 * notification_messages_token per person and topic; broadcasts are sent to that token, never the PSID.
 */
class MessengerMarketingOptin extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_STOPPED = 'stopped';

    protected $fillable = [
        'workspace_id',
        'channel_account_id',
        'contact_id',
        'psid',
        'topic',
        'token',
        'frequency',
        'status',
        'opted_in_at',
        'expires_at',
        'stopped_at',
        'requested_at',
    ];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'opted_in_at' => 'datetime',
            'expires_at' => 'datetime',
            'stopped_at' => 'datetime',
            'requested_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function channelAccount(): BelongsTo
    {
        return $this->belongsTo(ChannelAccount::class);
    }

    /**
     * Active opt-ins whose token has not expired.
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereNotNull('token')
            ->where(fn (Builder $query): Builder => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && filled($this->token)
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
