<?php

namespace App\Modules\Shipping\Services;

use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\OrderMoney;

/**
 * Exact decimal maths for shipping costs, rounded through OrderMoney.
 */
class ShippingMoney
{
    public static function precision(string $currency): int
    {
        return (new StoreOrderSetting(['currency' => $currency]))->precision();
    }

    public static function round(string|int|float|null $amount, int $precision = 2): string
    {
        return OrderMoney::decimal(OrderMoney::minor(self::clean($amount), $precision), $precision);
    }

    /**
     * Converts an amount into another currency: amount × rate, rounded half up.
     */
    public static function convert(string|int|float|null $amount, string|int|float|null $rate, int $precision = 2): string
    {
        return self::round(bcmul(self::clean($amount), self::clean($rate ?? 1), 12), $precision);
    }

    public static function percentOf(string|int|float|null $amount, string|int|float|null $percent, int $precision = 2): string
    {
        return self::round(bcdiv(bcmul(self::clean($amount), self::clean($percent), 12), '100', 12), $precision);
    }

    /**
     * @param  iterable<int, string|int|float|null>  $amounts
     */
    public static function sum(iterable $amounts, int $precision = 2): string
    {
        $minor = 0;
        foreach ($amounts as $amount) {
            $minor += OrderMoney::minor(self::clean($amount), $precision);
        }

        return OrderMoney::decimal($minor, $precision);
    }

    /**
     * Signed difference $left − $right as a decimal string.
     */
    public static function difference(string|int|float|null $left, string|int|float|null $right, int $precision = 2): string
    {
        $minor = OrderMoney::minor(self::clean($left), $precision) - OrderMoney::minor(self::clean($right), $precision);

        return ($minor < 0 ? '-' : '').OrderMoney::decimal(abs($minor), $precision);
    }

    public static function isGreater(string|int|float|null $left, string|int|float|null $right, int $precision = 2): bool
    {
        return OrderMoney::minor(self::clean($left), $precision) > OrderMoney::minor(self::clean($right), $precision);
    }

    private static function clean(string|int|float|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return '0';
        }

        return is_float($amount) ? number_format($amount, 8, '.', '') : trim((string) $amount);
    }
}
