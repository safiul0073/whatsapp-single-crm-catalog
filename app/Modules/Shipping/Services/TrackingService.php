<?php

namespace App\Modules\Shipping\Services;

use App\Modules\Shipping\Models\CarrierAccount;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Models\TrackingEvent;
use App\Modules\Shipping\Services\Carriers\CarrierApiException;
use App\Modules\Shipping\Services\Carriers\CarrierClientManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reads courier tracking for booked shipments and moves them forward.
 * Any of the store's accounts for the same courier can track a number,
 * so shipments sent through an agent on UPS, FedEx or Aramex are tracked too.
 */
class TrackingService
{
    public function __construct(
        private CarrierClientManager $clients,
        private ShipmentService $shipments,
    ) {}

    /**
     * Shipments whose tracking is due for a refresh, oldest check first.
     *
     * @return Collection<int, Shipment>
     */
    public function due(?int $limit = null): Collection
    {
        $interval = (int) config('shipping_carriers.tracking.interval_minutes', 60);

        return Shipment::query()
            ->whereIn('status', Shipment::TRACKABLE_STATUSES)
            ->whereNotNull('tracking_number')
            ->whereIn('carrier', array_keys(CarrierAccount::CARRIERS))
            ->where('created_at', '>=', now()->subDays((int) config('shipping_carriers.tracking.max_age_days', 90)))
            ->where(fn ($query) => $query->whereNull('tracking_synced_at')->orWhere('tracking_synced_at', '<=', now()->subMinutes($interval)))
            ->orderByRaw('tracking_synced_at is not null')
            ->orderBy('tracking_synced_at')
            ->limit($limit ?? (int) config('shipping_carriers.tracking.batch_size', 200))
            ->get();
    }

    public function accountFor(Shipment $shipment): ?CarrierAccount
    {
        $own = $shipment->carrierAccount;
        if ($own && $own->carrier === $shipment->carrier && $own->canTrack()) {
            return $own;
        }

        return CarrierAccount::query()
            ->where('workspace_id', $shipment->workspace_id)
            ->where('carrier', $shipment->carrier)
            ->active()
            ->orderBy('environment')
            ->orderBy('id')
            ->get()
            ->first(fn (CarrierAccount $account): bool => $account->canTrack());
    }

    /**
     * @return array{new_events: int, status_changed: bool, status: string}
     *
     * @throws CarrierApiException
     */
    public function sync(Shipment $shipment): array
    {
        if (blank($shipment->tracking_number) || ! CarrierClientManager::supports($shipment->carrier)) {
            throw new CarrierApiException('Only UPS, FedEx and Aramex tracking numbers can be tracked automatically.', 'UNSUPPORTED');
        }

        $account = $this->accountFor($shipment);
        if (! $account) {
            $this->fail($shipment, 'Connect an active '.(CarrierAccount::CARRIERS[$shipment->carrier] ?? $shipment->carrier).' account with API credentials to track this shipment.');

            throw new CarrierApiException((string) $shipment->tracking_error, 'NO_ACCOUNT');
        }

        try {
            $result = $this->clients->tracking($account)->track($account, (string) $shipment->tracking_number);
        } catch (CarrierApiException $exception) {
            $this->fail($shipment, $exception->summary());

            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Shipping tracking request failed', ['shipment_id' => $shipment->id, 'carrier' => $shipment->carrier, 'error' => $exception->getMessage()]);
            $this->fail($shipment, 'Unexpected error while reading the courier tracking response.');

            throw new CarrierApiException((string) $shipment->tracking_error);
        }

        $added = 0;
        foreach ($result['events'] as $event) {
            $fingerprint = sha1($shipment->tracking_number.'|'.$event['occurred_at'].'|'.$event['code'].'|'.$event['description']);
            $created = TrackingEvent::query()->firstOrCreate(
                ['shipping_shipment_id' => $shipment->id, 'fingerprint' => $fingerprint],
                [
                    'workspace_id' => $shipment->workspace_id,
                    'tracking_number' => $shipment->tracking_number,
                    'status' => $event['status'],
                    'code' => $event['code'],
                    'description' => $event['description'],
                    'location' => $event['location'],
                    'occurred_at' => $event['occurred_at'],
                ],
            );
            $added += $created->wasRecentlyCreated ? 1 : 0;
        }

        $changed = $this->shipments->applyTrackingStatus($shipment, $result['status'], $result['description'], $result['delivered_at']);
        $shipment->forceFill([
            'tracking_status' => $result['description'] ? mb_substr((string) $result['description'], 0, 160) : $shipment->tracking_status,
            'tracking_synced_at' => now(),
            'tracking_error' => null,
        ])->save();

        return ['new_events' => $added, 'status_changed' => $changed, 'status' => $shipment->status];
    }

    private function fail(Shipment $shipment, string $message): void
    {
        $shipment->forceFill(['tracking_synced_at' => now(), 'tracking_error' => mb_substr($message, 0, 255)])->save();
    }
}
