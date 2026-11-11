<?php

namespace App\Modules\PlansSubscriptions\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    public const INTERVAL_MONTH = 'month';

    public const INTERVAL_YEAR = 'year';

    public const INTERVAL_LIFETIME = 'lifetime';

    /**
     * Key inside `limits` holding the modules and menu items this plan does not include.
     */
    public const BLOCKED_FEATURES = 'blocked_features';

    protected $fillable = ['name', 'slug', 'description', 'price', 'currency', 'interval', 'limits', 'features', 'is_free', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'limits' => 'array', 'features' => 'array', 'is_free' => 'boolean', 'is_active' => 'boolean'];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isFree(): bool
    {
        return $this->is_free || (float) $this->price <= 0;
    }

    public function isLifetime(): bool
    {
        return $this->interval === self::INTERVAL_LIFETIME;
    }

    /**
     * Never-expiring plans (free and lifetime) have no renewal date.
     */
    public function neverExpires(): bool
    {
        return $this->isFree() || $this->isLifetime();
    }

    public function billingCurrency(): string
    {
        return $this->currency ?: currency_default_code();
    }

    public function limit(string $key): mixed
    {
        return data_get($this->limits ?? [], $key);
    }

    /**
     * @return list<string>
     */
    public function blockedFeatures(): array
    {
        return array_values(array_filter((array) data_get($this->limits ?? [], self::BLOCKED_FEATURES, []), 'is_string'));
    }

    /**
     * A menu item is included only when neither it nor its module is blocked.
     */
    public function includesFeature(string $module, ?string $item = null): bool
    {
        $blocked = $this->blockedFeatures();

        if (in_array($module, $blocked, true)) {
            return false;
        }

        return $item === null || ! in_array($item, $blocked, true);
    }
}
