<?php

namespace App\Modules\Commerce\Storefront\Models;

use Illuminate\Database\Eloquent\Model;

class StorefrontSlider extends Model
{
    protected $table = 'commerce_storefront_sliders';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['slides' => 'array'];
    }
}
