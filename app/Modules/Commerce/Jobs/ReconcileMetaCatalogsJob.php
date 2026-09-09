<?php

namespace App\Modules\Commerce\Jobs;

use App\Modules\Commerce\Models\Catalog;
use App\Modules\Commerce\Services\CatalogSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Queues a sync for every API-mode catalog that has gone stale, skipping catalogs that
 * fail readiness so one blocked catalog cannot stall reconciliation for the rest.
 */
class ReconcileMetaCatalogsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(CatalogSyncService $sync): void
    {
        Catalog::query()
            ->where('is_active', true)
            ->where('sync_mode', 'api')
            ->where(fn ($query) => $query->whereNull('last_reconciled_at')->orWhere('last_reconciled_at', '<', now()->subHours(6)))
            ->each(function (Catalog $catalog) use ($sync): bool {
                try {
                    $sync->queue($catalog);
                } catch (ValidationException $exception) {
                    $this->markBlocked($catalog, collect($exception->errors())->flatten()->implode(' '));
                } catch (\Throwable $exception) {
                    $this->markBlocked($catalog, $exception->getMessage());
                }

                return true;
            });
    }

    protected function markBlocked(Catalog $catalog, string $reason): void
    {
        $catalog->update(['last_sync_status' => 'blocked', 'last_error' => $reason]);
        Log::warning('commerce.catalog.reconcile_skipped', ['catalog_id' => $catalog->id, 'reason' => $reason]);
    }
}
