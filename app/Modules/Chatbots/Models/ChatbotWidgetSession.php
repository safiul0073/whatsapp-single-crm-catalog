<?php

namespace App\Modules\Chatbots\Models;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotWidgetSession extends Model
{
    protected $fillable = [
        'workspace_id',
        'widget_id',
        'chatbot_id',
        'conversation_id',
        'contact_id',
        'session_token',
        'visitor_uid',
        'visitor_metadata',
        'ip_hash',
        'user_agent_hash',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'visitor_metadata' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function widget(): BelongsTo
    {
        return $this->belongsTo(ChatbotWidget::class, 'widget_id');
    }

    public function chatbot(): BelongsTo
    {
        return $this->belongsTo(Chatbot::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
