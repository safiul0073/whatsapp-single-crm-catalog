<?php

namespace App\Modules\NotificationTemplates\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DeviceToken extends Model
{
    protected $table = 'device_tokens';

    protected $fillable = [
        'tokenable_type',
        'tokenable_id',
        'token',
        'platform',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * The owning tokenable model (Admin or User).
     */
    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope: filter by platform.
     */
    public function scopeForPlatform(Builder $query, string $platform): Builder
    {
        return $query->where('platform', $platform);
    }
}
