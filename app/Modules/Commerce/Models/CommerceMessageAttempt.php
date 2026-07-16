<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceMessageAttempt extends Model
{
    protected $table = 'commerce_message_attempts';

    protected $fillable = ['workspace_id', 'conversation_id', 'message_id', 'idempotency_key', 'message_type', 'status', 'request_payload', 'last_error'];

    protected function casts(): array
    {
        return ['request_payload' => 'array'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
