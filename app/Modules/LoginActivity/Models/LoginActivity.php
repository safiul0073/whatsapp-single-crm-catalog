<?php

namespace App\Modules\LoginActivity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LoginActivity extends Model
{
    public $timestamps = false;

    protected $table = 'login_activities';

    protected $fillable = [
        'user_type',
        'user_id',
        'event',
        'ip_address',
        'user_agent',
        'device',
        'browser',
        'platform',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): MorphTo
    {
        return $this->morphTo('user', 'user_type', 'user_id');
    }

    public function scopeForUser($query, string $userType, int $userId)
    {
        return $query->where('user_type', $userType)->where('user_id', $userId);
    }

    public function scopeRecent($query, int $limit = 10)
    {
        return $query->orderByDesc('created_at')->limit($limit);
    }

    /**
     * Get the badge variant for the event type.
     */
    public function getEventBadgeVariant(): string
    {
        return match ($this->event) {
            'login' => 'success',
            'logout' => 'info',
            'failed' => 'danger',
            'lockout' => 'warning',
            'impersonate_start' => 'warning',
            'impersonate_stop' => 'info',
            default => 'default',
        };
    }
}
