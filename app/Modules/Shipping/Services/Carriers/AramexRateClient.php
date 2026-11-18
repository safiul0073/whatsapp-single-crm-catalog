<?php

namespace App\Modules\Shipping\Services\Carriers;

use App\Modules\Shipping\Models\CarrierAccount;
use App\Modules\Shipping\Models\Shipment;
use Illuminate\Http\Client\Response;
use SimpleXMLElement;

/**
 * Aramex Rate Calculator (SOAP 1.1, CalculateRate). Element names and their order
 * follow the service WSDL. Aramex returns a total with coded additional charges and
 * no remote-area flag, so its remote status stays unknown unless a charge names it.
 */
class AramexRateClient extends BaseCarrierClient
{
    protected const NAMESPACE = 'http://ws.aramex.net/ShippingAPI/v1/';

    private const ACTION = 'http://ws.aramex.net/ShippingAPI/v1/Service_1_0/CalculateRate';

    public function rates(CarrierAccount $account, Shipment $shipment): array
    {
        $response = $this->soap($account, $this->baseUrl($account), self::ACTION, $this->envelope($account, $shipment));
        $result = $this->result($this->xml($response), 'RateCalculatorResponse', $response);
        $total = $result->TotalAmount ?? null;
        if ($total === null || ! is_numeric((string) $total->Value) || (float) $total->Value <= 0) {
            throw new CarrierApiException('Aramex returned no price for this shipment.', 'NO_RATE', $response->status());
        }

        $charges = [];
        foreach ($result->AdditionalCharges->AdditionalCharge ?? [] as $charge) {
            $code = trim((string) $charge->Code);
            $charges[] = ['reason' => $this->chargeReason($code), 'label' => 'Aramex charge '.$code, 'amount' => (string) $charge->Amount];
        }

        return [$this->quoteData(
            $account,
            $shipment,
            'Aramex '.$account->option('product_group', 'EXP').' / '.$account->option('product_type', 'PPX'),
            (string) $total->Value,
            (string) ($total->CurrencyCode ?: $shipment->currency),
            $charges,
            $this->chargeableWeight($account, $shipment),
            null,
            false,
            $account->option('product_type', 'PPX'),
        )];
    }

    public function testConnection(CarrierAccount $account): string
    {
        return 'Aramex has no separate sign-in check. These credentials are tested the first time rates are requested for a shipment.';
    }

    /**
     * Posts a SOAP 1.1 envelope built from the given body.
     */
    protected function soap(CarrierAccount $account, string $url, string $action, string $body): Response
    {
        $envelope = '<?xml version="1.0" encoding="utf-8"?>'
            .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:v1="'.self::NAMESPACE.'" xmlns:arr="http://schemas.microsoft.com/2003/10/Serialization/Arrays"><soapenv:Body>'
            .$body
            .'</soapenv:Body></soapenv:Envelope>';

        return $this->send($account, fn (): Response => $this->http()
            ->withHeaders(['SOAPAction' => '"'.$action.'"'])
            ->withBody($envelope, 'text/xml; charset=utf-8')
            ->post($url));
    }

    protected function xml(Response $response): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response->body(), SimpleXMLElement::class, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw new CarrierApiException('Aramex returned an unreadable response (HTTP '.$response->status().').', null, $response->status());
        }

        $fault = $xml->xpath('//*[local-name()="Fault"]/*[local-name()="faultstring"]');
        if ($fault) {
            throw new CarrierApiException((string) $fault[0], 'SOAP_FAULT', $response->status());
        }

        return $xml;
    }

    /**
     * The response element in the Aramex namespace, after its error flag is checked.
     */
    protected function result(SimpleXMLElement $xml, string $element, Response $response): SimpleXMLElement
    {
        $nodes = $xml->xpath('//*[local-name()="'.$element.'"]');
        if (! $nodes) {
            throw new CarrierApiException('Aramex returned an unexpected response (HTTP '.$response->status().').', null, $response->status());
        }
        $result = $nodes[0]->children(self::NAMESPACE);

        if (strtolower((string) $result->HasErrors) === 'true') {
            $notification = $result->Notifications->Notification[0] ?? null;

            throw new CarrierApiException((string) ($notification->Message ?? 'Aramex rejected the request.'), $notification ? (string) $notification->Code : null, $response->status());
        }

        return $result;
    }

    protected function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    protected function clientInfo(CarrierAccount $account): string
    {
        return '<v1:ClientInfo>'
            .'<v1:UserName>'.$this->e($account->credential('username')).'</v1:UserName>'
            .'<v1:Password>'.$this->e($account->credential('password')).'</v1:Password>'
            .'<v1:Version>v1.0</v1:Version>'
            .'<v1:AccountNumber>'.$this->e($account->account_number).'</v1:AccountNumber>'
            .'<v1:AccountPin>'.$this->e($account->credential('account_pin')).'</v1:AccountPin>'
            .'<v1:AccountEntity>'.$this->e($account->credential('account_entity')).'</v1:AccountEntity>'
            .'<v1:AccountCountryCode>'.$this->e($account->credential('account_country_code')).'</v1:AccountCountryCode>'
            .'</v1:ClientInfo>';
    }

    protected function transaction(string $reference): string
    {
        return '<v1:Transaction><v1:Reference1>'.$this->e($reference).'</v1:Reference1><v1:Reference2></v1:Reference2><v1:Reference3></v1:Reference3><v1:Reference4></v1:Reference4><v1:Reference5></v1:Reference5></v1:Transaction>';
    }

    protected function address(string $tag, ?string $line1, ?string $line2, ?string $city, ?string $state, ?string $postal, ?string $country): string
    {
        return "<v1:{$tag}>"
            .'<v1:Line1>'.$this->e($line1).'</v1:Line1><v1:Line2>'.$this->e($line2).'</v1:Line2><v1:Line3></v1:Line3>'
            .'<v1:City>'.$this->e($city).'</v1:City><v1:StateOrProvinceCode>'.$this->e($state).'</v1:StateOrProvinceCode>'
            .'<v1:PostCode>'.$this->e($postal).'</v1:PostCode><v1:CountryCode>'.$this->e($country)."</v1:CountryCode></v1:{$tag}>";
    }

    private function envelope(CarrierAccount $account, Shipment $shipment): string
    {
        $shipment->loadMissing('order');
        $packages = collect($this->packages($shipment));
        $largest = $packages->sortByDesc(fn (array $package): int => $package['length_cm'] * $package['width_cm'] * $package['height_cm'])->first();

        return '<v1:RateCalculatorRequest>'
            .$this->clientInfo($account)
            .$this->transaction($shipment->reference)
            .$this->address('OriginAddress', $account->origin_address_line1, null, $account->origin_city, $account->origin_state, $account->origin_postal_code, $account->origin_country_code)
            .$this->address('DestinationAddress', $shipment->address_line1, null, $shipment->city, $shipment->state, $shipment->postal_code, $shipment->country_code)
            .'<v1:ShipmentDetails>'
            .'<v1:Dimensions><v1:Length>'.$largest['length_cm'].'</v1:Length><v1:Width>'.$largest['width_cm'].'</v1:Width><v1:Height>'.$largest['height_cm'].'</v1:Height><v1:Unit>CM</v1:Unit></v1:Dimensions>'
            .'<v1:ActualWeight><v1:Unit>KG</v1:Unit><v1:Value>'.round((float) $packages->sum('weight_kg'), 2).'</v1:Value></v1:ActualWeight>'
            .'<v1:ChargeableWeight><v1:Unit>KG</v1:Unit><v1:Value>'.$this->chargeableWeight($account, $shipment).'</v1:Value></v1:ChargeableWeight>'
            .'<v1:DescriptionOfGoods>'.$this->e($account->option('goods_description', 'Garments')).'</v1:DescriptionOfGoods>'
            .'<v1:GoodsOriginCountry>'.$this->e($account->origin_country_code).'</v1:GoodsOriginCountry>'
            .'<v1:NumberOfPieces>'.$packages->count().'</v1:NumberOfPieces>'
            .'<v1:ProductGroup>'.$this->e($account->option('product_group', 'EXP')).'</v1:ProductGroup>'
            .'<v1:ProductType>'.$this->e($account->option('product_type', 'PPX')).'</v1:ProductType>'
            .'<v1:PaymentType>'.$this->e($account->option('payment_type', 'P')).'</v1:PaymentType>'
            .'<v1:PaymentOptions></v1:PaymentOptions>'
            .'</v1:ShipmentDetails>'
            .'<v1:PreferredCurrencyCode>'.$this->e($shipment->currency).'</v1:PreferredCurrencyCode>'
            .'</v1:RateCalculatorRequest>';
    }

    protected function chargeableWeight(CarrierAccount $account, Shipment $shipment): float
    {
        return $this->weights->calculate($shipment->cartons->toArray(), $account->dim_divisor, $account->weight_rounding_step)['billable_weight_kg'];
    }
}
