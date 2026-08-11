<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Commerce\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

class CommerceTemplatePayloadService
{
    public function catalogButtonComponent(Campaign $campaign): ?array
    {
        $commerce = $campaign->settings['commerce'] ?? [];
        $thumbnail = $commerce['thumbnail_product_retailer_id'] ?? null;

        if (blank($thumbnail)) {
            return null;
        }

        return [
            'type' => 'button',
            'sub_type' => 'CATALOG',
            'index' => '0',
            'parameters' => [[
                'type' => 'action',
                'action' => ['thumbnail_product_retailer_id' => (string) $thumbnail],
            ]],
        ];
    }

    public function multiProductButtonComponent(Campaign $campaign): ?array
    {
        $commerce = $campaign->settings['commerce'] ?? [];
        $sections = $commerce['sections'] ?? [];

        if ($sections === []) {
            return null;
        }

        return [
            'type' => 'button',
            'sub_type' => 'MPM',
            'index' => '0',
            'parameters' => [[
                'type' => 'action',
                'action' => array_filter([
                    'thumbnail_product_retailer_id' => $commerce['thumbnail_product_retailer_id'] ?? null,
                    'sections' => $sections,
                ]),
            ]],
        ];
    }

    public function settingsFromRequest(int $workspaceId, array $settings): array
    {
        $commerce = $settings['commerce'] ?? [];
        $variantIds = collect($commerce['variant_ids'] ?? [])->map(fn ($id): int => (int) $id)->filter()->unique()->values();

        if ($variantIds->isEmpty()) {
            return $settings;
        }

        $variants = ProductVariant::query()
            ->with('product.category')
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $variantIds)
            ->whereIn('status', ['active', 'out_of_stock'])
            ->whereHas('product', fn ($query) => $query->where('status', 'active'))
            ->get();

        if ($variants->count() !== $variantIds->count()) {
            throw ValidationException::withMessages([
                'settings.commerce.variant_ids' => 'One or more selected products are unavailable for WhatsApp commerce.',
            ]);
        }

        $sections = $variants
            ->groupBy(fn (ProductVariant $variant): string => $variant->product->category?->name ?? 'Products')
            ->map(fn ($items, string $title): array => [
                'title' => str($title)->limit(24, '')->toString(),
                'product_items' => $items
                    ->take(30)
                    ->map(fn (ProductVariant $variant): array => ['product_retailer_id' => $variant->meta_retailer_id])
                    ->values()
                    ->all(),
            ])
            ->take(10)
            ->values()
            ->all();

        $settings['commerce'] = array_filter([
            'thumbnail_product_retailer_id' => $commerce['thumbnail_product_retailer_id'] ?? $variants->first()?->meta_retailer_id,
            'variant_ids' => $variantIds->all(),
            'sections' => $sections,
        ]);

        return $settings;
    }
}
