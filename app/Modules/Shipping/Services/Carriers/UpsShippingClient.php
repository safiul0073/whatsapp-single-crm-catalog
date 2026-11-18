<?php

namespace App\Modules\Shipping\Services\Carriers;

use App\Modules\Shipping\Models\CarrierAccount;
use App\Modules\Shipping\Models\Shipment;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * UPS Tracking API (GET /api/track/v1/details/{number}) and Shipping API
 * (POST /api/shipments/{version}/ship, DELETE .../void/cancel/{id}), on the same OAuth token as rating.
 */
class UpsShippingClient extends UpsRateClient implements CarrierBookingClient, CarrierTrackingClient
{
    public function track(CarrierAccount $account, string $trackingNumber): array
    {
        $response = $this->authorised($account, fn (string $token): Response => $this->http()
            ->withToken($token)
            ->withHeaders(['transId' => Str::random(32), 'transactionSrc' => 'shipping-management'])
            ->acceptJson()
            ->get($this->baseUrl($account).'/api/track/v1/details/'.rawurlencode($trackingNumber), ['locale' => 'en_US', 'returnSignature' => 'false']));

        $shipment = Arr::first($this->listOf($response->json('trackResponse.shipment')));
        $package = Arr::first($this->listOf(Arr::get((array) $shipment, 'package')));
        if (! is_array($package)) {
            $warning = Arr::get((array) $shipment, 'warnings.0.message');

            throw new CarrierApiException((string) ($warning ?: 'UPS has no tracking information for '.$trackingNumber.' yet.'), 'NOT_FOUND', $response->status());
        }

        $events = [];
        foreach ($this->listOf(Arr::get($package, 'activity')) as $activity) {
            $events[] = $this->trackingEvent(
                $this->status((array) Arr::get($activity, 'status', [])),
                Arr::get($activity, 'status.code') ?: Arr::get($activity, 'status.type'),
                (string) Arr::get($activity, 'status.description', ''),
                collect([Arr::get($activity, 'location.address.city'), Arr::get($activity, 'location.address.stateProvince'), Arr::get($activity, 'location.address.countryCode')])->filter()->implode(', '),
                $this->timestamp(Arr::get($activity, 'date'), Arr::get($activity, 'time')),
            );
        }

        $current = (array) Arr::get($package, 'currentStatus', []);
        $status = $current !== [] ? $this->status($current) : ($events[0]['status'] ?? null);
        $delivered = collect($this->listOf(Arr::get($package, 'deliveryDate')))->firstWhere('type', 'DEL');

        return [
            'status' => $status,
            'description' => Arr::get($current, 'description') ?: ($events[0]['description'] ?? null),
            'delivered_at' => $status === 'delivered' ? ($this->timestamp($delivered['date'] ?? null, Arr::get($package, 'deliveryTime.endTime')) ?? ($events[0]['occurred_at'] ?? null)) : null,
            'events' => $events,
        ];
    }

    public function book(CarrierAccount $account, Shipment $shipment): array
    {
        $response = $this->authorised($account, fn (string $token): Response => $this->http()
            ->withToken($token)
            ->withHeaders(['transId' => Str::random(32), 'transactionSrc' => 'shipping-management'])
            ->acceptJson()
            ->post($this->baseUrl($account).'/api/shipments/'.config('shipping_carriers.api.ups.ship_version', 'v2409').'/ship', $this->shipPayload($account, $shipment)));

        $results = (array) $response->json('ShipmentResponse.ShipmentResults', []);
        $packages = $this->listOf(Arr::get($results, 'PackageResults'));
        $reference = (string) Arr::get($results, 'ShipmentIdentificationNumber', '');
        $trackingNumbers = array_values(array_filter(array_map(fn (array $package): string => (string) ($package['TrackingNumber'] ?? ''), $packages)));
        if ($reference === '' && $trackingNumbers === []) {
            throw new CarrierApiException('UPS did not return a shipment number.', null, $response->status());
        }

        $documents = [];
        foreach ($packages as $index => $package) {
            $image = Arr::get($package, 'ShippingLabel.GraphicImage');
            if (is_string($image) && $image !== '') {
                $documents[] = $this->document('label', 'ups-label-'.($package['TrackingNumber'] ?? $index + 1), $image, (string) Arr::get($package, 'ShippingLabel.ImageFormat.Code', 'GIF'));
            }
        }
        $form = Arr::get($results, 'Form.Image.GraphicImage');
        if (is_string($form) && $form !== '') {
            $documents[] = $this->document('commercial_invoice', 'ups-international-forms-'.$reference, $form, (string) Arr::get($results, 'Form.Image.ImageFormat.Code', 'PDF'));
        }

        $charge = Arr::get($results, 'NegotiatedRateCharges.TotalCharge') ?: Arr::get($results, 'ShipmentCharges.TotalCharges');

        return [
            'tracking_number' => $trackingNumbers[0] ?? $reference,
            'package_tracking_numbers' => $trackingNumbers,
            'booking_reference' => $reference !== '' ? $reference : $trackingNumbers[0],
            'documents' => $documents,
            'charge' => is_array($charge) && is_numeric($charge['MonetaryValue'] ?? null) ? ['amount' => (string) $charge['MonetaryValue'], 'currency' => (string) ($charge['CurrencyCode'] ?? $shipment->currency)] : null,
        ];
    }

    public function void(CarrierAccount $account, Shipment $shipment): string
    {
        $response = $this->authorised($account, fn (string $token): Response => $this->http()
            ->withToken($token)
            ->withHeaders(['transId' => Str::random(32), 'transactionSrc' => 'shipping-management'])
            ->acceptJson()
            ->delete($this->baseUrl($account).'/api/shipments/'.config('shipping_carriers.api.ups.ship_version', 'v2409').'/void/cancel/'.rawurlencode((string) $shipment->booking_reference)));

        if ((string) $response->json('VoidShipmentResponse.SummaryResult.Status.Code') !== '1') {
            throw new CarrierApiException((string) ($response->json('VoidShipmentResponse.SummaryResult.Status.Description') ?: 'UPS did not confirm the cancellation.'), null, $response->status());
        }

        return 'UPS cancelled shipment '.$shipment->booking_reference.'.';
    }

    /**
     * @return array<string, mixed>
     */
    private function shipPayload(CarrierAccount $account, Shipment $shipment): array
    {
        $shipment->loadMissing('order');
        $origin = array_filter([
            'AddressLine' => array_values(array_filter([$account->origin_address_line1])),
            'City' => $account->origin_city,
            'StateProvinceCode' => $account->origin_state,
            'PostalCode' => $account->origin_postal_code,
            'CountryCode' => $account->origin_country_code,
        ]);
        $party = fn (array $address): array => [
            'Name' => mb_substr($account->origin_name ?: $account->name, 0, 35),
            'AttentionName' => mb_substr($account->origin_name ?: $account->name, 0, 35),
            'Phone' => ['Number' => (string) $account->origin_phone],
            'Address' => $address,
        ];
        $international = strtoupper((string) $account->origin_country_code) !== strtoupper((string) $shipment->country_code);

        $details = [
            'Description' => mb_substr((string) $account->option('goods_description', 'Garments'), 0, 50),
            'Shipper' => $party($origin) + ['ShipperNumber' => $account->account_number],
            'ShipFrom' => $party($origin),
            'ShipTo' => [
                'Name' => mb_substr((string) ($shipment->recipient_name ?: 'Customer'), 0, 35),
                'AttentionName' => mb_substr((string) ($shipment->recipient_name ?: 'Customer'), 0, 35),
                'Phone' => ['Number' => (string) $shipment->phone],
                'Address' => array_filter([
                    'AddressLine' => array_values(array_filter([$shipment->address_line1, $shipment->address_line2])),
                    'City' => $shipment->city,
                    'StateProvinceCode' => $shipment->state,
                    'PostalCode' => $shipment->postal_code,
                    'CountryCode' => $shipment->country_code,
                ]) + ($shipment->address_type === 'residential' ? ['ResidentialAddressIndicator' => ''] : []),
            ],
            'PaymentInformation' => ['ShipmentCharge' => [['Type' => '01', 'BillShipper' => ['AccountNumber' => $account->account_number]]]],
            'Service' => ['Code' => (string) $shipment->service_code],
            'ShipmentRatingOptions' => ['NegotiatedRatesIndicator' => 'Y'],
            'ReferenceNumber' => $international ? null : ['Value' => $shipment->reference],
            'Package' => array_map(fn (array $package): array => [
                'Packaging' => ['Code' => '02', 'Description' => 'Package'],
                'Dimensions' => [
                    'UnitOfMeasurement' => ['Code' => 'CM'],
                    'Length' => (string) $package['length_cm'],
                    'Width' => (string) $package['width_cm'],
                    'Height' => (string) $package['height_cm'],
                ],
                'PackageWeight' => ['UnitOfMeasurement' => ['Code' => 'KGS'], 'Weight' => (string) $package['weight_kg']],
            ], $this->packages($shipment)),
        ];
        if ($international && in_array(strtoupper((string) $shipment->country_code), ['CA', 'PR'], true) && strtoupper((string) $account->origin_country_code) === 'US') {
            $details['InvoiceLineTotal'] = ['CurrencyCode' => $shipment->currency, 'MonetaryValue' => (string) max(1, (int) ceil((float) ($shipment->order?->total ?? 0)))];
        }

        return ['ShipmentRequest' => [
            'Request' => ['RequestOption' => 'nonvalidate', 'TransactionReference' => ['CustomerContext' => $shipment->reference]],
            'Shipment' => array_filter($details, fn ($value): bool => $value !== null),
            'LabelSpecification' => ['LabelImageFormat' => ['Code' => 'GIF'], 'HTTPUserAgent' => 'Mozilla/4.5'],
        ]];
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function status(array $status): string
    {
        $type = strtoupper((string) ($status['type'] ?? ''));
        $description = (string) ($status['description'] ?? '');

        return match (true) {
            $type === 'D' => 'delivered',
            in_array($type, ['X', 'RS'], true) => 'exception',
            $type === 'M' || $type === 'MV' => 'booked',
            default => $this->statusFromText($description) === 'booked' ? 'in_transit' : $this->statusFromText($description),
        };
    }

    private function timestamp(mixed $date, mixed $time): ?string
    {
        if (! is_string($date) || ! preg_match('/^\d{8}$/', $date)) {
            return null;
        }
        $time = is_string($time) && preg_match('/^\d{6}$/', $time) ? $time : '000000';

        return Carbon::createFromFormat('YmdHis', $date.$time)?->toDateTimeString();
    }
}
