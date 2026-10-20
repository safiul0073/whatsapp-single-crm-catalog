<?php

namespace App\Modules\Commerce\Storefront\Models;

use Illuminate\Database\Eloquent\Model;

class CartGroup extends Model
{
    protected $table = 'commerce_cart_groups';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['selection' => 'array'];
    }
}
