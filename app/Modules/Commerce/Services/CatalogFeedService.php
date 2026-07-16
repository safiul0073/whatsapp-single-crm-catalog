<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Models\Catalog;
use App\Modules\Commerce\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogFeedService
{
    public function response(Catalog $catalog): StreamedResponse
    {
        $catalog->forceFill(['last_fetched_at' => now()])->save();
        $variants = $this->activeVariants($catalog->workspace_id);
        $catalog->forceFill(['last_item_count' => $variants->count(), 'last_successful_at' => now(), 'last_sync_status' => 'completed', 'last_sync_summary' => ['successful' => $variants->count(), 'failed' => 0], 'last_error' => null])->save();

        return response()->streamDownload(function () use ($variants): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['id', 'title', 'description', 'availability', 'condition', 'price', 'link', 'image_link', 'additional_image_link', 'brand', 'item_group_id', 'color', 'size', 'gender', 'age_group', 'material', 'pattern']);
            foreach ($variants as $variant) {
                $payload = $this->itemPayload($variant);
                fputcsv($stream, [
                    $payload['retailer_id'],
                    $payload['name'],
                    $payload['description'],
                    $payload['availability'],
                    $payload['condition'],
                    number_format((float) $variant->price, 2, '.', '').' USD',
                    $payload['url'],
                    $payload['image_url'],
                    implode(',', $payload['additional_image_urls']),
                    $payload['brand'],
                    $payload['item_group_id'],
                    $payload['color'],
                    $payload['size'],
                    $payload['gender'],
                    $payload['age_group'],
                    $payload['material'],
                    $payload['pattern'],
                ]);
            }
            fclose($stream);
        }, 'meta-catalog.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function itemPayload(ProductVariant $variant): array
    {
        $variant->loadMissing(['product.primaryMedia', 'product.gallery.media', 'media']);
        $attributes = $variant->attributes ?? [];
        $primary = $variant->media?->url ?? $variant->product->primaryMedia?->url;
        $additional = $variant->product->gallery
            ->where('media_type', 'image')
            ->reject(fn ($item): bool => $item->media?->url === $primary)
            ->map(fn ($item): ?string => $item->media?->url)
            ->filter()
            ->take(9)
            ->values()
            ->all();

        return [
            'retailer_id' => $variant->meta_retailer_id,
            'name' => trim($variant->product->name.' - '.implode(' / ', array_values($attributes)), ' -'),
            'description' => strip_tags((string) $variant->product->description),
            'availability' => $variant->stock_quantity > 0 && $variant->status === 'active' ? 'in stock' : 'out of stock',
            'condition' => $variant->product->condition,
            'price' => (int) round((float) $variant->price * 100),
            'currency' => 'USD',
            'url' => URL::route('commerce.products.public', ['product' => $variant->product->slug]),
            'image_url' => $primary,
            'additional_image_urls' => $additional,
            'brand' => $variant->product->brand ?: config('app.name'),
            'item_group_id' => 'product-'.$variant->product_id,
            'color' => $attributes['color'] ?? null,
            'size' => $attributes['size'] ?? null,
            'gender' => $attributes['gender'] ?? $variant->product->audience,
            'age_group' => $attributes['age_group'] ?? null,
            'material' => $attributes['material'] ?? null,
            'pattern' => $attributes['pattern'] ?? null,
        ];
    }

    protected function activeVariants(int $workspaceId): Collection
    {
        return ProductVariant::query()
            ->with(['product.primaryMedia', 'product.gallery.media', 'media'])
            ->where('workspace_id', $workspaceId)
            ->whereIn('status', ['active', 'out_of_stock'])
            ->whereHas('product', fn ($query) => $query->where('status', 'active'))
            ->orderBy('id')
            ->get();
    }
}
