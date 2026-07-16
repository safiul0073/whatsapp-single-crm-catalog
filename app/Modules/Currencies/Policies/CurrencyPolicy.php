<?php

namespace App\Modules\Currencies\Policies;

use App\Modules\Currencies\Models\Currency;
use Illuminate\Contracts\Auth\Authenticatable;

class CurrencyPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('currencies.view');
    }

    public function view(Authenticatable $user, Currency $currency): bool
    {
        return $user->can('currencies.view');
    }

    public function create(Authenticatable $user): bool
    {
        return $user->can('currencies.create');
    }

    public function update(Authenticatable $user, Currency $currency): bool
    {
        return $user->can('currencies.edit');
    }

    public function delete(Authenticatable $user, Currency $currency): bool
    {
        return $user->can('currencies.delete');
    }
}
