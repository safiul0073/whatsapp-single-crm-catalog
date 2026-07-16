<?php

namespace App\Modules\Inbox\Models;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\CrmLead;
use App\Modules\Inbox\Enums\ConversationStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = ['workspace_id', 'channel_account_id', 'provider', 'provider_conversation_id', 'contact_id', 'assigned_to', 'status', 'last_message_at', 'session_expires_at', 'labels'];

    protected function casts(): array
    {
        return ['status' => ConversationStatus::class, 'labels' => 'array', 'last_message_at' => 'datetime', 'session_expires_at' => 'datetime'];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function channelAccount(): BelongsTo
    {
        return $this->belongsTo(ChannelAccount::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function crmLeads(): HasMany
    {
        return $this->hasMany(CrmLead::class);
    }

    public function crmActivities(): HasMany
    {
        return $this->hasMany(CrmActivity::class);
    }
}
