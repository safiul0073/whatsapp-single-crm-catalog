<?php

namespace App\Modules\KnowledgeBases\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeBaseSource extends Model
{
    protected $fillable = [
        'knowledge_base_id',
        'type',
        'title',
        'url',
        'file_path',
        'content',
        'status',
        'token_count',
        'chunks_count',
        'checksum',
        'vector_status',
        'vector_error',
        'error',
        'metadata',
        'last_indexed_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'last_indexed_at' => 'datetime',
            'token_count' => 'integer',
            'chunks_count' => 'integer',
        ];
    }

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeBaseChunk::class, 'source_id');
    }
}
