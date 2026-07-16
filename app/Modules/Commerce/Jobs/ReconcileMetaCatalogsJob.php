<?php

namespace App\Modules\Commerce\Jobs;

use App\Modules\Commerce\Models\Catalog;
use App\Modules\Commerce\Services\CatalogSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
            ->each(fn (Catalog $catalog) => $sync->queue($catalog));
    }
}
