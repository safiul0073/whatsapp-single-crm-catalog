<?php

namespace App\Modules\Commerce\Policies;

use App\Modules\Commerce\Models\Product;
use Illuminate\Contracts\Auth\Authenticatable;

class CommercePolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('commerce.products.view');
    }

    public function view(Authenticatable $user, Product $product): bool
    {
        return $user->can('commerce.products.view');
    }

    public function create(Authenticatable $user): bool
    {
        return $user->can('commerce.products.manage');
    }

    public function update(Authenticatable $user, Product $product): bool
    {
        return $user->can('commerce.products.manage');
    }

    public function delete(Authenticatable $user, Product $product): bool
    {
        return $user->can('commerce.products.manage');
    }
}
