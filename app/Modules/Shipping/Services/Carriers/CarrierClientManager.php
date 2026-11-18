<?php

namespace App\Modules\Shipping\Services\Carriers;

use App\Modules\Shipping\Models\CarrierAccount;
use InvalidArgumentException;

class CarrierClientManager
{
    private const CLIENTS = [
        'ups' => UpsShippingClient::class,
        'fedex' => FedexShippingClient::class,
        'aramex' => AramexShippingClient::class,
    ];

    public function for(CarrierAccount $account): CarrierRateClient
    {
        return $this->client($account);
    }

    public function tracking(CarrierAccount $account): CarrierTrackingClient
    {
        return $this->client($account);
    }

    public function booking(CarrierAccount $account): CarrierBookingClient
    {
        return $this->client($account);
    }

    public static function supports(?string $carrier): bool
    {
        return $carrier !== null && isset(self::CLIENTS[$carrier]);
    }

    private function client(CarrierAccount $account): CarrierRateClient&CarrierTrackingClient&CarrierBookingClient
    {
        if (! self::supports($account->carrier)) {
            throw new InvalidArgumentException('No API client for courier ['.$account->carrier.'].');
        }

        return app(self::CLIENTS[$account->carrier]);
    }
}
