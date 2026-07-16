<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Models\Catalog;
use App\Modules\Commerce\Models\ProductVariant;

class CatalogDiagnosticsService
{
    public function __construct(protected MetaCatalogClient $meta) {}

    public function diagnose(Catalog $catalog, bool $probeMeta = false): array
    {
        $catalog->loadMissing('channelAccount');
        $channel = $catalog->channelAccount;
        $checks = [
            $this->check('channel_connected', $channel?->status?->value === 'connected', 'WhatsApp channel is connected.'),
            $this->check('access_token', filled($channel?->credential('access_token')), 'Encrypted access token is available.'),
            $this->check('catalog_id', filled($catalog->meta_catalog_id), 'Meta catalog ID is configured.'),
            $this->check('https_app_url', str_starts_with((string) config('app.url'), 'https://'), 'Application URL uses public HTTPS.'),
        ];

        $variants = ProductVariant::query()->with(['product.primaryMedia'])->where('workspace_id', $catalog->workspace_id)->where('status', 'active')->whereHas('product', fn ($query) => $query->where('status', 'active'))->get();
        $checks[] = $this->check('active_items', $variants->isNotEmpty(), 'At least one active catalog item exists.');
        $checks[] = $this->check('public_images', $variants->every(fn ($variant): bool => str_starts_with((string) ($variant->media?->url ?? $variant->product->primaryMedia?->url), 'https://')), 'All active items have public HTTPS images.');

        if ($probeMeta && filled($catalog->meta_catalog_id) && filled($channel?->credential('access_token'))) {
            $response = $this->meta->catalog((string) $catalog->meta_catalog_id, (string) $channel->credential('access_token'));
            $checks[] = $this->check('catalog_access', $response->successful(), $response->successful() ? 'Meta catalog access verified.' : ($response->json('error.message') ?: 'Meta catalog access failed.'));
        }

        $blocking = collect($checks)->where('passed', false)->values();

        return ['ready' => $blocking->isEmpty(), 'checks' => $checks, 'blocking_count' => $blocking->count()];
    }

    protected function check(string $code, bool $passed, string $message): array
    {
        return ['code' => $code, 'passed' => $passed, 'message' => $message];
    }
}
