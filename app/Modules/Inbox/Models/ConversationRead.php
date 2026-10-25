<?php

namespace App\Modules\Inbox\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-user read marker: the newest inbound message a user has seen in a conversation.
 */
class ConversationRead extends Model
{
    protected $table = 'inbox_conversation_reads';

    protected $fillable = ['workspace_id', 'user_id', 'conversation_id', 'last_read_message_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
