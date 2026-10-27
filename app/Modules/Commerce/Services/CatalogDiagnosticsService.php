<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Models\Catalog;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use Illuminate\Support\Facades\Cache;

class CatalogDiagnosticsService
{
    protected const META_ACCESS_CACHE_SECONDS = 900;

    public function __construct(protected MetaCatalogClient $meta) {}

    /**
     * @return array{ready: bool, checks: array<int, array{code: string, passed: bool, message: string}>, blocking_count: int, meta_verified: bool|null}
     */
    public function diagnose(Catalog $catalog, bool $probeMeta = false, ?int $productId = null): array
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

        $variants = ProductVariant::query()->with(['product.primaryMedia', 'media'])->where('workspace_id', $catalog->workspace_id)->when($productId !== null, fn ($query) => $query->where('product_id', $productId))->whereIn('status', ['active', 'out_of_stock'])->whereHas('product', fn ($query) => $query->where('status', 'active'))->get();
        $checks[] = $this->check('active_items', $variants->isNotEmpty(), 'At least one active catalog item exists.');
        $checks[] = $this->check('public_images', $variants->every(fn ($variant): bool => str_starts_with((string) ($variant->media?->url ?? $variant->product->primaryMedia?->url), 'https://')), 'All active items have public HTTPS images.');

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
     * Last Facebook/Instagram Shop link results for this catalog; reads the cache only, so page renders never call Graph.
     *
     * @return array<int, array{channel_account_id: int, provider: string, name: string, passed: bool, message: string}>
     */
    public function salesChannelStatus(Catalog $catalog): array
    {
        return Cache::get($this->salesChannelsKey($catalog), []);
    }

    /**
     * Checks whether the catalog is the one connected to each Messenger Page and Instagram account of the workspace.
     * Linking happens in Meta Commerce Manager: Meta exposes no API to attach a catalog to a Page or Instagram Shop.
     *
     * @return array<int, array{channel_account_id: int, provider: string, name: string, passed: bool, message: string}>
     */
    public function probeSalesChannels(Catalog $catalog): array
    {
        $accounts = ChannelAccount::query()
            ->where('workspace_id', $catalog->workspace_id)
            ->whereIn('provider', ['messenger', 'instagram'])
            ->where('status', 'connected')
            ->orderBy('id')
            ->get();

        $results = $accounts->map(fn (ChannelAccount $account): array => $this->probeSalesChannel($catalog, $account))->all();
        Cache::put($this->salesChannelsKey($catalog), $results, now()->addDay());

        return $results;
    }

    /**
     * @return array{channel_account_id: int, provider: string, name: string, passed: bool, message: string}
     */
    protected function probeSalesChannel(Catalog $catalog, ChannelAccount $account): array
    {
        $result = ['channel_account_id' => $account->id, 'provider' => $account->provider, 'name' => (string) $account->name];
        $label = $account->provider === 'instagram' ? 'Instagram Shop' : 'Facebook Page Shop';
        $token = (string) $account->credential('access_token');

        if (blank($catalog->meta_catalog_id) || blank($token) || blank($account->provider_account_id)) {
            return $result + ['passed' => false, 'message' => 'Configure the Meta catalog ID and reconnect this channel before checking.'];
        }

        try {
            $response = $account->provider === 'instagram'
                ? $this->meta->instagramAvailableCatalogs((string) $account->provider_account_id, $token)
                : $this->meta->pageCatalogs((string) $account->provider_account_id, $token);
        } catch (\Throwable $exception) {
            return $result + ['passed' => false, 'message' => $label.' check failed: '.$exception->getMessage()];
        }

        if (! $response->successful()) {
            return $result + ['passed' => false, 'message' => (string) ($response->json('error.message') ?: $label.' check failed.')];
        }

        $linked = collect($response->json('data') ?: [])
            ->map(fn (array $item): string => (string) ($item['catalog_id'] ?? $item['id'] ?? ''))
            ->filter();

        if ($linked->contains((string) $catalog->meta_catalog_id)) {
            return $result + ['passed' => true, 'message' => $label.' uses this catalog.'];
        }

        if ($linked->isEmpty()) {
            return $result + ['passed' => false, 'message' => 'No catalog is connected to this '.$label.'. Connect catalog '.$catalog->meta_catalog_id.' in Meta Commerce Manager.'];
        }

        return $result + ['passed' => false, 'message' => $label.' uses catalog '.$linked->implode(', ').', not '.$catalog->meta_catalog_id.'.'];
    }

    protected function salesChannelsKey(Catalog $catalog): string
    {
        return "commerce.catalog.{$catalog->id}.sales_channels";
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
            if (str_contains((string) $response->json('error.message'), 'SMB business type')) {
                return $this->check('catalog_access', false, 'This number is connected in coexistence with the WhatsApp Business App, so Meta blocks catalog lookup through the WhatsApp Business Account. Link the catalog in the WhatsApp Business App or Commerce Manager, and grant the system user catalog_management access to catalog '.$catalog->meta_catalog_id.'.');
            }

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
