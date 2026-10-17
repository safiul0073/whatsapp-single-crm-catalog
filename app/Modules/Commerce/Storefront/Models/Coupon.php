<?php

namespace App\Modules\Commerce\Storefront\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $table = 'commerce_coupons';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'expires_at' => 'datetime', 'product_ids' => 'array', 'category_ids' => 'array', 'active' => 'boolean'];
    }
}
