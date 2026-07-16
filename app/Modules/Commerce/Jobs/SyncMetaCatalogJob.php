<?php

namespace App\Modules\Commerce\Jobs;

use App\Modules\Commerce\Models\CatalogSyncRun;
use App\Modules\Commerce\Services\CatalogSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncMetaCatalogJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public function __construct(public int $runId) {}

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(CatalogSyncService $sync): void
    {
        $run = CatalogSyncRun::query()->find($this->runId);
        if (! $run || $run->status === 'completed') {
            return;
        }

        $sync->run($run);
    }

    public function failed(\Throwable $exception): void
    {
        CatalogSyncRun::query()->whereKey($this->runId)->update(['status' => 'failed', 'last_error' => $exception->getMessage(), 'finished_at' => now()]);
    }
}
