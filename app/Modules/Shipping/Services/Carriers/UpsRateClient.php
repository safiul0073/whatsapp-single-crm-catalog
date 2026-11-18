<?php

namespace App\Modules\Shipping\Services\Carriers;

use App\Modules\Shipping\Models\CarrierAccount;
use App\Modules\Shipping\Models\Shipment;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * UPS Rating API (OAuth client credentials, POST /api/rating/{version}/Shop).
 * Field names follow UPS's published Rating and OAuthClientCredentials specifications.
 */
class UpsRateClient extends BaseCarrierClient
{
    public const SERVICES = [
        '01' => 'UPS Next Day Air', '02' => 'UPS 2nd Day Air', '03' => 'UPS Ground', '07' => 'UPS Worldwide Express',
        '08' => 'UPS Worldwide Expedited', '11' => 'UPS Standard', '12' => 'UPS 3 Day Select', '13' => 'UPS Next Day Air Saver',
        '14' => 'UPS Next Day Air Early', '54' => 'UPS Worldwide Express Plus', '59' => 'UPS 2nd Day Air A.M.', '65' => 'UPS Worldwide Saver',
        '71' => 'UPS Worldwide Express Freight Midday', '96' => 'UPS Worldwide Express Freight',
    ];

    public function rates(CarrierAccount $account, Shipment $shipment): array
    {
        $response = $this->authorised($account, fn (string $token): Response => $this->http()
            ->withToken($token)
            ->withHeaders(['transId' => Str::random(32), 'transactionSrc' => 'shipping-management'])
            ->acceptJson()
            ->post($this->baseUrl($account).'/api/rating/'.config('shipping_carriers.api.ups.version', 'v2409').'/Shop', $this->payload($account, $shipment)));

        $quotes = [];
        foreach (Arr::wrap($this->listOf($response->json('RateResponse.RatedShipment'))) as $rated) {
            $negotiated = Arr::get($rated, 'NegotiatedRateCharges');
            $total = Arr::get($negotiated, 'TotalCharge') ?: Arr::get($rated, 'TotalCharges');
            if (! is_array($total) || ! is_numeric($total['MonetaryValue'] ?? null)) {
                continue;
            }

            $itemised = $this->listOf(Arr::get($negotiated, 'ItemizedCharges')) ?: $this->listOf(Arr::get($rated, 'ItemizedCharges'));
            if ($itemised === []) {
                $itemised = collect($this->listOf(Arr::get($rated, 'RatedPackage')))->flatMap(fn (array $package): array => $this->listOf(Arr::get($package, 'ItemizedCharges')))->all();
            }

            $code = (string) Arr::get($rated, 'Service.Code');
            $quotes[] = $this->quoteData(
                $account,
                $shipment,
                self::SERVICES[$code] ?? (Arr::get($rated, 'Service.Description') ?: 'UPS service '.$code),
                (string) $total['MonetaryValue'],
                (string) ($total['CurrencyCode'] ?? $shipment->currency),
                array_map(fn (array $charge): array => [
                    'reason' => $this->chargeReason((string) ($charge['Description'] ?? $charge['SubType'] ?? '')),
                    'label' => trim((string) ($charge['Description'] ?? 'UPS charge').' ('.($charge['Code'] ?? '').')'),
                    'amount' => (string) ($charge['MonetaryValue'] ?? 0),
                ], $itemised),
                is_numeric(Arr::get($rated, 'BillingWeight.Weight')) ? $this->toKg((float) Arr::get($rated, 'BillingWeight.Weight'), Arr::get($rated, 'BillingWeight.UnitOfMeasurement.Code')) : null,
                $this->transit($rated),
                true,
                $code,
            );
        }

        return $quotes;
    }

    public function testConnection(CarrierAccount $account): string
    {
        $this->forgetToken($account);
        $this->token($account);

        return 'UPS accepted these credentials ('.$account->environment.').';
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CarrierAccount $account, Shipment $shipment): array
    {
        $origin = array_filter([
            'AddressLine' => array_values(array_filter([$account->origin_address_line1])),
            'City' => $account->origin_city,
            'StateProvinceCode' => $account->origin_state,
            'PostalCode' => $account->origin_postal_code,
            'CountryCode' => $account->origin_country_code,
        ]);

        return ['RateRequest' => [
            'Request' => ['RequestOption' => 'Shop', 'TransactionReference' => ['CustomerContext' => $shipment->reference]],
            'Shipment' => [
                'Shipper' => ['Name' => $account->origin_name ?: $account->name, 'ShipperNumber' => $account->account_number, 'Address' => $origin],
                'ShipFrom' => ['Name' => $account->origin_name ?: $account->name, 'Address' => $origin],
                'ShipTo' => ['Name' => $shipment->recipient_name ?: 'Customer', 'Address' => array_filter([
                    'AddressLine' => array_values(array_filter([$shipment->address_line1, $shipment->address_line2])),
                    'City' => $shipment->city,
                    'StateProvinceCode' => $shipment->state,
                    'PostalCode' => $shipment->postal_code,
                    'CountryCode' => $shipment->country_code,
                    'ResidentialAddressIndicator' => $shipment->address_type === 'residential' ? 'Y' : null,
                ])],
                'PaymentDetails' => ['ShipmentCharge' => [['Type' => '01', 'BillShipper' => ['AccountNumber' => $account->account_number]]]],
                'ShipmentRatingOptions' => ['NegotiatedRatesIndicator' => 'Y'],
                'NumOfPieces' => (string) count($this->packages($shipment)),
                'Package' => array_map(fn (array $package): array => [
                    'PackagingType' => ['Code' => '02', 'Description' => 'Package'],
                    'Dimensions' => [
                        'UnitOfMeasurement' => ['Code' => 'CM', 'Description' => 'Centimeters'],
                        'Length' => (string) $package['length_cm'],
                        'Width' => (string) $package['width_cm'],
                        'Height' => (string) $package['height_cm'],
                    ],
                    'PackageWeight' => ['UnitOfMeasurement' => ['Code' => 'KGS', 'Description' => 'Kilograms'], 'Weight' => (string) $package['weight_kg']],
                ], $this->packages($shipment)),
            ],
        ]];
    }

    /**
     * @param  callable(string): Response  $request
     */
    protected function authorised(CarrierAccount $account, callable $request): Response
    {
        $response = $this->send($account, fn (): Response => $request($this->token($account)));
        if ($response->status() === 401) {
            $this->forgetToken($account);
            $response = $this->send($account, fn (): Response => $request($this->token($account)));
        }
        if ($response->failed()) {
            throw $this->error($response);
        }

        return $response;
    }

    protected function token(CarrierAccount $account): string
    {
        return $this->cachedToken($account, function () use ($account): array {
            $response = $this->send($account, fn (): Response => $this->http()
                ->withBasicAuth((string) $account->credential('client_id'), (string) $account->credential('client_secret'))
                ->withHeaders(array_filter(['x-merchant-id' => $account->account_number]))
                ->asForm()
                ->post($this->baseUrl($account).'/security/v1/oauth/token', ['grant_type' => 'client_credentials']));

            if ($response->failed() || blank($response->json('access_token'))) {
                throw $this->error($response, 'UPS rejected the API credentials.');
            }

            return ['token' => (string) $response->json('access_token'), 'expires_in' => (int) $response->json('expires_in', 3600)];
        });
    }

    protected function error(Response $response, ?string $fallback = null): CarrierApiException
    {
        $error = Arr::first((array) $response->json('response.errors', []));

        return new CarrierApiException(
            (string) ($error['message'] ?? $fallback ?? 'UPS returned an error (HTTP '.$response->status().').'),
            isset($error['code']) ? (string) $error['code'] : null,
            $response->status(),
        );
    }

    /**
     * @param  array<string, mixed>  $rated
     */
    private function transit(array $rated): ?string
    {
        $days = Arr::get($rated, 'TimeInTransit.ServiceSummary.EstimatedArrival.BusinessDaysInTransit')
            ?? Arr::get($rated, 'GuaranteedDelivery.BusinessDaysInTransit');

        return is_numeric($days) ? ((int) $days).' business day(s)' : null;
    }

    /**
     * UPS returns one object where a list has a single entry on older versions.
     *
     * @return list<array<string, mixed>>
     */
    protected function listOf(mixed $value): array
    {
        if (! is_array($value) || $value === []) {
            return [];
        }

        return array_is_list($value) ? $value : [$value];
    }
}
