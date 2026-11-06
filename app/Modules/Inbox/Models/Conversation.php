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
    protected $fillable = ['workspace_id', 'channel_account_id', 'provider', 'provider_conversation_id', 'contact_id', 'assigned_to', 'ai_paused_at', 'ai_paused_by', 'ai_pause_reason', 'status', 'last_message_at', 'session_expires_at', 'labels'];

    public const AI_PAUSE_MANUAL = 'manual';

    public const AI_PAUSE_CUSTOMER_REQUEST = 'customer_request';

    public const AI_PAUSE_BOT_UNSURE = 'bot_unsure';

    protected function casts(): array
    {
        return ['status' => ConversationStatus::class, 'labels' => 'array', 'last_message_at' => 'datetime', 'session_expires_at' => 'datetime', 'ai_paused_at' => 'datetime'];
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

    /**
     * Human takeover: the AI chatbot does not answer this conversation until a team member resumes it.
     */
    public function isAiPaused(): bool
    {
        return $this->ai_paused_at !== null;
    }

    public function pauseAi(string $reason, ?int $userId = null): void
    {
        $this->forceFill(['ai_paused_at' => now(), 'ai_paused_by' => $userId, 'ai_pause_reason' => $reason])->save();
    }

    public function resumeAi(): void
    {
        $this->forceFill(['ai_paused_at' => null, 'ai_paused_by' => null, 'ai_pause_reason' => null])->save();
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
