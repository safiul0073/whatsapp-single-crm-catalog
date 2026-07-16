<?php

namespace App\Modules\KnowledgeBases\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeBaseChunk extends Model
{
    protected $fillable = [
        'knowledge_base_id',
        'source_id',
        'content',
        'embedding',
        'vector_id',
        'token_count',
        'position',
        'metadata',
        'score',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'metadata' => 'array',
            'token_count' => 'integer',
            'position' => 'integer',
            'score' => 'float',
        ];
    }

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBaseSource::class, 'source_id');
    }
}
