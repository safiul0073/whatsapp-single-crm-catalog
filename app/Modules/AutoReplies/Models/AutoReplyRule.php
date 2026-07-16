<?php

namespace App\Modules\AutoReplies\Models;

use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoReplyRule extends Model
{
    protected $fillable = [
        'workspace_id',
        'name',
        'trigger_type',
        'trigger_value',
        'match_type',
        'reply_type',
        'reply_text',
        'reply_payload',
        'priority',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'reply_payload' => 'array',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function getTriggerLabelAttribute(): string
    {
        return match ($this->trigger_type) {
            'welcome' => 'Welcome',
            'out_of_hours' => 'Out of hours',
            'fallback' => 'Fallback',
            default => 'Keyword',
        };
    }

    public function getReplyTypeLabelAttribute(): string
    {
        return str($this->reply_type)->replace('_', ' ')->headline()->toString();
    }
}
