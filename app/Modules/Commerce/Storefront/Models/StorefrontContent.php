<?php

namespace App\Modules\Commerce\Storefront\Models;

use Illuminate\Database\Eloquent\Model;

class StorefrontContent extends Model
{
    protected $table = 'commerce_storefront_contents';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array', 'published_at' => 'datetime'];
    }
}
