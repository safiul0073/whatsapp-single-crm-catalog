<?php

namespace App\Modules\Currencies;

use App\Modules\Currencies\Models\Currency;
use App\Modules\Currencies\Policies\CurrencyPolicy;
use App\Modules\Shared\Support\BasePanelModule;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'currencies';
    }

    public function permissions(): array
    {
        return [
            'admin' => [
                'currencies.view' => 'View currencies',
                'currencies.create' => 'Create currencies',
                'currencies.edit' => 'Edit currencies',
                'currencies.delete' => 'Delete currencies',
            ],
        ];
    }

    public function policies(): array
    {
        return [
            Currency::class => CurrencyPolicy::class,
        ];
    }
}
