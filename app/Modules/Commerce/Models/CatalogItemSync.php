<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogItemSync extends Model
{
    protected $table = 'commerce_catalog_item_syncs';

    protected $fillable = ['workspace_id', 'catalog_id', 'variant_id', 'retailer_id', 'provider_item_id', 'payload_hash', 'status', 'attempts', 'provider_response', 'last_error', 'synced_at'];

    protected function casts(): array
    {
        return ['attempts' => 'integer', 'provider_response' => 'array', 'synced_at' => 'datetime'];
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Catalog::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
