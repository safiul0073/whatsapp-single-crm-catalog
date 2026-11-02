<?php

namespace App\Modules\Commerce\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/** US street-address suggestions from the free Photon (OpenStreetMap) geocoder. */
class AddressAutocompleteService
{
    /** Photon bounding boxes (lon/lat) per supported country. */
    private const BBOX = ['US' => '-179.2,17.6,-66.8,71.5', 'BD' => '88.0,20.6,92.7,26.7'];

    /**
     * Searches the given country, or every supported country when none is given.
     *
     * @return array<int, array{label: string, line1: string, city: string, state: string, postal_code: string, country: string}>
     */
    public function suggest(string $query, ?string $country = null): array
    {
        $query = preg_replace('/\s+/', ' ', trim($query)) ?? '';
        $countries = array_values(array_filter($country ? [strtoupper($country)] : array_keys(self::BBOX), fn (string $code): bool => isset(self::BBOX[$code])));
        if (mb_strlen($query) < 3 || $countries === []) {
            return [];
        }

        return Cache::remember('pos:address:v3:'.implode('-', $countries).':'.md5(mb_strtolower($query)), now()->addHour(), fn (): array => $this->fetch($query, $countries));
    }

    /**
     * @param  array<int, string>  $countries
     * @return array<int, array{label: string, line1: string, city: string, state: string, postal_code: string, country: string}>
     */
    private function fetch(string $query, array $countries): array
    {
        $url = rtrim((string) config('services.photon.url'), '/').'/api/';
        $number = preg_match('/^\s*(\d+[a-z]?)\b/i', $query, $match) ? $match[1] : null;
        try {
            $responses = Http::pool(fn (Pool $pool): array => array_map(
                fn (string $code) => $pool->as($code)->timeout((int) config('services.photon.timeout', 4))->acceptJson()
                    ->get($url, ['q' => $query, 'limit' => 10, 'lang' => 'en', 'bbox' => self::BBOX[$code]]),
                $countries,
            ));
        } catch (Throwable) {
            return [];
        }
        $perCountry = collect($countries)->map(fn (string $code): array => ($responses[$code] ?? null) instanceof Response && $responses[$code]->successful()
            ? collect($responses[$code]->json('features', []))->map(fn (array $feature): ?array => $this->map($feature['properties'] ?? [], $number, $code))->filter()->unique('label')->values()->all()
            : [])->all();

        $merged = [];
        for ($index = 0; $index < 6; $index++) {
            foreach ($perCountry as $results) {
                if (isset($results[$index])) {
                    $merged[] = $results[$index];
                }
            }
        }

        return array_slice($merged, 0, 6);
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array{label: string, line1: string, city: string, state: string, postal_code: string, country: string}|null
     */
    private function map(array $properties, ?string $number, string $country): ?array
    {
        $street = $properties['street'] ?? (($properties['type'] ?? null) === 'street' ? ($properties['name'] ?? null) : null);
        if (($properties['countrycode'] ?? null) !== $country || blank($street)) {
            return null;
        }
        $houseNumber = $properties['housenumber'] ?? ($number !== null && ! preg_match('/\b'.preg_quote($number, '/').'\b/i', $street) ? $number : null);
        $line1 = trim(($houseNumber ? $houseNumber.' ' : '').$street);
        $city = (string) ($properties['city'] ?? $properties['town'] ?? $properties['village'] ?? $properties['district'] ?? $properties['county'] ?? '');
        $state = $country === 'US' ? $this->stateCode((string) ($properties['state'] ?? '')) : trim(preg_replace('/\s+(Division|Province|State)$/i', '', (string) ($properties['state'] ?? '')) ?? '');
        $postalCode = (string) ($properties['postcode'] ?? '');

        return [
            'label' => implode(', ', array_filter([$line1, $city, trim($state.' '.$postalCode)])),
            'line1' => $line1,
            'city' => $city,
            'state' => $state,
            'postal_code' => $postalCode,
            'country' => $country,
        ];
    }

    private function stateCode(string $name): string
    {
        $states = [
            'Alabama' => 'AL', 'Alaska' => 'AK', 'Arizona' => 'AZ', 'Arkansas' => 'AR', 'California' => 'CA', 'Colorado' => 'CO', 'Connecticut' => 'CT', 'Delaware' => 'DE',
            'District of Columbia' => 'DC', 'Florida' => 'FL', 'Georgia' => 'GA', 'Hawaii' => 'HI', 'Idaho' => 'ID', 'Illinois' => 'IL', 'Indiana' => 'IN', 'Iowa' => 'IA',
            'Kansas' => 'KS', 'Kentucky' => 'KY', 'Louisiana' => 'LA', 'Maine' => 'ME', 'Maryland' => 'MD', 'Massachusetts' => 'MA', 'Michigan' => 'MI', 'Minnesota' => 'MN',
            'Mississippi' => 'MS', 'Missouri' => 'MO', 'Montana' => 'MT', 'Nebraska' => 'NE', 'Nevada' => 'NV', 'New Hampshire' => 'NH', 'New Jersey' => 'NJ', 'New Mexico' => 'NM',
            'New York' => 'NY', 'North Carolina' => 'NC', 'North Dakota' => 'ND', 'Ohio' => 'OH', 'Oklahoma' => 'OK', 'Oregon' => 'OR', 'Pennsylvania' => 'PA', 'Rhode Island' => 'RI',
            'South Carolina' => 'SC', 'South Dakota' => 'SD', 'Tennessee' => 'TN', 'Texas' => 'TX', 'Utah' => 'UT', 'Vermont' => 'VT', 'Virginia' => 'VA', 'Washington' => 'WA',
            'West Virginia' => 'WV', 'Wisconsin' => 'WI', 'Wyoming' => 'WY',
        ];

        return $states[$name] ?? $name;
    }
}
