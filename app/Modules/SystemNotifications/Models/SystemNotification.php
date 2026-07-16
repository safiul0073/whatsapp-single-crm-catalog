<?php

namespace App\Modules\SystemNotifications\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SystemNotification extends Model
{
    use HasUuids;

    protected $table = 'system_notifications';

    protected $fillable = [
        'type',
        'notifiable_type',
        'notifiable_id',
        'data',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
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
     * Scope: only unread notifications.
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope: only read notifications.
     */
    public function scopeRead(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Scope: recent notifications with optional limit.
     */
    public function scopeRecent(Builder $query, int $limit = 10): Builder
    {
        return $query->latest()->limit($limit);
    }

    /**
     * Scope: notifications for a specific notifiable.
     */
    public function scopeForNotifiable(Builder $query, Model $notifiable): Builder
    {
        return $query->where('notifiable_type', $notifiable->getMorphClass())
            ->where('notifiable_id', $notifiable->getKey());
    }

    /**
     * Mark this notification as read.
     */
    public function markAsRead(): void
    {
        if (is_null($this->read_at)) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * Get the notification title.
     */
    public function getTitle(): string
    {
        return $this->data['title'] ?? '';
    }

    /**
     * Get the notification body.
     */
    public function getBody(): string
    {
        return $this->data['body'] ?? '';
    }

    /**
     * Get the notification icon.
     */
    public function getIcon(): string
    {
        return $this->data['icon'] ?? 'ph-bell';
    }

    /**
     * Get the notification URL.
     */
    public function getUrl(): ?string
    {
        return $this->data['url'] ?? null;
    }

    /**
     * Get the notification visual type (info, success, warning, danger).
     */
    public function getType(): string
    {
        return $this->data['type'] ?? 'info';
    }

    /**
     * Check if the notification has been read.
     */
    public function isRead(): bool
    {
        return ! is_null($this->read_at);
    }
}
