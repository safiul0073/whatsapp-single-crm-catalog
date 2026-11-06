<?php

namespace App\Modules\MetaAds\Models;

use App\Models\User;
use App\Modules\Commerce\Services\OrderMoney;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An ad created from this platform: one Meta campaign → ad set → creative → ad, always created
 * paused so nothing is spent until the owner starts it.
 */
class MetaAd extends Model
{
    public const KIND_PRODUCT = 'product';

    public const KIND_BOOST_POST = 'boost_post';

    public const KIND_PARTNERSHIP = 'partnership';

    public const KINDS = [self::KIND_PRODUCT, self::KIND_BOOST_POST, self::KIND_PARTNERSHIP];

    public const DESTINATIONS = ['website', 'messenger', 'whatsapp'];

    public const STATUS_CREATING = 'creating';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DELETED = 'deleted';

    protected $fillable = [
        'workspace_id',
        'created_by',
        'ad_account_id',
        'page_id',
        'instagram_account_id',
        'kind',
        'name',
        'objective',
        'destination',
        'daily_budget_minor',
        'currency',
        'starts_at',
        'ends_at',
        'targeting',
        'source',
        'meta_campaign_id',
        'meta_adset_id',
        'meta_creative_id',
        'meta_ad_id',
        'status',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'daily_budget_minor' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'targeting' => 'array',
            'source' => 'array',
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

    public function isLive(): bool
    {
        return in_array($this->status, [self::STATUS_PAUSED, self::STATUS_ACTIVE], true) && filled($this->meta_campaign_id);
    }

    public function budgetLabel(): string
    {
        return trim($this->currency.' '.OrderMoney::decimal((int) $this->daily_budget_minor));
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            self::KIND_PRODUCT => __('Product'),
            self::KIND_BOOST_POST => __('Boosted post'),
            self::KIND_PARTNERSHIP => __('Partnership'),
            default => (string) $this->kind,
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_CREATING => __('Creating…'),
            self::STATUS_PAUSED => __('Paused'),
            self::STATUS_ACTIVE => __('Running'),
            self::STATUS_FAILED => __('Failed'),
            default => (string) $this->status,
        };
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'badge-success',
            self::STATUS_PAUSED => 'badge-warning',
            self::STATUS_FAILED => 'badge-danger',
            default => 'badge-light',
        };
    }
}
