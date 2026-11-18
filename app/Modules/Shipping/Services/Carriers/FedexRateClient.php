<?php

namespace App\Modules\Shipping\Services\Carriers;

use App\Modules\Shipping\Models\CarrierAccount;
use App\Modules\Shipping\Models\Shipment;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;

/**
 * FedEx Rates and Transit Times API (POST /oauth/token, POST /rate/v1/rates/quotes).
 * Field names follow FedEx's published OpenAPI document for the Rate API.
 */
class FedexRateClient extends BaseCarrierClient
{
    public const PICKUP_TYPES = [
        'DROPOFF_AT_FEDEX_LOCATION' => 'Drop off at a FedEx location',
        'USE_SCHEDULED_PICKUP' => 'Regular scheduled pickup',
        'CONTACT_FEDEX_TO_SCHEDULE' => 'Contact FedEx to schedule',
    ];

    public function rates(CarrierAccount $account, Shipment $shipment): array
    {
        $response = $this->send($account, fn (): Response => $this->quote($account, $shipment));
        if ($response->status() === 401) {
            $this->forgetToken($account);
            $response = $this->send($account, fn (): Response => $this->quote($account, $shipment));
        }
        if ($response->failed()) {
            throw $this->error($response);
        }

        $quotes = [];
        foreach ((array) $response->json('output.rateReplyDetails', []) as $reply) {
            $details = collect((array) Arr::get($reply, 'ratedShipmentDetails', []));
            $detail = $details->firstWhere('rateType', 'ACCOUNT') ?? $details->first();
            if (! is_array($detail) || ! is_numeric($detail['totalNetCharge'] ?? null)) {
                continue;
            }

            $rate = (array) Arr::get($detail, 'shipmentRateDetail', []);
            $quotes[] = $this->quoteData(
                $account,
                $shipment,
                (string) (Arr::get($reply, 'serviceName') ?: str((string) Arr::get($reply, 'serviceType'))->replace('_', ' ')->title()),
                (string) $detail['totalNetCharge'],
                (string) ($rate['currency'] ?? Arr::get($detail, 'currency') ?? $shipment->currency),
                array_map(fn (array $surcharge): array => [
                    'reason' => $this->chargeReason((string) ($surcharge['type'] ?? $surcharge['description'] ?? '')),
                    'label' => (string) ($surcharge['description'] ?? str((string) ($surcharge['type'] ?? 'FedEx surcharge'))->replace('_', ' ')->title()),
                    'amount' => (string) ($surcharge['amount'] ?? 0),
                ], array_values(array_filter((array) ($rate['surCharges'] ?? []), 'is_array'))),
                is_numeric(Arr::get($rate, 'totalBillingWeight.value')) ? $this->toKg((float) Arr::get($rate, 'totalBillingWeight.value'), Arr::get($rate, 'totalBillingWeight.units')) : null,
                $this->transit($reply),
                true,
                (string) Arr::get($reply, 'serviceType'),
            );
        }

        return $quotes;
    }

    public function testConnection(CarrierAccount $account): string
    {
        $this->forgetToken($account);
        $this->token($account);

        return 'FedEx accepted these credentials ('.$account->environment.').';
    }

    private function quote(CarrierAccount $account, Shipment $shipment): Response
    {
        return $this->http()
            ->withToken($this->token($account))
            ->withHeaders(['x-locale' => 'en_US', 'x-customer-transaction-id' => $shipment->reference])
            ->asJson()
            ->acceptJson()
            ->post($this->baseUrl($account).'/rate/v1/rates/quotes', $this->payload($account, $shipment));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CarrierAccount $account, Shipment $shipment): array
    {
        $shipment->loadMissing('order');
        $packages = collect($this->packages($shipment));
        $international = strtoupper((string) $account->origin_country_code) !== strtoupper((string) $shipment->country_code);

        $requested = [
            'shipper' => ['address' => array_filter([
                'streetLines' => array_values(array_filter([$account->origin_address_line1])),
                'city' => $account->origin_city,
                'stateOrProvinceCode' => $account->origin_state,
                'postalCode' => $account->origin_postal_code,
                'countryCode' => $account->origin_country_code,
            ])],
            'recipient' => ['address' => array_filter([
                'streetLines' => array_values(array_filter([$shipment->address_line1, $shipment->address_line2])),
                'city' => $shipment->city,
                'stateOrProvinceCode' => in_array($shipment->country_code, ['US', 'CA', 'PR'], true) ? $shipment->state : null,
                'postalCode' => $shipment->postal_code,
                'countryCode' => $shipment->country_code,
            ]) + ['residential' => $shipment->address_type === 'residential']],
            'shipDateStamp' => now()->toDateString(),
            'pickupType' => $account->option('pickup_type', 'DROPOFF_AT_FEDEX_LOCATION'),
            'rateRequestType' => ['ACCOUNT', 'LIST'],
            'packagingType' => 'YOUR_PACKAGING',
            'totalPackageCount' => $packages->count(),
            'requestedPackageLineItems' => $packages->map(fn (array $package): array => [
                'groupPackageCount' => 1,
                'weight' => ['units' => 'KG', 'value' => $package['weight_kg']],
                'dimensions' => ['length' => $package['length_cm'], 'width' => $package['width_cm'], 'height' => $package['height_cm'], 'units' => 'CM'],
            ])->all(),
        ];

        if ($international) {
            $requested['customsClearanceDetail'] = [
                'dutiesPayment' => ['paymentType' => 'SENDER'],
                'commodities' => [[
                    'description' => $account->option('goods_description', 'Garments'),
                    'quantity' => max(1, (int) $shipment->total_pieces),
                    'quantityUnits' => 'PCS',
                    'weight' => ['units' => 'KG', 'value' => round((float) $packages->sum('weight_kg'), 2)],
                    'customsValue' => ['amount' => round(max(1, (float) ($shipment->order?->total ?? 0)), 2), 'currency' => $shipment->currency],
                ]],
            ];
        }

        return [
            'accountNumber' => ['value' => $account->account_number],
            'rateRequestControlParameters' => ['returnTransitTimes' => true],
            'requestedShipment' => $requested,
        ];
    }

    protected function token(CarrierAccount $account): string
    {
        return $this->cachedToken($account, function () use ($account): array {
            $response = $this->send($account, fn (): Response => $this->http()->asForm()->post($this->baseUrl($account).'/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $account->credential('client_id'),
                'client_secret' => $account->credential('client_secret'),
            ]));

            if ($response->failed() || blank($response->json('access_token'))) {
                throw $this->error($response, 'FedEx rejected the API credentials.');
            }

            return ['token' => (string) $response->json('access_token'), 'expires_in' => (int) $response->json('expires_in', 3600)];
        });
    }

    protected function error(Response $response, ?string $fallback = null): CarrierApiException
    {
        $error = Arr::first((array) $response->json('errors', []));
        $message = match (true) {
            $response->status() === 429 => 'FedEx is limiting requests right now. Wait a little and try again.',
            is_array($error) && filled($error['message'] ?? null) => (string) $error['message'],
            default => $fallback ?? 'FedEx returned an error (HTTP '.$response->status().').',
        };

        return new CarrierApiException($message, is_array($error) && isset($error['code']) ? (string) $error['code'] : null, $response->status());
    }

    /**
     * @param  array<string, mixed>  $reply
     */
    private function transit(array $reply): ?string
    {
        $value = Arr::get($reply, 'commit.daysInTransit') ?: Arr::get($reply, 'operationalDetail.transitTime');
        if (blank($value) || $value === 'UNKNOWN') {
            $date = Arr::get($reply, 'operationalDetail.deliveryDate') ?: Arr::get($reply, 'operationalDetail.commitDate');

            return filled($date) ? 'By '.substr((string) $date, 0, 10) : null;
        }

        return str((string) $value)->replace('_', ' ')->lower()->toString();
    }
}
