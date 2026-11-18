<?php

namespace App\Modules\Shipping\Services\Carriers;

use App\Modules\Shipping\Models\CarrierAccount;
use App\Modules\Shipping\Models\Shipment;

interface CarrierBookingClient
{
    /**
     * Creates a real, billable shipment with the courier and returns its labels.
     * Only BookingService may call this, after a person has confirmed the booking.
     *
     * @return array{tracking_number: string, package_tracking_numbers: list<string>, booking_reference: string, documents: list<array{type: string, name: string, content: string, mime: string}>, charge: ?array{amount: string, currency: string}}
     *
     * @throws CarrierApiException
     */
    public function book(CarrierAccount $account, Shipment $shipment): array;

    /**
     * Cancels a booking with the courier. Only BookingService may call this, after the owner approved it.
     *
     * @throws CarrierApiException
     */
    public function void(CarrierAccount $account, Shipment $shipment): string;
}
