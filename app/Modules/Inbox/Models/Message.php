<?php

namespace App\Modules\Inbox\Models;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Enums\MessageStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = ['workspace_id', 'channel_account_id', 'provider', 'conversation_id', 'contact_id', 'direction', 'type', 'body', 'payload', 'status', 'provider_message_id', 'campaign_id', 'whatsapp_message_id'];

    protected function casts(): array
    {
        return ['status' => MessageStatus::class, 'payload' => 'array'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
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
