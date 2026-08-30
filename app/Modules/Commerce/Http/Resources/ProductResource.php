<?php

namespace App\Modules\Commerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'brand' => $this->brandRecord?->name ?? $this->brand,
            'category' => $this->category?->name,
            'short_description' => $this->short_description,
            'single_piece_price' => (float) $this->single_piece_price,
            'wholesale_price' => (float) $this->wholesale_price,
            'selling_mode' => $this->selling_mode ?? 'both',
            'primary_image' => $this->primaryMedia ? $this->primaryMedia->url : null,
            'rating' => (float) $this->rating,
            'reviews_count' => (int) $this->reviews_count,
            'starting_price' => (float) $this->starting_price, // Will be loaded dynamically via withMin
            'published_at' => $this->published_at,
            // UI specific fields for ecommarce frontend
            'price' => (float) $this->resolveUnitPrice(1, 'single'),
            'originalPrice' => $this->single_piece_price, 
            'sale' => false,
            'saleText' => null,
            'hasVariants' => $this->variants->isNotEmpty(),
            'variantType' => count($this->colors) > 0 ? (count($this->options) > 0 ? 'color_size' : 'color') : (count($this->options) > 0 ? 'size' : null),
            'colors' => collect($this->colors)->map(fn($color) => [
                'id' => $color->id,
                'name' => $color->name ?? $color->color_family,
                'code' => $color->hex_code ?? '#000000',
            ])->toArray(),
            'sizes' => collect($this->variants)->pluck('size')->filter()->unique()->map(fn($size, $idx) => [
                'id' => $idx,
                'name' => $size
            ])->values()->toArray(),
            'available' => $this->status === 'active',
        ];
    }
}
