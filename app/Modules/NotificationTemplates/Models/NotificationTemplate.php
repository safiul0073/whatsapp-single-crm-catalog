<?php

namespace App\Modules\NotificationTemplates\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'notification_templates';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'email_subject',
        'email_body',
        'sms_body',
        'in_app_title',
        'in_app_body',
        'push_title',
        'push_body',
        'channels',
        'variables',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'variables' => 'array',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Find a template by its slug.
     */
    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }

    /**
     * Scope: only active templates.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: find by slug.
     */
    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }

    /**
     * Get the enabled channels for this template, filtered by global settings.
     *
     * @return array<string>
     */
    public function getEnabledChannels(): array
    {
        $templateChannels = $this->channels ?? [];

        return array_filter($templateChannels, function (string $channel) {
            return match ($channel) {
                'email' => (bool) setting('enable_email_notifications', true),
                'sms' => (bool) setting('enable_sms_notifications', false),
                'in_app' => true,
                'web_push' => (bool) setting('enable_push_notifications', false),
                'mobile_push' => (bool) setting('enable_mobile_push_notifications', false),
                default => false,
            };
        });
    }

    /**
     * Relationship: notification logs for this template.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'template_slug', 'slug');
    }
}
