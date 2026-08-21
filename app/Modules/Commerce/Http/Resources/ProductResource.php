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
            'primary_image' => $this->primaryMedia ? media_url($this->primaryMedia->file_path) : null,
            'rating' => (float) $this->rating,
            'reviews_count' => (int) $this->reviews_count,
            'starting_price' => (float) $this->starting_price, // Will be loaded dynamically via withMin
            'published_at' => $this->published_at,
        ];
    }
}
