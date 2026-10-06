<?php

namespace App\Modules\Commerce\Services;

use App\Modules\AuditLog\Services\AuditLogService;
use App\Modules\Commerce\Jobs\SyncMetaCatalogJob;
use App\Modules\Commerce\Models\Catalog;
use App\Modules\Commerce\Models\CatalogItemSync;
use App\Modules\Commerce\Models\CatalogSyncRun;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

class CatalogSyncService
{
    public function __construct(
        protected CatalogFeedService $feed,
        protected MetaCatalogClient $meta,
        protected CatalogDiagnosticsService $diagnostics,
        protected AuditLogService $audit,
    ) {}

    public function queue(Catalog $catalog, ?Product $product = null): CatalogSyncRun
    {
        $catalog->refresh();

        if (! $catalog->is_active) {
            throw ValidationException::withMessages(['catalog' => 'Activate this catalog before synchronizing.']);
        }

        if ($catalog->sync_mode !== 'api') {
            throw ValidationException::withMessages(['sync_mode' => 'Switch this catalog to direct API mode before synchronizing.']);
        }

        if ($product && ($product->workspace_id !== $catalog->workspace_id || $product->status !== 'active')) {
            throw ValidationException::withMessages(['catalog' => 'Publish this product in the catalog workspace before sending it to Meta.']);
        }

        $diagnostics = $this->diagnostics->diagnose($catalog, true, $product?->id);
        if (! $diagnostics['ready']) {
            throw ValidationException::withMessages(['catalog' => collect($diagnostics['checks'])->where('passed', false)->pluck('message')->all()]);
        }

        $count = ProductVariant::query()->where('workspace_id', $catalog->workspace_id)->when($product, fn ($query) => $query->where('product_id', $product->id))->count();
        $run = CatalogSyncRun::query()->create(['workspace_id' => $catalog->workspace_id, 'catalog_id' => $catalog->id, 'mode' => 'api', 'status' => 'queued', 'total_items' => $count, 'summary' => ['product_id' => $product?->id, 'meta_catalog_id' => $catalog->meta_catalog_id]]);
        $catalog->update(['last_sync_status' => 'queued']);
        SyncMetaCatalogJob::dispatch($run->id);
        $this->audit->logCustom('commerce.catalog.sync_queued', ['catalog_id' => $catalog->id, 'run_id' => $run->id]);

        return $run;
    }

    public function run(CatalogSyncRun $run): void
    {
        $run->loadMissing('catalog.channelAccount');
        $catalog = $run->catalog;
        if ((string) data_get($run->summary, 'meta_catalog_id', $catalog->meta_catalog_id) !== (string) $catalog->meta_catalog_id) {
            throw new \RuntimeException('The catalog connection changed. Start a new synchronization.');
        }

        $token = (string) $catalog->channelAccount->credential('access_token');
        $run->update(['status' => 'running', 'started_at' => $run->started_at ?? now(), 'last_error' => null]);
        $successful = 0;
        $failed = 0;
        $pending = 0;
        $productId = data_get($run->summary, 'product_id');
        $deadline = microtime(true) + 35;

        $variants = ProductVariant::query()->with(['product.primaryMedia', 'product.gallery.media', 'product.workspace', 'media'])->where('workspace_id', $catalog->workspace_id)->when($productId, fn ($query) => $query->where('product_id', $productId))->orderBy('id')->get();
        foreach ($variants as $index => $variant) {
            if (microtime(true) >= $deadline) {
                $pending += $variants->count() - $index;

                break;
            }
            $payload = $this->feed->itemPayload($variant);
            $method = in_array($variant->status, ['active', 'out_of_stock'], true) && $variant->product->status === 'active' ? 'UPDATE' : 'DELETE';
            $hash = hash('sha256', json_encode([$catalog->meta_catalog_id, $method, $payload], JSON_THROW_ON_ERROR));
            $sync = CatalogItemSync::query()->firstOrCreate(
                ['catalog_id' => $catalog->id, 'variant_id' => $variant->id],
                ['workspace_id' => $catalog->workspace_id, 'retailer_id' => $variant->meta_retailer_id]
            );

            if ($sync->payload_hash === $hash && $sync->status === 'synced') {
                $successful++;

                continue;
            }

            if ($method === 'DELETE' && blank($sync->provider_response)) {
                $sync->update(['payload_hash' => $hash, 'status' => 'synced']);
                $successful++;

                continue;
            }

            try {
                if ($sync->status === 'processing' && (string) data_get($sync->provider_response, 'meta_catalog_id') === (string) $catalog->meta_catalog_id && filled(data_get($sync->provider_response, 'handles.0'))) {
                    $response = $this->meta->batchStatus((string) $catalog->meta_catalog_id, $token, (string) data_get($sync->provider_response, 'handles.0'));
                    $result = $response->json('data.0') ?? $response->json();
                    if (! $response->successful() || ! empty($result['errors']) || (int) ($result['errors_total_count'] ?? 0) > 0 || ! empty($result['ids_of_invalid_requests']) || strtoupper((string) ($result['status'] ?? '')) === 'FAILED') {
                        throw new \RuntimeException($response->json('error.message') ?: json_encode($result, JSON_THROW_ON_ERROR));
                    }
                    if (strtoupper((string) ($result['status'] ?? '')) !== 'FINISHED') {
                        $pending++;

                        continue;
                    }
                    $sync->update(['status' => 'synced', 'last_error' => null, 'synced_at' => now()]);
                    if ($sync->payload_hash === $hash) {
                        $successful++;

                        continue;
                    }
                    if (microtime(true) >= $deadline) {
                        $pending++;

                        continue;
                    }
                }

                $response = $method === 'UPDATE'
                    ? $this->meta->upsertProduct((string) $catalog->meta_catalog_id, $token, $variant->meta_retailer_id, $payload)
                    : $this->meta->deleteProduct((string) $catalog->meta_catalog_id, $token, $variant->meta_retailer_id);
                $sync->increment('attempts');
                $sync->update(['provider_response' => array_merge($response->json() ?? [], ['meta_catalog_id' => $catalog->meta_catalog_id])]);
                if (! $response->successful() || blank($response->json('handles.0'))) {
                    throw new \RuntimeException($response->json('error.message') ?: 'Meta did not accept the catalog item. '.json_encode($response->json(), JSON_THROW_ON_ERROR));
                }
                $sync->update(['retailer_id' => $variant->meta_retailer_id, 'payload_hash' => $hash, 'status' => 'processing', 'last_error' => null]);
                $pending++;
            } catch (\Throwable $exception) {
                $sync->update(['status' => 'failed', 'last_error' => $exception->getMessage()]);
                $failed++;
            }
        }

        $status = $pending > 0 ? 'running' : ($failed === 0 ? 'completed' : 'failed');
        $summary = array_merge($run->summary ?? [], ['successful' => $successful, 'failed' => $failed, 'pending' => $pending]);
        $run->update(['status' => $status, 'successful_items' => $successful, 'failed_items' => $failed, 'summary' => $summary, 'finished_at' => $pending > 0 ? null : now(), 'last_error' => $failed ? 'One or more catalog items failed.' : null]);
        $catalog->update(['last_sync_status' => $status, 'last_sync_summary' => $summary, 'last_item_count' => $catalog->itemSyncs()->where('status', 'synced')->where('provider_response->meta_catalog_id', $catalog->meta_catalog_id)->whereHas('variant', fn ($query) => $query->whereIn('status', ['active', 'out_of_stock'])->whereHas('product', fn ($products) => $products->where('status', 'active')))->count(), 'last_successful_at' => $status === 'completed' ? now() : $catalog->last_successful_at, 'last_reconciled_at' => $status === 'completed' && ! $productId ? now() : $catalog->last_reconciled_at, 'last_error' => $failed ? 'One or more catalog items failed.' : null]);

        if ($pending > 0) {
            return;
        }

        if ($failed > 0) {
            throw new \RuntimeException('Meta catalog synchronization completed with failed items.');
        }
    }
}
