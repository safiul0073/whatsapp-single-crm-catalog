<?php

namespace App\Modules\Currencies\Services;

use App\Modules\Currencies\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Picks the display currency for a visitor: switcher cookie, then IP country, then the default.
 * Display only — payments are always charged in currency_default_code().
 */
class VisitorCurrencyService
{
    public const COOKIE = 'display_currency';

    /** ISO-3166 alpha-2 country => ISO-4217 currency. */
    private const COUNTRY_CURRENCIES = [
        'US' => 'USD', 'PR' => 'USD', 'EC' => 'USD', 'SV' => 'USD', 'PA' => 'USD',
        'CA' => 'CAD', 'MX' => 'MXN', 'BR' => 'BRL', 'AR' => 'ARS', 'CL' => 'CLP', 'CO' => 'COP', 'PE' => 'PEN',
        'GB' => 'GBP', 'IE' => 'EUR', 'DE' => 'EUR', 'FR' => 'EUR', 'IT' => 'EUR', 'ES' => 'EUR', 'PT' => 'EUR',
        'NL' => 'EUR', 'BE' => 'EUR', 'AT' => 'EUR', 'FI' => 'EUR', 'GR' => 'EUR', 'LU' => 'EUR', 'SK' => 'EUR',
        'SI' => 'EUR', 'EE' => 'EUR', 'LV' => 'EUR', 'LT' => 'EUR', 'MT' => 'EUR', 'CY' => 'EUR', 'HR' => 'EUR',
        'CH' => 'CHF', 'SE' => 'SEK', 'NO' => 'NOK', 'DK' => 'DKK', 'PL' => 'PLN', 'CZ' => 'CZK', 'HU' => 'HUF',
        'RO' => 'RON', 'BG' => 'BGN', 'TR' => 'TRY', 'RU' => 'RUB', 'UA' => 'UAH',
        'BD' => 'BDT', 'IN' => 'INR', 'PK' => 'PKR', 'NP' => 'NPR', 'LK' => 'LKR',
        'CN' => 'CNY', 'JP' => 'JPY', 'KR' => 'KRW', 'HK' => 'HKD', 'TW' => 'TWD', 'SG' => 'SGD', 'MY' => 'MYR',
        'TH' => 'THB', 'ID' => 'IDR', 'PH' => 'PHP', 'VN' => 'VND', 'AU' => 'AUD', 'NZ' => 'NZD',
        'AE' => 'AED', 'SA' => 'SAR', 'QA' => 'QAR', 'KW' => 'KWD', 'BH' => 'BHD', 'OM' => 'OMR', 'JO' => 'JOD',
        'IL' => 'ILS', 'EG' => 'EGP', 'MA' => 'MAD', 'NG' => 'NGN', 'KE' => 'KES', 'GH' => 'GHS', 'ZA' => 'ZAR',
    ];

    public function resolve(Request $request): string
    {
        $default = currency_default_code();
        $active = $this->activeCodes();

        $cookie = strtoupper((string) $request->cookie(self::COOKIE));
        if ($cookie !== '' && in_array($cookie, $active, true)) {
            return $cookie;
        }

        $code = self::COUNTRY_CURRENCIES[$this->country($request) ?? ''] ?? null;

        return $code && in_array($code, $active, true) ? $code : $default;
    }

    /** @return list<string> */
    public function activeCodes(): array
    {
        try {
            return Currency::getActiveCodes();
        } catch (Throwable) {
            return [];
        }
    }

    public function country(Request $request): ?string
    {
        $header = strtoupper((string) $request->header('CF-IPCountry', $request->header('X-Vercel-IP-Country', '')));
        if (preg_match('/^[A-Z]{2}$/', $header) && ! in_array($header, ['XX', 'T1'], true)) {
            return $header;
        }

        $ip = (string) $request->ip();
        $url = (string) config('commerce.geoip.url');
        if ($url === '' || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        $country = Cache::remember('currencies:geoip:'.$ip, now()->addDays(7), function () use ($url, $ip): string {
            try {
                $json = (array) Http::timeout(3)->get(str_replace('{ip}', $ip, $url))->json();
            } catch (Throwable) {
                $json = [];
            }

            return strtoupper((string) ($json['countryCode'] ?? $json['country_code'] ?? ''));
        });

        return preg_match('/^[A-Z]{2}$/', $country) ? $country : null;
    }
}
