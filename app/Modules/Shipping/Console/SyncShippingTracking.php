<?php

namespace App\Modules\Shipping\Console;

use App\Modules\Shipping\Jobs\SyncShipmentTracking;
use App\Modules\Shipping\Services\TrackingService;
use Illuminate\Console\Command;

class SyncShippingTracking extends Command
{
    protected $signature = 'shipping:sync-tracking {--limit= : Most shipments to check in this run}';

    protected $description = 'Queue a courier tracking check for booked UPS, FedEx and Aramex shipments that are due';

    public function handle(TrackingService $tracking): int
    {
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $due = $tracking->due($limit);
        foreach ($due as $shipment) {
            SyncShipmentTracking::dispatch($shipment->id);
        }

        $this->info("Queued {$due->count()} tracking check(s).");

        return self::SUCCESS;
    }
}
