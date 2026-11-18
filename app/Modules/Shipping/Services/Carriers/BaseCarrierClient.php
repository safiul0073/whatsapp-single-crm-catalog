<?php

namespace App\Modules\Shipping\Services\Carriers;

use App\Modules\Shipping\Models\CarrierAccount;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Services\ShipmentWeightCalculator;
use App\Modules\Shipping\Services\ShippingMoney;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

abstract class BaseCarrierClient implements CarrierRateClient
{
    public function __construct(protected ShipmentWeightCalculator $weights) {}

    protected function baseUrl(CarrierAccount $account): string
    {
        return rtrim((string) config('shipping_carriers.api.'.$account->carrier.'.'.($account->environment === 'production' ? 'production' : 'sandbox')), '/');
    }

    protected function http(): PendingRequest
    {
        return Http::connectTimeout((int) config('shipping_carriers.api.connect_timeout', 5))
            ->timeout((int) config('shipping_carriers.api.timeout', 20));
    }

    /**
     * Runs a request and turns network failures into a message staff can read.
     *
     * @param  callable(): Response  $request
     */
    protected function send(CarrierAccount $account, callable $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException $exception) {
            throw new CarrierApiException($account->carrierLabel().' could not be reached. Try again in a moment.', 'CONNECTION');
        }
    }

    /**
     * Keeps an access token for its lifetime so the courier's token limit is not hit.
     *
     * @param  callable(): array{token: string, expires_in: int}  $fetch
     */
    protected function cachedToken(CarrierAccount $account, callable $fetch): string
    {
        $key = $this->tokenCacheKey($account);
        $cached = Cache::get($key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $token = $fetch();
        Cache::put($key, $token['token'], max(60, $token['expires_in'] - 120));

        return $token['token'];
    }

    protected function forgetToken(CarrierAccount $account): void
    {
        Cache::forget($this->tokenCacheKey($account));
    }

    /**
     * One row per physical package, as couriers rate each package on its own.
     *
     * @return list<array{weight_kg: float, length_cm: int, width_cm: int, height_cm: int}>
     */
    protected function packages(Shipment $shipment): array
    {
        $packages = [];
        foreach ($shipment->cartons as $carton) {
            for ($count = 0; $count < max(1, (int) $carton->quantity); $count++) {
                $packages[] = [
                    'weight_kg' => round((float) $carton->actual_weight_kg, 2),
                    'length_cm' => (int) ceil((float) $carton->length_cm),
                    'width_cm' => (int) ceil((float) $carton->width_cm),
                    'height_cm' => (int) ceil((float) $carton->height_cm),
                ];
            }
        }

        return $packages;
    }

    /**
     * Builds quotation data whose lines add up to exactly what the courier returned.
     *
     * @param  list<array{reason: string, label: ?string, amount: string|float|int}>  $charges
     * @return array<string, mixed>
     */
    protected function quoteData(CarrierAccount $account, Shipment $shipment, string $service, string|float $total, string $currency, array $charges, ?float $billableWeightKg, ?string $transit, bool $itemised, ?string $serviceCode = null): array
    {
        $currency = strtoupper($currency);
        $precision = ShippingMoney::precision($currency);
        $charges = array_values(array_filter($charges, fn (array $charge): bool => (float) $charge['amount'] > 0));
        $chargeTotal = ShippingMoney::sum(array_column($charges, 'amount'), $precision);
        $total = ShippingMoney::round($total, $precision);
        if (ShippingMoney::isGreater($chargeTotal, $total, $precision)) {
            $charges = [];
            $chargeTotal = '0';
        }

        $hasRemote = collect($charges)->contains('reason', 'remote_area');
        $needsRate = $currency !== $shipment->currency && (float) $account->exchange_rate === 1.0;

        return [
            'shipping_carrier_account_id' => $account->id,
            'carrier' => $account->carrier,
            'service' => mb_substr($service, 0, 80),
            'service_code' => $serviceCode !== null && $serviceCode !== '' ? mb_substr($serviceCode, 0, 40) : null,
            'source' => 'api',
            'billable_weight_kg' => $billableWeightKg,
            'base_rate' => ShippingMoney::difference($total, $chargeTotal, $precision),
            'currency' => $currency,
            'exchange_rate' => $currency === $shipment->currency ? '1' : (string) $account->exchange_rate,
            'transit_days' => $transit ? mb_substr($transit, 0, 40) : null,
            'remote_status' => $hasRemote ? 'remote' : ($itemised ? 'normal' : 'unknown'),
            'status' => $needsRate ? 'unverified' : 'verified',
            'valid_until' => now()->addHours((int) config('shipping_carriers.api.quote_validity_hours', 24)),
            'notes' => $needsRate
                ? 'Quoted in '.$currency.'. Set the exchange rate on the courier account, then request rates again.'
                : 'Live rate from '.$account->carrierLabel().' ('.$account->environment.')',
            'charges' => $charges,
        ];
    }

    /**
     * Maps a courier's own surcharge wording onto the reasons tracked for reconciliation.
     */
    protected function chargeReason(string $text): string
    {
        $text = strtolower(str_replace('_', ' ', $text));

        return match (true) {
            str_contains($text, 'fuel') => 'fuel',
            (bool) preg_match('/remote|extended area|out of (delivery|pickup) area|delivery area|high cost service area|offshore/', $text) => 'remote_area',
            str_contains($text, 'additional handling') => 'additional_handling',
            (bool) preg_match('/oversize|large package|over dimension|over length|extreme length/', $text) => 'oversize',
            (bool) preg_match('/overweight|over max/', $text) => 'overweight',
            str_contains($text, 'address correction') => 'address_correction',
            (bool) preg_match('/customs|brokerage|duty|duties/', $text) => 'customs',
            default => 'other',
        };
    }

    /**
     * Tracking event in the shape the tracking service stores.
     *
     * @return array{status: string, code: ?string, description: string, location: ?string, occurred_at: ?string}
     */
    protected function trackingEvent(string $status, ?string $code, string $description, ?string $location, ?string $occurredAt): array
    {
        $location = trim((string) $location, ' ,');

        return [
            'status' => $status,
            'code' => filled($code) ? mb_substr((string) $code, 0, 20) : null,
            'description' => mb_substr(trim($description) !== '' ? trim($description) : 'Status update', 0, 255),
            'location' => $location !== '' ? mb_substr($location, 0, 160) : null,
            'occurred_at' => $occurredAt,
        ];
    }

    /**
     * Reads a courier's wording when it gives no usable status code.
     */
    protected function statusFromText(string $text): string
    {
        $text = strtolower($text);

        return match (true) {
            (bool) preg_match('/not delivered|undeliver|unable|attempt|refused|exception|delay|held|hold|return(ed)? to (shipper|sender)|damaged|incorrect address|address (problem|issue)|customs (hold|delay)/', $text) => 'exception',
            str_contains($text, 'out for delivery') || str_contains($text, 'with delivery courier') => 'out_for_delivery',
            (bool) preg_match('/\bdelivered\b|collected by consignee/', $text) => 'delivered',
            (bool) preg_match('/label created|shipment (information|data) (sent|received)|record created|order processed: ready for ups|shipment created/', $text) => 'booked',
            default => 'in_transit',
        };
    }

    /**
     * Keeps a courier's label or document bytes with a file name and type.
     *
     * @return array{type: string, name: string, content: string, mime: string}
     */
    protected function document(string $type, string $name, string $base64, string $format): array
    {
        $format = strtolower($format);
        $mime = match ($format) {
            'pdf' => 'application/pdf',
            'gif' => 'image/gif',
            'png' => 'image/png',
            'zpl', 'epl' => 'text/plain',
            default => 'application/octet-stream',
        };

        return ['type' => $type, 'name' => $name.'.'.$format, 'content' => (string) base64_decode($base64, true), 'mime' => $mime];
    }

    protected function toKg(float $weight, ?string $unit): float
    {
        return round(in_array(strtoupper((string) $unit), ['LB', 'LBS'], true) ? $weight * 0.45359237 : $weight, 3);
    }

    private function tokenCacheKey(CarrierAccount $account): string
    {
        return 'shipping:carrier-token:'.$account->id.':'.$account->environment.':'.sha1(json_encode($account->credentials).$account->updated_at);
    }
}
