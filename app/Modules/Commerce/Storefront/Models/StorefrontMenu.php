<?php

namespace App\Modules\Commerce\Storefront\Models;

use Illuminate\Database\Eloquent\Model;

class StorefrontMenu extends Model
{
    protected $table = 'commerce_storefront_menus';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['items' => 'array'];
    }
}
