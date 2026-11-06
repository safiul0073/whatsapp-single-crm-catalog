<?php

namespace App\Modules\MetaSocial\Models;

use App\Models\User;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A post written here and published (now or later) to a Facebook Page and/or an Instagram account.
 */
class MetaPagePost extends Model
{
    public const TARGETS = ['facebook', 'instagram'];

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PUBLISHING = 'publishing';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'workspace_id',
        'created_by',
        'page_id',
        'instagram_account_id',
        'targets',
        'message',
        'link',
        'image_url',
        'product_id',
        'scheduled_at',
        'status',
        'published_at',
        'facebook_post_id',
        'instagram_media_id',
        'errors',
    ];

    protected function casts(): array
    {
        return [
            'targets' => 'array',
            'errors' => 'array',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function targetList(): array
    {
        return array_values(array_intersect(self::TARGETS, (array) $this->getAttribute('targets')));
    }

    public function postsTo(string $target): bool
    {
        return in_array($target, $this->targetList(), true);
    }

    public function caption(): string
    {
        return trim($this->message.(filled($this->link) && filled($this->image_url) ? "\n\n".$this->link : ''));
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_SCHEDULED => __('Scheduled'),
            self::STATUS_PUBLISHING => __('Publishing…'),
            self::STATUS_PUBLISHED => __('Published'),
            self::STATUS_PARTIAL => __('Partly published'),
            self::STATUS_FAILED => __('Failed'),
            default => (string) $this->status,
        };
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            self::STATUS_PUBLISHED => 'badge-success',
            self::STATUS_PARTIAL, self::STATUS_SCHEDULED => 'badge-warning',
            self::STATUS_FAILED => 'badge-danger',
            default => 'badge-light',
        };
    }

    public function facebookUrl(): ?string
    {
        return filled($this->facebook_post_id) ? 'https://www.facebook.com/'.$this->facebook_post_id : null;
    }
}
