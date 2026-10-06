<?php

namespace App\Modules\Commerce\Jobs;

use App\Modules\Commerce\Models\CatalogSyncRun;
use App\Modules\Commerce\Services\CatalogSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SyncMetaCatalogJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 120;

    public int $maxExceptions = 4;

    public int $timeout = 60;

    public function __construct(public int $runId) {}

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function middleware(): array
    {
        $catalogId = CatalogSyncRun::query()->whereKey($this->runId)->value('catalog_id');

        return [(new WithoutOverlapping('meta-catalog-'.$catalogId))->releaseAfter(30)->expireAfter(90)];
    }

    public function handle(CatalogSyncService $sync): void
    {
        $run = CatalogSyncRun::query()->find($this->runId);
        if (! $run || $run->status === 'completed') {
            return;
        }

        $sync->run($run);
        if ($run->fresh()->status === 'running') {
            $this->release(30);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $run = CatalogSyncRun::query()->find($this->runId);
        if ($run && (string) data_get($run->summary, 'meta_catalog_id') === (string) $run->catalog?->meta_catalog_id) {
            $run->catalog->update(['last_sync_status' => 'failed', 'last_error' => $exception->getMessage()]);
        }
        CatalogSyncRun::query()->whereKey($this->runId)->update(['status' => 'failed', 'last_error' => $exception->getMessage(), 'finished_at' => now()]);
    }
}
