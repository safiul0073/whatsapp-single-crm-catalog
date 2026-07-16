<?php

namespace App\Modules\Contacts\Models;

use App\Modules\MarketingChannels\Models\ChannelAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactProviderIdentity extends Model
{
    protected $fillable = [
        'workspace_id',
        'contact_id',
        'channel_account_id',
        'provider',
        'provider_contact_id',
        'address',
        'username',
        'identity_type',
        'status',
        'metadata',
        'last_interaction_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'last_interaction_at' => 'datetime',
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
}
