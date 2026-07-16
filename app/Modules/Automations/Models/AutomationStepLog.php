<?php

namespace App\Modules\Automations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationStepLog extends Model
{
    protected $fillable = [
        'automation_run_id',
        'automation_id',
        'node_id',
        'node_type',
        'node_kind',
        'status',
        'selected_port',
        'input',
        'output',
        'error',
        'scheduled_until',
        'started_at',
        'completed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output' => 'array',
            'scheduled_until' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AutomationRun::class, 'automation_run_id');
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }
}
