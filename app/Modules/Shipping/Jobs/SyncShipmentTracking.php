<?php

namespace App\Modules\Shipping\Jobs;

use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Services\Carriers\CarrierApiException;
use App\Modules\Shipping\Services\TrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Reads the courier's tracking for one shipment. It only reads: it never books, cancels or pays.
 */
class SyncShipmentTracking implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 600;

    public function __construct(public int $shipmentId) {}

    public function uniqueId(): string
    {
        return (string) $this->shipmentId;
    }

    public function handle(TrackingService $tracking): void
    {
        $shipment = Shipment::query()->find($this->shipmentId);
        if (! $shipment || ! in_array($shipment->status, Shipment::TRACKABLE_STATUSES, true)) {
            return;
        }

        try {
            $tracking->sync($shipment);
        } catch (CarrierApiException) {
            // The reason is saved on the shipment and shown to staff; the next run tries again.
        }
    }
}
