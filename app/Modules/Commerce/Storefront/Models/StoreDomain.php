<?php

namespace App\Modules\Commerce\Storefront\Models;

use Illuminate\Database\Eloquent\Model;

class StoreDomain extends Model
{
    protected $table = 'commerce_store_domains';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'verified_at' => 'datetime'];
    }
}
