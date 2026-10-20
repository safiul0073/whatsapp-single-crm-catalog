<?php

namespace App\Modules\Commerce\Storefront\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorefrontPage extends Model
{
    protected $table = 'commerce_storefront_pages';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['seo' => 'array', 'published_content' => 'array', 'published_at' => 'datetime'];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(StorefrontSection::class, 'page_id')->orderBy('position');
    }
}
