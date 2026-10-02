<?php

namespace App\Modules\Commerce\Services;

use Illuminate\Validation\ValidationException;

class OrderMoney
{
    public static function minor(string|int|float $amount, int $precision = 2): int
    {
        $value = (string) $amount;
        if (! preg_match('/^\d{1,10}(?:\.\d+)?$/', $value)) {
            throw ValidationException::withMessages(['price' => 'Invalid money amount.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = str_pad($fraction, $precision + 1, '0');

        return ((int) $whole * (10 ** $precision)) + (int) substr($fraction, 0, $precision) + ((int) $fraction[$precision] >= 5 ? 1 : 0);
    }

    public static function decimal(int $minor, int $precision = 2): string
    {
        $factor = 10 ** $precision;

        return $precision === 0 ? (string) $minor : intdiv($minor, $factor).'.'.str_pad((string) ($minor % $factor), $precision, '0', STR_PAD_LEFT);
    }
}
