<?php

namespace App\Modules\Commerce\Storefront\Models;

use Illuminate\Database\Eloquent\Model;

class StorefrontSetting extends Model
{
    protected $table = 'commerce_storefront_settings';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['config' => 'array'];
    }
}
