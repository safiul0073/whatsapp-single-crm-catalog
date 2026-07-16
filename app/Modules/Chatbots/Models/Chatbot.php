<?php

namespace App\Modules\Chatbots\Models;

use App\Modules\KnowledgeBases\Models\KnowledgeBase;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Chatbot extends Model
{
    protected $fillable = [
        'workspace_id',
        'name',
        'persona',
        'greeting',
        'temperature',
        'max_tokens',
        'fallback_only_knowledge_base',
        'confidence_threshold',
        'handoff_rules',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'handoff_rules' => 'array',
            'confidence_threshold' => 'decimal:2',
            'temperature' => 'decimal:2',
            'max_tokens' => 'integer',
            'fallback_only_knowledge_base' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function knowledgeBases(): BelongsToMany
    {
        return $this->belongsToMany(KnowledgeBase::class, 'chatbot_knowledge_base')->withTimestamps();
    }
}
