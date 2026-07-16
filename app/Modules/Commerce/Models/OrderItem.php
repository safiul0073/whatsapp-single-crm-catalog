<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $table = 'commerce_order_items';

    protected $fillable = ['workspace_id', 'order_id', 'variant_id', 'retailer_id', 'sku', 'product_name', 'attributes', 'quantity', 'unit_price', 'line_total', 'provider_unit_price'];

    protected function casts(): array
    {
        return ['attributes' => 'array', 'quantity' => 'integer', 'unit_price' => 'decimal:2', 'line_total' => 'decimal:2', 'provider_unit_price' => 'decimal:2'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
