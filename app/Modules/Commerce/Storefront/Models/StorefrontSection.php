<?php

namespace App\Modules\Commerce\Storefront\Models;

use Illuminate\Database\Eloquent\Model;

class StorefrontSection extends Model
{
    protected $table = 'commerce_storefront_sections';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['config' => 'array', 'active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }
}
