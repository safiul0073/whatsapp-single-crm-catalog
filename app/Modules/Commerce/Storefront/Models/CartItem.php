<?php

namespace App\Modules\Commerce\Storefront\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $table = 'commerce_cart_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'price_snapshot' => 'decimal:4'];
    }
}
