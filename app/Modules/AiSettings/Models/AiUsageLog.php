<?php

namespace App\Modules\AiSettings\Models;

use App\Models\User;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    protected $fillable = [
        'workspace_id',
        'user_id',
        'feature',
        'provider',
        'model',
        'status',
        'duration_ms',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'estimated_cost',
        'request_excerpt',
        'response_excerpt',
        'error_message',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'estimated_cost' => 'decimal:6',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
