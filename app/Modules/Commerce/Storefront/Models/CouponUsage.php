<?php

namespace App\Modules\Commerce\Storefront\Models;

use Illuminate\Database\Eloquent\Model;

class CouponUsage extends Model
{
    protected $table = 'commerce_coupon_usages';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [];
    }
}
