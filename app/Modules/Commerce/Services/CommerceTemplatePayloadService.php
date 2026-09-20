<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Commerce\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

class CommerceTemplatePayloadService
{
    /**
     * @param  array<int, array<string, mixed>>  $templateComponents
     */
    public function catalogButtonComponent(Campaign $campaign, array $templateComponents = []): ?array
    {
        $commerce = $campaign->settings['commerce'] ?? [];
        $thumbnail = $commerce['thumbnail_product_retailer_id'] ?? null;

        if (blank($thumbnail)) {
            return null;
        }

        return [
            'type' => 'button',
            'sub_type' => 'CATALOG',
            'index' => (string) $this->resolveButtonIndex($templateComponents, 'CATALOG'),
            'parameters' => [[
                'type' => 'action',
                'action' => ['thumbnail_product_retailer_id' => (string) $thumbnail],
            ]],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $templateComponents
     */
    public function multiProductButtonComponent(Campaign $campaign, array $templateComponents = []): ?array
    {
        $commerce = $campaign->settings['commerce'] ?? [];
        $sections = $commerce['sections'] ?? [];

        if ($sections === []) {
            return null;
        }

        return [
            'type' => 'button',
            'sub_type' => 'MPM',
            'index' => (string) $this->resolveButtonIndex($templateComponents, 'MPM'),
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
        $thumbnail = $commerce['thumbnail_product_retailer_id'] ?? null;
        $variantIds = collect($commerce['variant_ids'] ?? [])->map(fn ($id): int => (int) $id)->filter()->unique()->values();

        if ($variantIds->isEmpty()) {
            if (filled($thumbnail)) {
                $thumbnailExists = ProductVariant::query()
                    ->where('workspace_id', $workspaceId)
                    ->where('meta_retailer_id', $thumbnail)
                    ->whereIn('status', ['active', 'out_of_stock'])
                    ->exists();

                if (! $thumbnailExists) {
                    $settings['commerce']['thumbnail_product_retailer_id'] = null;
                }
            }

            return $settings;
        }

        $variants = ProductVariant::query()
            ->with('product.category')
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $variantIds)
            ->whereIn('status', ['active', 'out_of_stock'])
            ->whereNotNull('meta_retailer_id')
            ->where('meta_retailer_id', '!=', '')
            ->whereHas('product', fn ($query) => $query->where('status', 'active'))
            ->get();

        if ($variants->count() !== $variantIds->count()) {
            throw ValidationException::withMessages([
                'settings.commerce.variant_ids' => 'One or more selected products are unavailable or not synced to the Meta catalog.',
            ]);
        }

        $sections = $variants
            ->groupBy(fn (ProductVariant $variant): string => $variant->product->category?->name ?? 'Products')
            ->map(fn ($items, string $title): array => [
                'title' => str($title)->limit(24, '')->toString(),
                'product_items' => $items
                    ->take(30)
                    ->filter(fn (ProductVariant $variant): bool => filled($variant->meta_retailer_id))
                    ->map(fn (ProductVariant $variant): array => ['product_retailer_id' => $variant->meta_retailer_id])
                    ->values()
                    ->all(),
            ])
            ->filter(fn (array $section): bool => $section['product_items'] !== [])
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

    /**
     * Resolve the 0-based index of a button type (CATALOG or MPM) within the template's BUTTONS component.
     *
     * @param  array<int, array<string, mixed>>  $templateComponents
     */
    protected function resolveButtonIndex(array $templateComponents, string $buttonType): int
    {
        $buttonsComponent = collect($templateComponents)
            ->first(fn (array $component): bool => strtoupper((string) ($component['type'] ?? '')) === 'BUTTONS');

        if (! $buttonsComponent) {
            return 0;
        }

        $buttons = $buttonsComponent['buttons'] ?? [];

        foreach ($buttons as $index => $button) {
            if (strtoupper((string) ($button['type'] ?? '')) === strtoupper($buttonType)) {
                return $index;
            }
        }

        return 0;
    }
}
