<?php

namespace App\Modules\NotificationTemplates\Models;

use App\Enums\NotificationTemplateSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotificationLog extends Model
{
    protected $table = 'notification_logs';

    protected $fillable = [
        'template_slug',
        'channel',
        'notifiable_type',
        'notifiable_id',
        'status',
        'metadata',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * The notifiable entity (Admin or User).
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The notification template this log belongs to.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_slug', 'slug');
    }

    /**
     * Scope: filter by channel.
     */
    public function scopeByChannel(Builder $query, string $channel): Builder
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope: filter by status.
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: filter by notification template slug (type-safe).
     */
    public function scopeByTemplateSlug(Builder $query, NotificationTemplateSlug $slug): Builder
    {
        return $query->where('template_slug', $slug->value);
    }

    /**
     * Scope: only failed notifications.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope: recent logs.
     */
    public function scopeRecent(Builder $query, int $limit = 50): Builder
    {
        return $query->latest()->limit($limit);
    }
}
