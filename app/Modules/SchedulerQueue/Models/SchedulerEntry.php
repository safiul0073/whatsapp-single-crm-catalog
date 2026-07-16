<?php

namespace App\Modules\SchedulerQueue\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class SchedulerEntry extends Model
{
    protected $fillable = [
        'key',
        'label',
        'type',
        'target',
        'frequency',
        'queue',
        'enabled',
        'last_run_at',
        'last_finished_at',
        'last_status',
        'last_message',
        'options',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_run_at' => 'datetime',
            'last_finished_at' => 'datetime',
            'options' => 'array',
        ];
    }

    public function markStarted(): void
    {
        $this->forceFill([
            'last_run_at' => now(),
            'last_status' => 'running',
            'last_message' => null,
        ])->save();
    }

    public function markFinished(string $status, ?string $message = null): void
    {
        $this->forceFill([
            'last_finished_at' => now(),
            'last_status' => $status,
            'last_message' => $message,
        ])->save();
    }

    public function nextRunAt(?Carbon $from = null): ?Carbon
    {
        $from ??= $this->last_run_at;

        if (! $from) {
            return null;
        }

        return match ($this->frequency) {
            'every_minute' => $from->copy()->addMinute(),
            'every_five_minutes' => $from->copy()->addMinutes(5),
            'every_fifteen_minutes' => $from->copy()->addMinutes(15),
            'every_thirty_minutes' => $from->copy()->addMinutes(30),
            'hourly' => $from->copy()->addHour(),
            'daily' => $from->copy()->addDay(),
            default => null,
        };
    }
}
