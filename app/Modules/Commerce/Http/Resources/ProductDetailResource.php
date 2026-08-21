<?php

namespace App\Modules\Commerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductDetailResource extends JsonResource
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
            'description' => $this->description,
            'care_information' => $this->care_information,
            'features' => $this->features,
            'feature_highlights' => $this->feature_highlights,
            'shipping_countries' => $this->shipping_countries,
            'specifications' => $this->specifications,
            'fit' => $this->fit,
            'set_includes' => $this->set_includes,
            'gender' => $this->gender,
            'season' => $this->season,
            'shipping_info' => $this->shipping_info,
            'delivery_time' => $this->delivery_time,
            'moq' => (int) $this->moq,
            'rating' => (float) $this->rating,
            'reviews_count' => (int) $this->reviews_count,
            'condition' => $this->condition,
            'fabric_gsm' => $this->fabric_gsm,
            'material' => $this->material,
            'default_unit_weight_kg' => (float) $this->default_unit_weight_kg,
            'single_piece_price' => (float) $this->single_piece_price,
            'wholesale_price' => (float) $this->wholesale_price,
            'country_of_origin' => $this->country_of_origin,
            'published_at' => $this->published_at,
            'primary_image' => $this->primaryMedia ? media_url($this->primaryMedia->file_path) : null,
            'gallery' => $this->gallery->map(fn ($g) => [
                'id' => $g->id,
                'url' => media_url($g->media?->file_path),
                'position' => $g->position,
            ]),
            'colors' => $this->colors->map(fn ($color) => [
                'id' => $color->id,
                'name' => $color->name,
                'hex_code' => $color->hex_code,
                'swatch_image' => $color->swatchMedia ? media_url($color->swatchMedia->file_path) : null,
                'position' => $color->position,
            ]),
            'options' => $this->options->map(fn ($option) => [
                'id' => $option->id,
                'name' => $option->name,
                'code' => $option->code,
                'values' => $option->values->pluck('value'),
            ]),
            'variants' => $this->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'price' => (float) $variant->price,
                'compare_at_price' => (float) $variant->compare_at_price,
                'cost_price' => (float) $variant->cost_price,
                'stock_quantity' => (int) $variant->stock_quantity,
                'weight_kg' => (float) $variant->weight_kg,
                'color_id' => $variant->color_id,
                'options' => $variant->options,
                'status' => $variant->status,
                'image' => $variant->media ? media_url($variant->media->file_path) : null,
            ]),
            'tier_prices' => $this->tierPrices->map(fn ($tier) => [
                'id' => $tier->id,
                'min_quantity' => (int) $tier->min_quantity,
                'price' => (float) $tier->price,
            ]),
        ];
    }
}
