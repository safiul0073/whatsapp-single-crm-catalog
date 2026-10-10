<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $table = 'commerce_order_items';

    protected $fillable = ['group_id', 'workspace_id', 'order_id', 'variant_id', 'retailer_id', 'sku', 'product_name', 'attributes', 'quantity', 'unit_price', 'line_total', 'provider_unit_price'];

    protected function casts(): array
    {
        return ['attributes' => 'array', 'quantity' => 'integer', 'unit_price' => 'decimal:4', 'line_total' => 'decimal:4', 'provider_unit_price' => 'decimal:4'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /** @return array<int, array{url: string, label: string}> */
    public function productImages(): array
    {
        $product = $this->variant?->product;
        $color = $this->variant?->color;
        $gallery = $product?->gallery ?? collect();
        $images = $gallery->filter(fn ($image) => $image->media?->isImage() && $image->media_type !== 'video');
        $selected = $color ? $images->where('color_id', $color->id) : collect();
        $fallback = $images->whereNull('color_id');
        $urls = collect([$color?->swatchMedia?->isImage() ? $color->swatchMedia->url : null]);
        if ($selected->isNotEmpty()) {
            $urls = $urls->merge($selected->map(fn ($image) => $image->media->url));
        } else {
            $urls = $urls->push($product?->primaryMedia?->isImage() ? $product->primaryMedia->url : null)
                ->merge($fallback->map(fn ($image) => $image->media->url));
        }

        return $urls->filter()->unique()->values()->map(fn ($url) => [
            'url' => $url,
            'label' => $this->product_name.(! empty($this->getAttribute('attributes')['color']) ? ' · '.$this->getAttribute('attributes')['color'] : ''),
        ])->all();
    }
}
