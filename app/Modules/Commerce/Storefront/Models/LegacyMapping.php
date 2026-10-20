<?php

namespace App\Modules\Commerce\Storefront\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyMapping extends Model
{
    protected $table = 'commerce_legacy_mappings';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }
}
