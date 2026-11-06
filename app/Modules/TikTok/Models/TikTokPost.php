<?php

namespace App\Modules\TikTok\Models;

use App\Models\User;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A video sent from this dashboard to TikTok, either posted directly (video.publish) or sent to the
 * creator's TikTok inbox as a draft (video.upload).
 */
class TikTokPost extends Model
{
    public const MODE_DIRECT = 'direct';

    public const MODE_DRAFT = 'draft';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_IN_INBOX = 'in_inbox';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_FAILED = 'failed';

    public const PRIVACY_LEVELS = ['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'FOLLOWER_OF_CREATOR', 'SELF_ONLY'];

    protected $table = 'tiktok_posts';

    protected $fillable = [
        'workspace_id',
        'created_by',
        'channel_account_id',
        'mode',
        'title',
        'privacy_level',
        'allow_comment',
        'allow_duet',
        'allow_stitch',
        'brand_organic',
        'brand_content',
        'is_aigc',
        'product_id',
        'video_path',
        'video_mime',
        'video_size',
        'publish_id',
        'status',
        'fail_reason',
        'tiktok_post_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'allow_comment' => 'boolean',
            'allow_duet' => 'boolean',
            'allow_stitch' => 'boolean',
            'brand_organic' => 'boolean',
            'brand_content' => 'boolean',
            'is_aigc' => 'boolean',
            'video_size' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function channelAccount(): BelongsTo
    {
        return $this->belongsTo(ChannelAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_PROCESSING], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_QUEUED => __('Uploading…'),
            self::STATUS_PROCESSING => __('TikTok is processing'),
            self::STATUS_IN_INBOX => __('In your TikTok inbox'),
            self::STATUS_PUBLISHED => __('Posted'),
            self::STATUS_FAILED => __('Failed'),
            default => (string) $this->status,
        };
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            self::STATUS_PUBLISHED => 'badge-success',
            self::STATUS_IN_INBOX, self::STATUS_PROCESSING, self::STATUS_QUEUED => 'badge-warning',
            self::STATUS_FAILED => 'badge-danger',
            default => 'badge-light',
        };
    }

    public static function privacyLabel(string $level): string
    {
        return match ($level) {
            'PUBLIC_TO_EVERYONE' => __('Everyone'),
            'MUTUAL_FOLLOW_FRIENDS' => __('Friends'),
            'FOLLOWER_OF_CREATOR' => __('Followers'),
            'SELF_ONLY' => __('Only me'),
            default => $level,
        };
    }
}
