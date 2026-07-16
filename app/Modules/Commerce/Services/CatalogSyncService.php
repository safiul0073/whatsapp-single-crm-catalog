<?php

namespace App\Modules\Commerce\Services;

use App\Modules\AuditLog\Services\AuditLogService;
use App\Modules\Commerce\Jobs\SyncMetaCatalogJob;
use App\Modules\Commerce\Models\Catalog;
use App\Modules\Commerce\Models\CatalogItemSync;
use App\Modules\Commerce\Models\CatalogSyncRun;
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

    public function queue(Catalog $catalog): CatalogSyncRun
    {
        if ($catalog->sync_mode !== 'api') {
            throw ValidationException::withMessages(['sync_mode' => 'Switch this catalog to direct API mode before synchronizing.']);
        }

        $diagnostics = $this->diagnostics->diagnose($catalog, true);
        if (! $diagnostics['ready']) {
            throw ValidationException::withMessages(['catalog' => collect($diagnostics['checks'])->where('passed', false)->pluck('message')->all()]);
        }

        $count = ProductVariant::query()->where('workspace_id', $catalog->workspace_id)->count();
        $run = CatalogSyncRun::query()->create(['workspace_id' => $catalog->workspace_id, 'catalog_id' => $catalog->id, 'mode' => 'api', 'status' => 'queued', 'total_items' => $count]);
        $catalog->update(['last_sync_status' => 'queued']);
        SyncMetaCatalogJob::dispatch($run->id);
        $this->audit->logCustom('commerce.catalog.sync_queued', ['catalog_id' => $catalog->id, 'run_id' => $run->id]);

        return $run;
    }

    public function run(CatalogSyncRun $run): void
    {
        $run->loadMissing('catalog.channelAccount');
        $catalog = $run->catalog;
        $token = (string) $catalog->channelAccount->credential('access_token');
        $run->update(['status' => 'running', 'started_at' => $run->started_at ?? now(), 'last_error' => null]);
        $successful = 0;
        $failed = 0;

        $variants = ProductVariant::query()->with(['product.primaryMedia', 'product.gallery.media', 'media'])->where('workspace_id', $catalog->workspace_id)->get();
        foreach ($variants as $variant) {
            $payload = $this->feed->itemPayload($variant);
            $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
            $sync = CatalogItemSync::query()->firstOrCreate(
                ['catalog_id' => $catalog->id, 'variant_id' => $variant->id],
                ['workspace_id' => $catalog->workspace_id, 'retailer_id' => $variant->meta_retailer_id]
            );

            if ($sync->payload_hash === $hash && $sync->status === 'synced') {
                $successful++;

                continue;
            }

            $response = in_array($variant->status, ['active', 'out_of_stock'], true) && $variant->product->status === 'active'
                ? $this->meta->upsertProduct((string) $catalog->meta_catalog_id, $token, $variant->meta_retailer_id, $payload)
                : $this->meta->deleteProduct((string) $catalog->meta_catalog_id, $token, $variant->meta_retailer_id);

            $sync->increment('attempts');
            if ($response->successful()) {
                $sync->update(['retailer_id' => $variant->meta_retailer_id, 'provider_item_id' => $response->json('handles.0') ?: $sync->provider_item_id, 'payload_hash' => $hash, 'status' => 'synced', 'provider_response' => $response->json(), 'last_error' => null, 'synced_at' => now()]);
                $successful++;
            } else {
                $sync->update(['status' => 'failed', 'provider_response' => $response->json(), 'last_error' => $response->json('error.message') ?: 'Meta catalog synchronization failed.']);
                $failed++;
            }
        }

        $status = $failed === 0 ? 'completed' : 'failed';
        $summary = ['successful' => $successful, 'failed' => $failed];
        $run->update(['status' => $status, 'successful_items' => $successful, 'failed_items' => $failed, 'summary' => $summary, 'finished_at' => now(), 'last_error' => $failed ? 'One or more catalog items failed.' : null]);
        $catalog->update(['last_sync_status' => $status, 'last_sync_summary' => $summary, 'last_successful_at' => $failed === 0 ? now() : $catalog->last_successful_at, 'last_reconciled_at' => now(), 'last_error' => $failed ? 'One or more catalog items failed.' : null]);

        if ($failed > 0) {
            throw new \RuntimeException('Meta catalog synchronization completed with failed items.');
        }
    }
}
