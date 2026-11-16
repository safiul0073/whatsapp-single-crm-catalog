<?php

namespace App\Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Model;

class CarrierAccount extends Model
{
    public const CARRIERS = ['ups' => 'UPS', 'fedex' => 'FedEx', 'aramex' => 'Aramex'];

    public const ENVIRONMENTS = ['sandbox', 'production'];

    protected $table = 'shipping_carrier_accounts';

    protected $fillable = [
        'workspace_id',
        'name',
        'carrier',
        'account_number',
        'environment',
        'credentials',
        'dim_divisor',
        'weight_rounding_step',
        'is_active',
        'notes',
    ];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'dim_divisor' => 'integer',
            'weight_rounding_step' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function carrierLabel(): string
    {
        return self::CARRIERS[$this->carrier] ?? strtoupper((string) $this->carrier);
    }

    public function hasCredentials(): bool
    {
        return array_filter((array) $this->credentials) !== [];
    }
}
