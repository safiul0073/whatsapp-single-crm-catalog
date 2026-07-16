<?php

namespace App\Modules\Commerce\Policies;

use App\Modules\Commerce\Models\Product;
use Illuminate\Contracts\Auth\Authenticatable;

class CommercePolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('commerce.view');
    }

    public function view(Authenticatable $user, Product $product): bool
    {
        return $user->can('commerce.view');
    }

    public function create(Authenticatable $user): bool
    {
        return $user->can('commerce.manage');
    }

    public function update(Authenticatable $user, Product $product): bool
    {
        return $user->can('commerce.manage');
    }

    public function delete(Authenticatable $user, Product $product): bool
    {
        return $user->can('commerce.manage');
    }
}
