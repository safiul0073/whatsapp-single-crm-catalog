<?php

namespace App\Modules\Shipping\Services\Carriers;

use App\Modules\Shipping\Models\CarrierAccount;

interface CarrierTrackingClient
{
    /**
     * The courier's scan history for one tracking number, newest first where the courier sorts it.
     * Statuses are booked, in_transit, out_for_delivery, exception or delivered.
     *
     * @return array{status: ?string, description: ?string, delivered_at: ?string, events: list<array{status: string, code: ?string, description: string, location: ?string, occurred_at: ?string}>}
     *
     * @throws CarrierApiException
     */
    public function track(CarrierAccount $account, string $trackingNumber): array;
}
