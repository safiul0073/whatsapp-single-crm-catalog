<?php

namespace App\Modules\Commerce\Storefront\Models;

use Illuminate\Database\Eloquent\Model;

class PackTemplate extends Model
{
    protected $table = 'commerce_pack_templates';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['product_ids' => 'array', 'published' => 'boolean'];
    }
}
