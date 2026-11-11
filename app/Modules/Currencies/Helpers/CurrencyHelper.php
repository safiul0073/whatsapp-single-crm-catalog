<?php

use App\Modules\Currencies\Models\Currency;
use App\Modules\Currencies\Services\VisitorCurrencyService;

if (! function_exists('currency_normalize_symbol')) {
    function currency_normalize_symbol(string $symbol): string
    {
        $decoded = html_entity_decode($symbol, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_substr(trim(str_replace("\xc2\xa0", ' ', $decoded)), 0, 32);
    }
}

if (! function_exists('currency_default_code')) {
    function currency_default_code(): string
    {
        $code = function_exists('payment_gateway_setting')
            ? payment_gateway_setting('payment_currency', 'USD')
            : 'USD';

        return strtoupper((string) ($code ?: 'USD'));
    }
}

if (! function_exists('currency_find')) {
    function currency_find(?string $code = null): ?Currency
    {
        static $currencies = [];

        $code = strtoupper((string) ($code ?: currency_default_code()));

        return $currencies[$code] ??= Currency::query()->where('code', $code)->first();
    }
}

if (! function_exists('currency_symbol')) {
    function currency_symbol(?string $code = null): string
    {
        $currency = currency_find($code);

        return $currency?->symbol ?: strtoupper((string) ($code ?: currency_default_code()));
    }
}

if (! function_exists('currency_convert')) {
    function currency_convert(float|int|string $amount, ?string $from = null, ?string $to = null): float
    {
        $fromCurrency = currency_find($from);
        $toCurrency = currency_find($to);

        if (! $fromCurrency || ! $toCurrency) {
            return (float) $amount;
        }

        $fromRate = (float) $fromCurrency->exchange_rate;
        $toRate = (float) $toCurrency->exchange_rate;

        if ($fromRate <= 0 || $toRate <= 0) {
            return (float) $amount;
        }

        return ((float) $amount / $fromRate) * $toRate;
    }
}

if (! function_exists('currency_format')) {
    function currency_format(float|int|string $amount, ?string $code = null, bool $withCode = false): string
    {
        $code = strtoupper((string) ($code ?: currency_default_code()));
        $symbol = currency_symbol($code);
        $formattedAmount = number_format((float) $amount, 2);
        $formatted = $symbol === $code ? "{$code} {$formattedAmount}" : "{$symbol}{$formattedAmount}";

        return $withCode ? "{$formatted} {$code}" : $formatted;
    }
}

if (! function_exists('currency_display_code')) {
    /** Visitor's display currency (switcher cookie → IP country → default). Display only. */
    function currency_display_code(): string
    {
        if (! app()->bound('currency.display')) {
            $code = app(VisitorCurrencyService::class)->resolve(request());
            app()->instance('currency.display', $code);
        }

        return (string) app('currency.display');
    }
}

if (! function_exists('currency_display_amount')) {
    function currency_display_amount(float|int|string $amount, ?string $from = null): float
    {
        return currency_convert($amount, $from ?: currency_default_code(), currency_display_code());
    }
}

if (! function_exists('currency_display')) {
    /** Format an amount stored in $from (default currency) in the visitor's display currency. */
    function currency_display(float|int|string $amount, ?string $from = null): string
    {
        return currency_format(currency_display_amount($amount, $from), currency_display_code());
    }
}
