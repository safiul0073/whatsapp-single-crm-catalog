<?php

namespace App\Modules\Shipping\Services;

use App\Models\User;
use App\Modules\Shipping\Models\CarrierAccount;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Services\Carriers\CarrierApiException;
use App\Modules\Shipping\Services\Carriers\CarrierClientManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Books approved shipments with the courier and cancels them. Every call to a courier
 * here creates or cancels a real charge, so each one needs a person's explicit confirmation:
 * booking needs `shipping.bookings.create`, cancelling needs the owner-level `shipping.bookings.void`.
 */
class BookingService
{
    public function __construct(
        private CarrierClientManager $clients,
        private ShipmentService $shipments,
        private ShippingDocumentService $documents,
        private ShippingAuditService $audit,
        private ShippingAlertService $alerts,
    ) {}

    /**
     * Plain-language reasons this shipment cannot be booked through the courier API yet.
     *
     * @return list<string>
     */
    public function blockers(Shipment $shipment): array
    {
        $shipment->loadMissing(['carrierAccount', 'cartons']);
        $account = $shipment->carrierAccount;
        $blockers = [];

        if ($shipment->approved_quote_id === null) {
            $blockers[] = __('Approve a quotation first.');
        }
        if ($shipment->shipping_agent_id !== null || ! $account) {
            $blockers[] = __('Only shipments approved on one of your own courier accounts can be booked through the API. Agent shipments are booked by the agent.');
        }
        if ($account && ! CarrierClientManager::supports($account->carrier)) {
            $blockers[] = __('This courier has no booking API.');
        }
        if ($account && ($gaps = $account->bookingGaps()) !== []) {
            $blockers[] = __('Courier account :name still needs: :gaps.', ['name' => $account->name, 'gaps' => implode(', ', $gaps)]);
        }
        if (blank($shipment->service_code)) {
            $blockers[] = __('The approved quotation has no courier service code. Request live rates and approve a courier API rate.');
        }
        if ($shipment->cartons->isEmpty()) {
            $blockers[] = __('Add the cartons first.');
        }
        if (! $shipment->hasCompleteAddress()) {
            $blockers[] = __('Complete the destination address, including the phone number.');
        }
        if (filled($shipment->tracking_number) || filled($shipment->booking_reference)) {
            $blockers[] = __('This shipment already has a tracking number.');
        }
        if (in_array($shipment->status, ['delivered', 'closed', 'cancelled'], true)) {
            $blockers[] = __('This shipment is :status.', ['status' => strtolower($shipment->statusLabel())]);
        }

        return $blockers;
    }

    public function book(Shipment $shipment, User $user, bool $confirmed): Shipment
    {
        if (! $confirmed) {
            throw ValidationException::withMessages(['booking' => __('Tick the confirmation box: booking creates a real, billable shipment with the courier.')]);
        }

        $lock = Cache::lock('shipping:book:'.$shipment->id, 120);
        if (! $lock->get()) {
            throw ValidationException::withMessages(['booking' => __('This shipment is already being booked. Refresh the page in a moment.')]);
        }

        try {
            $shipment->refresh();
            if (($blockers = $this->blockers($shipment)) !== []) {
                throw ValidationException::withMessages(['booking' => implode(' ', $blockers)]);
            }

            /** @var CarrierAccount $account */
            $account = $shipment->carrierAccount;
            try {
                $booking = $this->clients->booking($account)->book($account, $shipment);
            } catch (CarrierApiException $exception) {
                $this->audit->record($shipment->workspace_id, 'booking.failed', 'Booking with '.$account->carrierLabel().' failed for '.$shipment->reference.': '.$exception->summary(), $shipment, null, null, $user->id);

                throw ValidationException::withMessages(['booking' => __(':courier did not book the shipment: :reason', ['courier' => $account->carrierLabel(), 'reason' => $exception->summary()])]);
            } catch (Throwable $exception) {
                Log::error('Shipping booking failed', ['shipment_id' => $shipment->id, 'carrier' => $account->carrier, 'error' => $exception->getMessage()]);
                $this->audit->record($shipment->workspace_id, 'booking.failed', 'Booking with '.$account->carrierLabel().' failed for '.$shipment->reference.': unexpected response', $shipment, null, null, $user->id);

                throw ValidationException::withMessages(['booking' => __('The courier response could not be read. Check the courier portal before trying again, so the shipment is not booked twice.')]);
            }

            $shipment->forceFill([
                'booking_source' => 'api',
                'booking_reference' => mb_substr($booking['booking_reference'], 0, 80),
                'package_tracking_numbers' => $booking['package_tracking_numbers'],
                'booked_by' => $user->id,
                'booked_at' => now(),
            ])->save();

            foreach ($booking['documents'] as $document) {
                if ($document['content'] !== '') {
                    $this->documents->storeContent($shipment, $document['type'], $document['name'], $document['content'], $document['mime'], $user);
                }
            }

            $this->shipments->recordBooking($shipment, ['tracking_number' => mb_substr($booking['tracking_number'], 0, 80), 'status' => 'booked'], $user);

            $charge = $booking['charge'];
            $this->audit->record(
                $shipment->workspace_id,
                'booking.created',
                'Booked with '.$account->carrierLabel().' ('.$account->environment.'): '.$shipment->booking_reference.($charge ? ', courier charge '.$charge['amount'].' '.$charge['currency'] : ''),
                $shipment,
                null,
                ['booking_reference' => $shipment->booking_reference, 'tracking_numbers' => $booking['package_tracking_numbers'], 'charge' => $charge],
                $user->id,
            );

            return $shipment->refresh();
        } finally {
            $lock->release();
        }
    }

    public function requestVoid(Shipment $shipment, User $user, string $reason): Shipment
    {
        if (! $shipment->wasBookedThroughApi() || $shipment->voided_at !== null) {
            throw ValidationException::withMessages(['void' => __('Only bookings made through the courier API can be cancelled here.')]);
        }
        if ($shipment->status !== 'booked') {
            throw ValidationException::withMessages(['void' => __('The courier has already picked this shipment up, so the booking can no longer be cancelled.')]);
        }

        $shipment->update(['void_requested_by' => $user->id, 'void_requested_at' => now(), 'void_reason' => mb_substr($reason, 0, 255)]);
        $this->audit->record($shipment->workspace_id, 'booking.void_requested', 'Cancellation requested for '.$shipment->reference.': '.$reason, $shipment, null, ['reason' => $reason], $user->id);
        $this->alerts->notify($shipment, __('Booking cancellation needs approval — :reference', ['reference' => $shipment->reference]), $user->name.': '.$reason, 'shipping.bookings.void');

        return $shipment;
    }

    /**
     * Cancels the booking with the courier. When the courier has no cancel API (or it was
     * already cancelled in the courier portal) the approver confirms that instead.
     */
    public function approveVoid(Shipment $shipment, User $user, bool $alreadyCancelledWithCourier = false): Shipment
    {
        if (! $shipment->hasPendingVoidRequest()) {
            throw ValidationException::withMessages(['void' => __('There is no cancellation request for this shipment.')]);
        }
        if ($shipment->status !== 'booked') {
            throw ValidationException::withMessages(['void' => __('The courier has already picked this shipment up, so the booking can no longer be cancelled.')]);
        }

        $message = __('Marked as cancelled with the courier directly.');
        if (! $alreadyCancelledWithCourier) {
            $account = $shipment->carrierAccount;
            if (! $account) {
                throw ValidationException::withMessages(['void' => __('The courier account for this booking was removed. Cancel it in the courier portal, then confirm that here.')]);
            }
            try {
                $message = $this->clients->booking($account)->void($account, $shipment);
            } catch (CarrierApiException $exception) {
                $this->audit->record($shipment->workspace_id, 'booking.void_failed', 'Cancellation failed for '.$shipment->reference.': '.$exception->summary(), $shipment, null, null, $user->id);

                throw ValidationException::withMessages(['void' => $exception->summary()]);
            }
        }

        $shipment->update(['voided_by' => $user->id, 'voided_at' => now()]);
        $this->shipments->setStatus($shipment, 'cancelled', $user);
        $this->audit->record($shipment->workspace_id, 'booking.voided', 'Booking '.$shipment->booking_reference.' cancelled: '.$message, $shipment, null, ['reason' => $shipment->void_reason, 'already_cancelled' => $alreadyCancelledWithCourier], $user->id);

        return $shipment;
    }

    public function rejectVoid(Shipment $shipment, User $user): Shipment
    {
        if (! $shipment->hasPendingVoidRequest()) {
            throw ValidationException::withMessages(['void' => __('There is no cancellation request for this shipment.')]);
        }

        $reason = $shipment->void_reason;
        $shipment->update(['void_requested_by' => null, 'void_requested_at' => null, 'void_reason' => null]);
        $this->audit->record($shipment->workspace_id, 'booking.void_rejected', 'Cancellation request declined for '.$shipment->reference, $shipment, ['reason' => $reason], null, $user->id);

        return $shipment;
    }
}
