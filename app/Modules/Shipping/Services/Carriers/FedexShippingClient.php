<?php

namespace App\Modules\Shipping\Services\Carriers;

use App\Modules\Shipping\Models\CarrierAccount;
use App\Modules\Shipping\Models\Shipment;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * FedEx Track API (POST /track/v1/trackingnumbers) and Ship API
 * (POST /ship/v1/shipments, PUT /ship/v1/shipments/cancel). The FedEx developer
 * project behind the credentials must include these APIs as well as Rates.
 */
class FedexShippingClient extends FedexRateClient implements CarrierBookingClient, CarrierTrackingClient
{
    private const EXCEPTION_CODES = ['DE', 'SE', 'DY', 'CD', 'RS', 'CA', 'HP', 'DD'];

    public function track(CarrierAccount $account, string $trackingNumber): array
    {
        $response = $this->call($account, 'post', '/track/v1/trackingnumbers', [
            'includeDetailedScans' => true,
            'trackingInfo' => [['trackingNumberInfo' => ['trackingNumber' => $trackingNumber]]],
        ]);

        $result = (array) $response->json('output.completeTrackResults.0.trackResults.0', []);
        if (filled(Arr::get($result, 'error.code'))) {
            throw new CarrierApiException((string) (Arr::get($result, 'error.message') ?: 'FedEx has no tracking information for '.$trackingNumber.' yet.'), (string) Arr::get($result, 'error.code'), $response->status());
        }
        if ($result === []) {
            throw new CarrierApiException('FedEx has no tracking information for '.$trackingNumber.' yet.', 'NOT_FOUND', $response->status());
        }

        $events = [];
        foreach ((array) Arr::get($result, 'scanEvents', []) as $scan) {
            if (! is_array($scan)) {
                continue;
            }
            $description = (string) ($scan['eventDescription'] ?? '');
            if (filled($scan['exceptionDescription'] ?? null)) {
                $description .= ' — '.$scan['exceptionDescription'];
            }
            $events[] = $this->trackingEvent(
                $this->status((string) ($scan['derivedStatusCode'] ?? $scan['eventType'] ?? ''), $description),
                $scan['eventType'] ?? null,
                $description,
                collect([Arr::get($scan, 'scanLocation.city'), Arr::get($scan, 'scanLocation.stateOrProvinceCode'), Arr::get($scan, 'scanLocation.countryCode')])->filter()->implode(', '),
                $this->timestamp($scan['date'] ?? null),
            );
        }

        $latest = (array) Arr::get($result, 'latestStatusDetail', []);
        $status = $latest !== []
            ? $this->status((string) ($latest['derivedCode'] ?? $latest['code'] ?? ''), (string) ($latest['description'] ?? $latest['statusByLocale'] ?? ''))
            : ($events[0]['status'] ?? null);
        $delivered = collect((array) Arr::get($result, 'dateAndTimes', []))->firstWhere('type', 'ACTUAL_DELIVERY');

        return [
            'status' => $status,
            'description' => ($latest['description'] ?? $latest['statusByLocale'] ?? null) ?: ($events[0]['description'] ?? null),
            'delivered_at' => $status === 'delivered' ? ($this->timestamp($delivered['dateTime'] ?? null) ?? ($events[0]['occurred_at'] ?? null)) : null,
            'events' => $events,
        ];
    }

    public function book(CarrierAccount $account, Shipment $shipment): array
    {
        $response = $this->call($account, 'post', '/ship/v1/shipments', $this->shipPayload($account, $shipment), $shipment->reference);
        $transaction = (array) $response->json('output.transactionShipments.0', []);
        $pieces = array_values(array_filter((array) Arr::get($transaction, 'pieceResponses', []), 'is_array'));
        $master = (string) (Arr::get($transaction, 'masterTrackingNumber') ?: Arr::get($pieces, '0.trackingNumber', ''));
        if ($master === '') {
            throw new CarrierApiException('FedEx did not return a tracking number.', null, $response->status());
        }

        $documents = [];
        foreach ($pieces as $index => $piece) {
            foreach ((array) ($piece['packageDocuments'] ?? []) as $document) {
                if (filled($document['encodedLabel'] ?? null)) {
                    $documents[] = $this->document('label', 'fedex-label-'.($piece['trackingNumber'] ?? $index + 1), (string) $document['encodedLabel'], (string) ($document['docType'] ?? 'PDF'));
                }
            }
        }
        foreach ((array) Arr::get($transaction, 'shipmentDocuments', []) as $document) {
            if (is_array($document) && filled($document['encodedLabel'] ?? null)) {
                $type = str_contains(strtoupper((string) ($document['contentType'] ?? '')), 'INVOICE') ? 'commercial_invoice' : 'customs';
                $documents[] = $this->document($type, 'fedex-'.str_replace('_', '-', $type).'-'.$master, (string) $document['encodedLabel'], (string) ($document['docType'] ?? 'PDF'));
            }
        }

        $rate = (array) Arr::get($transaction, 'completedShipmentDetail.shipmentRating.shipmentRateDetails.0', []);

        return [
            'tracking_number' => $master,
            'package_tracking_numbers' => array_values(array_filter(array_map(fn (array $piece): string => (string) ($piece['trackingNumber'] ?? ''), $pieces))),
            'booking_reference' => $master,
            'documents' => $documents,
            'charge' => is_numeric($rate['totalNetCharge'] ?? null) ? ['amount' => (string) $rate['totalNetCharge'], 'currency' => (string) ($rate['currency'] ?? $shipment->currency)] : null,
        ];
    }

    public function void(CarrierAccount $account, Shipment $shipment): string
    {
        $response = $this->call($account, 'put', '/ship/v1/shipments/cancel', [
            'accountNumber' => ['value' => $account->account_number],
            'emailShipment' => false,
            'senderCountryCode' => $account->origin_country_code,
            'deletionControl' => 'DELETE_ALL_PACKAGES',
            'trackingNumber' => $shipment->booking_reference,
        ]);

        if ($response->json('output.cancelledShipment') !== true) {
            throw new CarrierApiException((string) ($response->json('output.message') ?: 'FedEx did not confirm the cancellation.'), null, $response->status());
        }

        return 'FedEx cancelled shipment '.$shipment->booking_reference.'.';
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function call(CarrierAccount $account, string $method, string $path, array $body, ?string $transactionId = null): Response
    {
        $request = fn (): Response => $this->http()
            ->withToken($this->token($account))
            ->withHeaders(array_filter(['x-locale' => 'en_US', 'x-customer-transaction-id' => $transactionId]))
            ->asJson()
            ->acceptJson()
            ->{$method}($this->baseUrl($account).$path, $body);

        $response = $this->send($account, $request);
        if ($response->status() === 401) {
            $this->forgetToken($account);
            $response = $this->send($account, $request);
        }
        if ($response->failed()) {
            throw $this->error($response);
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function shipPayload(CarrierAccount $account, Shipment $shipment): array
    {
        $shipment->loadMissing('order');
        $packages = collect($this->packages($shipment));
        $international = strtoupper((string) $account->origin_country_code) !== strtoupper((string) $shipment->country_code);
        $customsValue = round(max(1, (float) ($shipment->order?->total ?? 0)), 2);
        $pieces = max(1, (int) $shipment->total_pieces);

        $requested = [
            'shipper' => [
                'contact' => ['personName' => $account->origin_name ?: $account->name, 'phoneNumber' => $account->origin_phone, 'companyName' => $account->origin_name ?: $account->name],
                'address' => array_filter([
                    'streetLines' => array_values(array_filter([$account->origin_address_line1])),
                    'city' => $account->origin_city,
                    'stateOrProvinceCode' => $account->origin_state,
                    'postalCode' => $account->origin_postal_code,
                    'countryCode' => $account->origin_country_code,
                ]),
            ],
            'recipients' => [[
                'contact' => ['personName' => $shipment->recipient_name ?: 'Customer', 'phoneNumber' => $shipment->phone],
                'address' => array_filter([
                    'streetLines' => array_values(array_filter([$shipment->address_line1, $shipment->address_line2])),
                    'city' => $shipment->city,
                    'stateOrProvinceCode' => in_array($shipment->country_code, ['US', 'CA', 'PR'], true) ? $shipment->state : null,
                    'postalCode' => $shipment->postal_code,
                    'countryCode' => $shipment->country_code,
                ]) + ['residential' => $shipment->address_type === 'residential'],
            ]],
            'shipDatestamp' => now()->toDateString(),
            'serviceType' => $shipment->service_code,
            'packagingType' => 'YOUR_PACKAGING',
            'pickupType' => $account->option('pickup_type', 'DROPOFF_AT_FEDEX_LOCATION'),
            'shippingChargesPayment' => ['paymentType' => 'SENDER', 'payor' => ['responsibleParty' => ['accountNumber' => ['value' => $account->account_number]]]],
            'labelSpecification' => ['imageType' => 'PDF', 'labelStockType' => 'PAPER_85X11_TOP_HALF_LABEL'],
            'requestedPackageLineItems' => $packages->map(fn (array $package, int $index): array => [
                'sequenceNumber' => $index + 1,
                'weight' => ['units' => 'KG', 'value' => $package['weight_kg']],
                'dimensions' => ['length' => $package['length_cm'], 'width' => $package['width_cm'], 'height' => $package['height_cm'], 'units' => 'CM'],
                'customerReferences' => [['customerReferenceType' => 'CUSTOMER_REFERENCE', 'value' => $shipment->reference]],
            ])->values()->all(),
        ];

        if ($international) {
            $requested['customsClearanceDetail'] = [
                'dutiesPayment' => ['paymentType' => 'SENDER'],
                'isDocumentOnly' => false,
                'commodities' => [[
                    'description' => $account->option('goods_description', 'Garments'),
                    'countryOfManufacture' => $account->origin_country_code,
                    'quantity' => $pieces,
                    'quantityUnits' => 'PCS',
                    'unitPrice' => ['amount' => round($customsValue / $pieces, 2), 'currency' => $shipment->currency],
                    'customsValue' => ['amount' => $customsValue, 'currency' => $shipment->currency],
                    'weight' => ['units' => 'KG', 'value' => round((float) $packages->sum('weight_kg'), 2)],
                ]],
            ];
            $requested['shippingDocumentSpecification'] = [
                'shippingDocumentTypes' => ['COMMERCIAL_INVOICE'],
                'commercialInvoiceDetail' => ['documentFormat' => ['stockType' => 'PAPER_LETTER', 'docType' => 'PDF']],
            ];
        }

        return [
            'labelResponseOptions' => 'LABEL',
            'accountNumber' => ['value' => $account->account_number],
            'requestedShipment' => $requested,
        ];
    }

    private function status(string $code, string $description): string
    {
        $code = strtoupper($code);

        return match (true) {
            $code === 'DL' => 'delivered',
            $code === 'OD' => 'out_for_delivery',
            in_array($code, self::EXCEPTION_CODES, true) => 'exception',
            in_array($code, ['OC', 'IN'], true) => 'booked',
            default => in_array($this->statusFromText($description), ['exception', 'delivered', 'out_for_delivery'], true) ? $this->statusFromText($description) : 'in_transit',
        };
    }

    private function timestamp(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return rescue(fn (): string => Carbon::parse($value)->utc()->toDateTimeString(), null, false);
    }
}
