<?php

namespace App\Modules\Shipping\Services\Carriers;

use App\Modules\Shipping\Models\CarrierAccount;
use App\Modules\Shipping\Models\Shipment;
use Illuminate\Support\Carbon;
use SimpleXMLElement;

/**
 * Aramex Tracking (TrackShipments) and Shipping (CreateShipments) SOAP services.
 * Aramex offers no API call to cancel a waybill, so cancellations are done with Aramex directly.
 */
class AramexShippingClient extends AramexRateClient implements CarrierBookingClient, CarrierTrackingClient
{
    private const TRACK_ACTION = 'http://ws.aramex.net/ShippingAPI/v1/Service_1_0/TrackShipments';

    private const CREATE_ACTION = 'http://ws.aramex.net/ShippingAPI/v1/Service_1_0/CreateShipments';

    public function track(CarrierAccount $account, string $trackingNumber): array
    {
        $response = $this->soap($account, $this->serviceUrl($account, 'tracking'), self::TRACK_ACTION, '<v1:ShipmentTrackingRequest>'
            .$this->clientInfo($account)
            .$this->transaction($trackingNumber)
            .'<v1:Shipments><arr:string>'.$this->e($trackingNumber).'</arr:string></v1:Shipments>'
            .'<v1:GetLastTrackingUpdateOnly>false</v1:GetLastTrackingUpdateOnly>'
            .'</v1:ShipmentTrackingRequest>');

        $xml = $this->xml($response);
        $this->result($xml, 'ShipmentTrackingResponse', $response);

        $events = [];
        foreach ($xml->xpath('//*[local-name()="TrackingResult"]') ?: [] as $node) {
            $value = fn (string $name): string => trim((string) ($node->xpath('*[local-name()="'.$name.'"]')[0] ?? ''));
            $description = $value('UpdateDescription');
            if ($value('Comments') !== '') {
                $description .= ' — '.$value('Comments');
            }
            $events[] = $this->trackingEvent(
                $this->status($value('UpdateCode'), $description, $value('ProblemCode')),
                $value('UpdateCode'),
                $description,
                $value('UpdateLocation'),
                $this->timestamp($value('UpdateDateTime')),
            );
        }

        if ($events === []) {
            throw new CarrierApiException('Aramex has no tracking information for '.$trackingNumber.' yet.', 'NOT_FOUND', $response->status());
        }

        usort($events, fn (array $first, array $second): int => strcmp((string) $second['occurred_at'], (string) $first['occurred_at']));
        $latest = $events[0];

        return [
            'status' => $latest['status'],
            'description' => $latest['description'],
            'delivered_at' => $latest['status'] === 'delivered' ? $latest['occurred_at'] : null,
            'events' => $events,
        ];
    }

    public function book(CarrierAccount $account, Shipment $shipment): array
    {
        $response = $this->soap($account, $this->serviceUrl($account, 'shipping'), self::CREATE_ACTION, $this->createEnvelope($account, $shipment));
        $xml = $this->xml($response);
        $this->result($xml, 'ShipmentCreationResponse', $response);

        $processed = ($xml->xpath('//*[local-name()="ProcessedShipment"]') ?: [])[0] ?? null;
        if (! $processed instanceof SimpleXMLElement) {
            throw new CarrierApiException('Aramex did not return a waybill.', null, $response->status());
        }
        $value = fn (string $path): string => trim((string) ($processed->xpath($path)[0] ?? ''));

        if (strtolower($value('*[local-name()="HasErrors"]')) === 'true') {
            throw new CarrierApiException($value('.//*[local-name()="Notification"]/*[local-name()="Message"]') ?: 'Aramex rejected the shipment.', $value('.//*[local-name()="Notification"]/*[local-name()="Code"]') ?: null, $response->status());
        }

        $waybill = $value('*[local-name()="ID"]');
        if ($waybill === '') {
            throw new CarrierApiException('Aramex did not return a waybill number.', null, $response->status());
        }

        $label = $value('.//*[local-name()="ShipmentLabel"]/*[local-name()="LabelFileContents"]');

        return [
            'tracking_number' => $waybill,
            'package_tracking_numbers' => [$waybill],
            'booking_reference' => $waybill,
            'documents' => $label !== '' ? [$this->document('label', 'aramex-waybill-'.$waybill, $label, 'pdf')] : [],
            'charge' => null,
        ];
    }

    public function void(CarrierAccount $account, Shipment $shipment): string
    {
        throw new CarrierApiException('Aramex has no API call to cancel a waybill. Cancel waybill '.$shipment->booking_reference.' with Aramex directly, then confirm it here.', 'NOT_SUPPORTED');
    }

    private function serviceUrl(CarrierAccount $account, string $service): string
    {
        return rtrim((string) config('shipping_carriers.api.aramex.'.$service.'.'.($account->environment === 'production' ? 'production' : 'sandbox')), '/');
    }

    private function createEnvelope(CarrierAccount $account, Shipment $shipment): string
    {
        $shipment->loadMissing('order');
        $packages = collect($this->packages($shipment));
        $largest = $packages->sortByDesc(fn (array $package): int => $package['length_cm'] * $package['width_cm'] * $package['height_cm'])->first();
        $contact = fn (?string $name, ?string $company, ?string $phone): string => '<v1:Contact>'
            .'<v1:Department></v1:Department><v1:PersonName>'.$this->e($name).'</v1:PersonName><v1:Title></v1:Title>'
            .'<v1:CompanyName>'.$this->e($company ?: $name).'</v1:CompanyName>'
            .'<v1:PhoneNumber1>'.$this->e($phone).'</v1:PhoneNumber1><v1:PhoneNumber1Ext></v1:PhoneNumber1Ext><v1:PhoneNumber2></v1:PhoneNumber2><v1:PhoneNumber2Ext></v1:PhoneNumber2Ext>'
            .'<v1:FaxNumber></v1:FaxNumber><v1:CellPhone>'.$this->e($phone).'</v1:CellPhone><v1:EmailAddress></v1:EmailAddress><v1:Type></v1:Type>'
            .'</v1:Contact>';
        $now = now();

        return '<v1:ShipmentCreationRequest>'
            .$this->clientInfo($account)
            .$this->transaction($shipment->reference)
            .'<v1:Shipments><v1:Shipment>'
            .'<v1:Reference1>'.$this->e($shipment->reference).'</v1:Reference1><v1:Reference2></v1:Reference2><v1:Reference3></v1:Reference3>'
            .'<v1:Shipper><v1:Reference1>'.$this->e($shipment->reference).'</v1:Reference1><v1:Reference2></v1:Reference2>'
            .'<v1:AccountNumber>'.$this->e($account->account_number).'</v1:AccountNumber>'
            .$this->address('PartyAddress', $account->origin_address_line1, null, $account->origin_city, $account->origin_state, $account->origin_postal_code, $account->origin_country_code)
            .$contact($account->origin_name ?: $account->name, $account->origin_name ?: $account->name, $account->origin_phone)
            .'</v1:Shipper>'
            .'<v1:Consignee><v1:Reference1></v1:Reference1><v1:Reference2></v1:Reference2><v1:AccountNumber></v1:AccountNumber>'
            .$this->address('PartyAddress', $shipment->address_line1, $shipment->address_line2, $shipment->city, $shipment->state, $shipment->postal_code, $shipment->country_code)
            .$contact($shipment->recipient_name ?: 'Customer', null, $shipment->phone)
            .'</v1:Consignee>'
            .'<v1:ShippingDateTime>'.$now->format('Y-m-d\TH:i:s').'</v1:ShippingDateTime>'
            .'<v1:DueDate>'.$now->copy()->addDays(7)->format('Y-m-d\TH:i:s').'</v1:DueDate>'
            .'<v1:Comments></v1:Comments><v1:PickupLocation></v1:PickupLocation><v1:OperationsInstructions></v1:OperationsInstructions><v1:AccountingInstrcutions></v1:AccountingInstrcutions>'
            .'<v1:Details>'
            .'<v1:Dimensions><v1:Length>'.$largest['length_cm'].'</v1:Length><v1:Width>'.$largest['width_cm'].'</v1:Width><v1:Height>'.$largest['height_cm'].'</v1:Height><v1:Unit>CM</v1:Unit></v1:Dimensions>'
            .'<v1:ActualWeight><v1:Unit>KG</v1:Unit><v1:Value>'.round((float) $packages->sum('weight_kg'), 2).'</v1:Value></v1:ActualWeight>'
            .'<v1:ChargeableWeight><v1:Unit>KG</v1:Unit><v1:Value>'.$this->chargeableWeight($account, $shipment).'</v1:Value></v1:ChargeableWeight>'
            .'<v1:DescriptionOfGoods>'.$this->e($account->option('goods_description', 'Garments')).'</v1:DescriptionOfGoods>'
            .'<v1:GoodsOriginCountry>'.$this->e($account->origin_country_code).'</v1:GoodsOriginCountry>'
            .'<v1:NumberOfPieces>'.$packages->count().'</v1:NumberOfPieces>'
            .'<v1:ProductGroup>'.$this->e($account->option('product_group', 'EXP')).'</v1:ProductGroup>'
            .'<v1:ProductType>'.$this->e($shipment->service_code ?: $account->option('product_type', 'PPX')).'</v1:ProductType>'
            .'<v1:PaymentType>'.$this->e($account->option('payment_type', 'P')).'</v1:PaymentType>'
            .'<v1:PaymentOptions></v1:PaymentOptions>'
            .'<v1:CustomsValueAmount><v1:CurrencyCode>'.$this->e($shipment->currency).'</v1:CurrencyCode><v1:Value>'.round(max(1, (float) ($shipment->order?->total ?? 0)), 2).'</v1:Value></v1:CustomsValueAmount>'
            .'<v1:Services></v1:Services>'
            .'</v1:Details>'
            .'</v1:Shipment></v1:Shipments>'
            .'<v1:LabelInfo><v1:ReportID>'.(int) config('shipping_carriers.api.aramex.label_report_id', 9201).'</v1:ReportID><v1:ReportType>RPT</v1:ReportType></v1:LabelInfo>'
            .'</v1:ShipmentCreationRequest>';
    }

    private function status(string $code, string $description, string $problemCode): string
    {
        $code = strtoupper($code);

        return match (true) {
            $problemCode !== '' => 'exception',
            in_array($code, ['SH005', 'SH006', 'SH007'], true) => 'delivered',
            $code === 'SH003' => 'out_for_delivery',
            default => $this->statusFromText($description),
        };
    }

    /**
     * Aramex sends dates as ISO strings or as WCF "/Date(1700000000000+0400)/" values.
     */
    private function timestamp(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        if (preg_match('/Date\((-?\d+)/', $value, $match)) {
            return Carbon::createFromTimestampMs((int) $match[1])->utc()->toDateTimeString();
        }

        return rescue(fn (): string => Carbon::parse($value)->utc()->toDateTimeString(), null, false);
    }
}
