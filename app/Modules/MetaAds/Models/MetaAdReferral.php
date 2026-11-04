<?php

namespace App\Modules\MetaAds\Models;

use App\Modules\Inbox\Models\Conversation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A conversation that started from a click-to-WhatsApp, click-to-Messenger or Instagram ad.
 */
class MetaAdReferral extends Model
{
    protected $fillable = [
        'workspace_id',
        'message_id',
        'conversation_id',
        'contact_id',
        'channel_account_id',
        'provider',
        'ad_id',
        'ctwa_clid',
        'source_type',
        'source_url',
        'headline',
        'body',
        'media_type',
        'referred_at',
    ];

    protected function casts(): array
    {
        return [
            'referred_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
