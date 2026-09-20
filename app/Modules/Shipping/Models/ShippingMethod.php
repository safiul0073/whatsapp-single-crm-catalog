<?php

namespace App\Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingMethod extends Model
{
    protected $table = 'shipping_methods';

    protected $fillable = [
        'workspace_id',
        'name',
        'code',
        'carrier',
        'type',
        'description',
        'estimated_delivery_min_days',
        'estimated_delivery_max_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'estimated_delivery_min_days' => 'integer',
            'estimated_delivery_max_days' => 'integer',
        ];
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class, 'shipping_method_id');
    }
}
