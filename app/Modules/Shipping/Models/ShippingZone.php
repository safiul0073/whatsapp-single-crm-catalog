<?php

namespace App\Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingZone extends Model
{
    protected $table = 'shipping_zones';

    protected $fillable = [
        'workspace_id',
        'name',
        'code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function countries(): HasMany
    {
        return $this->hasMany(ShippingZoneCountry::class, 'shipping_zone_id');
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class, 'shipping_zone_id');
    }
}
