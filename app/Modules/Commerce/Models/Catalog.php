<?php

namespace App\Modules\Commerce\Models;

use App\Modules\MarketingChannels\Models\ChannelAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Catalog extends Model
{
    protected $table = 'commerce_catalogs';

    protected $fillable = ['workspace_id', 'channel_account_id', 'meta_catalog_id', 'feed_token', 'is_active', 'sync_mode', 'readiness_state', 'cart_enabled', 'catalog_visible', 'last_sync_status', 'last_sync_summary', 'last_item_count', 'last_fetched_at', 'last_successful_at', 'last_reconciled_at', 'last_error'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'cart_enabled' => 'boolean', 'catalog_visible' => 'boolean', 'last_sync_summary' => 'array', 'last_item_count' => 'integer', 'last_fetched_at' => 'datetime', 'last_successful_at' => 'datetime', 'last_reconciled_at' => 'datetime'];
    }

    public function channelAccount(): BelongsTo
    {
        return $this->belongsTo(ChannelAccount::class);
    }

    public function itemSyncs(): HasMany
    {
        return $this->hasMany(CatalogItemSync::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(CatalogSyncRun::class);
    }
}
