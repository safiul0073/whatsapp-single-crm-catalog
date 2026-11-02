<?php

namespace App\Modules\Commerce\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Resolves a postal code to its locality through the free Zippopotam.us service. */
class PostalCodeLookupService
{
    private const FORMATS = ['US' => '/^(\d{5})(?:-\d{4})?$/', 'BD' => '/^(\d{4})$/'];

    /**
     * @return array<int, string>
     */
    public static function countries(): array
    {
        return array_values(array_intersect(array_keys(self::FORMATS), (array) config('services.zippopotam.countries', ['US', 'BD'])));
    }

    /**
     * @return array{postal_code: string, city: string, state: string, area: string, country: string}|null
     */
    public function lookup(string $country, string $postalCode): ?array
    {
        $country = strtoupper($country);
        if (! in_array($country, self::countries(), true) || ! preg_match(self::FORMATS[$country], trim($postalCode), $match)) {
            return null;
        }
        $postalCode = $match[1];
        $key = 'postal:'.$country.':'.$postalCode;
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached === false ? null : $cached;
        }
        $result = $this->fetch($country, $postalCode);
        Cache::put($key, $result ?? false, $result ? now()->addDay() : now()->addHour());

        return $result;
    }

    /**
     * @return array{postal_code: string, city: string, state: string, area: string, country: string}|null
     */
    private function fetch(string $country, string $postalCode): ?array
    {
        try {
            $response = Http::timeout((int) config('services.zippopotam.timeout', 4))->acceptJson()
                ->get(rtrim((string) config('services.zippopotam.url'), '/').'/'.$country.'/'.$postalCode);
        } catch (Throwable) {
            return null;
        }
        $place = $response->successful() ? ($response->json('places.0') ?? null) : null;
        if (! is_array($place)) {
            return null;
        }
        $name = trim((string) ($place['place name'] ?? ''));
        $state = trim((string) ($place['state abbreviation'] ?? '')) ?: trim((string) ($place['state'] ?? ''));

        return [
            'postal_code' => $postalCode,
            'city' => $country === 'BD' ? $state : $name,
            'state' => $state,
            'area' => $country === 'BD' ? $name : '',
            'country' => $country,
        ];
    }
}
