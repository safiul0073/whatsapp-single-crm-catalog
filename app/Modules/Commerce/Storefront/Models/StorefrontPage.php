<?php

namespace App\Modules\Commerce\Storefront\Models;

use Illuminate\Database\Eloquent\Model;

class StorefrontPage extends Model
{
    protected $table = 'commerce_storefront_pages';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['seo' => 'array', 'published_content' => 'array', 'published_at' => 'datetime'];
    }
}
