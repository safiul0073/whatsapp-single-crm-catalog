<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Media\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $table = 'commerce_product_variants';

    protected $fillable = ['workspace_id', 'product_id', 'media_id', 'sku', 'meta_retailer_id', 'attributes', 'price', 'compare_at_price', 'stock_quantity', 'weight_kg', 'package_dimensions', 'status'];

    protected function casts(): array
    {
        return ['attributes' => 'array', 'package_dimensions' => 'array', 'price' => 'decimal:2', 'compare_at_price' => 'decimal:2', 'stock_quantity' => 'integer', 'weight_kg' => 'decimal:3'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'variant_id');
    }
}
