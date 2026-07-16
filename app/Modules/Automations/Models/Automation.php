<?php

namespace App\Modules\Automations\Models;

use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Automation extends Model
{
    protected $fillable = [
        'workspace_id',
        'name',
        'description',
        'trigger',
        'nodes',
        'edges',
        'is_active',
        'runs_count',
        'completed_runs_count',
        'failed_runs_count',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'trigger' => 'array',
            'nodes' => 'array',
            'edges' => 'array',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }

    public function stepLogs(): HasMany
    {
        return $this->hasMany(AutomationStepLog::class);
    }

    public function getCompletionRateAttribute(): int
    {
        if ($this->runs_count < 1) {
            return 0;
        }

        return (int) round(($this->completed_runs_count / $this->runs_count) * 100);
    }

    public function getStepCountAttribute(): int
    {
        return count($this->nodes ?? []);
    }

    public function getTriggerLabelAttribute(): string
    {
        $trigger = $this->trigger ?? [];

        return $trigger['label'] ?? $trigger['data']['detail'] ?? $trigger['config']['event'] ?? $trigger['kind'] ?? $trigger['type'] ?? 'No trigger';
    }
}
