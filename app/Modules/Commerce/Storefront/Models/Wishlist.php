<?php

namespace App\Modules\Commerce\Storefront\Models;

use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    protected $table = 'commerce_wishlists';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [];
    }
}
