<?php

namespace App\Modules\PlansSubscriptions\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'price', 'interval', 'limits', 'features', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'limits' => 'array', 'features' => 'array', 'is_active' => 'boolean'];
    }
}
