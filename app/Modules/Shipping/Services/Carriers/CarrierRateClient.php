<?php

namespace App\Modules\Shipping\Services\Carriers;

use App\Modules\Shipping\Models\CarrierAccount;
use App\Modules\Shipping\Models\Shipment;

interface CarrierRateClient
{
    /**
     * Live prices for a shipment, one entry per service the courier offers.
     * Each entry is quotation data with a `charges` list, in the courier's billing currency.
     *
     * @return list<array<string, mixed>>
     *
     * @throws CarrierApiException
     */
    public function rates(CarrierAccount $account, Shipment $shipment): array;

    /**
     * Proves the saved credentials are accepted by the courier.
     *
     * @throws CarrierApiException
     */
    public function testConnection(CarrierAccount $account): string;
}
