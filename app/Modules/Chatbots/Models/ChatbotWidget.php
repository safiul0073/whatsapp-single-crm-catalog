<?php

namespace App\Modules\Chatbots\Models;

use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatbotWidget extends Model
{
    protected $fillable = [
        'workspace_id',
        'chatbot_id',
        'name',
        'public_token',
        'is_active',
        'allowed_domains',
        'lead_fields',
        'settings',
        'greeting',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'allowed_domains' => 'array',
            'lead_fields' => 'array',
            'settings' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function chatbot(): BelongsTo
    {
        return $this->belongsTo(Chatbot::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ChatbotWidgetSession::class, 'widget_id');
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings ?? [], $key, $default);
    }

    public function automatedReplyEnabled(): bool
    {
        return (bool) $this->setting('automated_reply_enabled', true);
    }
}
