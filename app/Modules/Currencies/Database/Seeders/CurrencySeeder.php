<?php

namespace App\Modules\Currencies\Database\Seeders;

use App\Modules\Currencies\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    private const POPULAR_ORDER = [
        'USD' => 1,
        'EUR' => 2,
        'GBP' => 3,
        'BDT' => 4,
        'INR' => 5,
        'NGN' => 6,
        'GHS' => 7,
        'KES' => 8,
        'ZAR' => 9,
        'CAD' => 10,
        'AUD' => 11,
        'JPY' => 12,
        'CNY' => 13,
        'MYR' => 14,
        'SGD' => 15,
    ];

    public function run(): void
    {
        $order = 0;

        foreach (config('currencies.names') as $code => $name) {
            $sortOrder = self::POPULAR_ORDER[$code] ?? ($order += 1);
            $symbol = html_entity_decode((string) config("currencies.symbols.$code", ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $symbol = trim(str_replace("\xc2\xa0", ' ', $symbol));

            Currency::firstOrCreate(
                ['code' => $code],
                [
                    'code' => $code,
                    'name' => $name,
                    'symbol' => mb_substr($symbol, 0, 32),
                    'exchange_rate' => $code === 'USD' ? 1.000000 : 0.000000,
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                ]
            );
        }
    }
}
