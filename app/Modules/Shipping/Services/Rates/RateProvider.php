<?php

namespace App\Modules\Shipping\Services\Rates;

use App\Modules\Shipping\Models\Shipment;

/**
 * A source of shipping prices. Agent rate charts implement it today; live courier
 * APIs (UPS, FedEx, Aramex) plug in here by adding a class to the config list.
 */
interface RateProvider
{
    /**
     * Quotation attributes ready to be stored, each with a `charges` list.
     *
     * @return list<array<string, mixed>>
     */
    public function quotes(Shipment $shipment): array;
}
