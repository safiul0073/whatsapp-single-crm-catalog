<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Models\Catalog;
use App\Modules\Commerce\Models\ProductVariant;
use Illuminate\Support\Facades\Cache;

class CatalogDiagnosticsService
{
    protected const META_ACCESS_CACHE_SECONDS = 900;

    public function __construct(protected MetaCatalogClient $meta) {}

    /**
     * @return array{ready: bool, checks: array<int, array{code: string, passed: bool, message: string}>, blocking_count: int, meta_verified: bool|null}
     */
    public function diagnose(Catalog $catalog, bool $probeMeta = false): array
    {
        $catalog->loadMissing('channelAccount');
        $channel = $catalog->channelAccount;
        $checks = [
            $this->check('channel_connected', $channel?->status?->value === 'connected', 'WhatsApp channel is connected.'),
            $this->check('access_token', filled($channel?->credential('access_token')), 'Encrypted access token is available.'),
            $this->check('catalog_id', filled($catalog->meta_catalog_id), 'Meta catalog ID is configured.'),
            $this->check('currency', preg_match('/^[A-Z]{3}$/', strtoupper((string) $catalog->currency)) === 1, 'Catalog currency is a valid ISO currency code.'),
            $this->check('https_app_url', str_starts_with((string) config('app.url'), 'https://'), 'Application URL uses public HTTPS.'),
        ];

        $variants = ProductVariant::query()->with(['product.primaryMedia'])->where('workspace_id', $catalog->workspace_id)->where('status', 'active')->whereHas('product', fn ($query) => $query->where('status', 'active'))->get();
        $checks[] = $this->check('active_items', $variants->isNotEmpty(), 'At least one active catalog item exists.');
        $checks[] = $this->check('public_images', $variants->every(fn ($variant): bool => str_starts_with((string) ($variant->media?->url ?? $variant->product->primaryMedia?->url), 'https://')), 'All active items have public HTTPS images.');
        $checks[] = $this->check('sync_freshness', ! $catalog->last_successful_at || $catalog->last_successful_at->greaterThan(now()->subDays(7)), 'Catalog sync is fresh or has not run yet.');

        $access = $this->probeCatalogAccess($catalog, $probeMeta);
        if ($access !== null) {
            $checks[] = $access;
        }

        $blocking = collect($checks)->where('passed', false)->values();

        return ['ready' => $blocking->isEmpty(), 'checks' => $checks, 'blocking_count' => $blocking->count(), 'meta_verified' => $access === null ? null : $access['passed']];
    }

    /**
     * Probes Meta for catalog access, cached so page renders do not hit Graph on every request.
     * Returns null when the catalog is not configured enough to probe, matching the previous skip semantics.
     *
     * @return array{code: string, passed: bool, message: string}|null
     */
    public function probeCatalogAccess(Catalog $catalog, bool $fresh = false): ?array
    {
        $catalog->loadMissing('channelAccount');
        $token = (string) $catalog->channelAccount?->credential('access_token');
        if (blank($catalog->meta_catalog_id) || blank($token)) {
            return null;
        }

        $key = "commerce.catalog.{$catalog->id}.meta_access";
        if ($fresh) {
            Cache::forget($key);
        }

        $wabaId = (string) $catalog->channelAccount?->provider_account_id;

        return Cache::remember($key, static::META_ACCESS_CACHE_SECONDS, function () use ($catalog, $token, $wabaId): array {
            try {
                $response = $this->meta->catalog((string) $catalog->meta_catalog_id, $token);
                if ($response->successful()) {
                    return $this->check('catalog_access', true, 'Meta catalog access verified.');
                }

                return $this->checkLinkedCatalog($catalog, $token, $wabaId, (string) ($response->json('error.message') ?: 'Meta catalog access failed.'));
            } catch (\Throwable $exception) {
                return $this->check('catalog_access', false, 'Meta catalog access failed: '.$exception->getMessage());
            }
        });
    }

    /**
     * Falls back to the WhatsApp Business Account catalog edge, which a WhatsApp system user token
     * can read without the catalog_management scope that a direct catalog node read requires.
     *
     * @return array{code: string, passed: bool, message: string}
     */
    protected function checkLinkedCatalog(Catalog $catalog, string $token, string $wabaId, string $directError): array
    {
        if (blank($wabaId)) {
            return $this->check('catalog_access', false, $directError);
        }

        $response = $this->meta->wabaCatalogs($wabaId, $token);
        if (! $response->successful()) {
            return $this->check('catalog_access', false, $directError);
        }

        $linked = collect($response->json('data') ?: [])->pluck('id')->map(fn ($id): string => (string) $id);
        if ($linked->contains((string) $catalog->meta_catalog_id)) {
            return $this->check('catalog_access', true, 'Meta catalog access verified via the WhatsApp Business Account.');
        }

        if ($linked->isEmpty()) {
            return $this->check('catalog_access', false, 'No catalog is linked to this WhatsApp Business Account. Link it in Meta Commerce Manager.');
        }

        return $this->check('catalog_access', false, 'This WhatsApp Business Account is linked to catalog '.$linked->implode(', ').', not '.$catalog->meta_catalog_id.'.');
    }

    /**
     * @return array{code: string, passed: bool, message: string}
     */
    protected function check(string $code, bool $passed, string $message): array
    {
        return ['code' => $code, 'passed' => $passed, 'message' => $message];
    }
}
