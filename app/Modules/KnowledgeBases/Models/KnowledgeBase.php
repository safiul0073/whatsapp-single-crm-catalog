<?php

namespace App\Modules\KnowledgeBases\Models;

use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeBase extends Model
{
    protected $fillable = [
        'workspace_id',
        'name',
        'description',
        'status',
        'visibility',
        'settings',
        'sources_count',
        'chunks_count',
        'last_indexed_at',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'sources_count' => 'integer',
            'chunks_count' => 'integer',
            'last_indexed_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(KnowledgeBaseSource::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeBaseChunk::class);
    }

    public function chatbots(): BelongsToMany
    {
        return $this->belongsToMany(Chatbot::class, 'chatbot_knowledge_base')->withTimestamps();
    }
}
