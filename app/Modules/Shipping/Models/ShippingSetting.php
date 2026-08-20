<?php

namespace App\Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingSetting extends Model
{
    protected $table = 'shipping_settings';

    protected $fillable = [
        'workspace_id',
        'default_packaging_weight_kg',
        'is_packaging_weight_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_packaging_weight_enabled' => 'boolean',
            'default_packaging_weight_kg' => 'decimal:3',
        ];
    }
}
