<?php

namespace App\Modules\Commerce\Storefront\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{
    protected $table = 'commerce_customer_addresses';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['address' => 'array', 'is_default' => 'boolean'];
    }
}
