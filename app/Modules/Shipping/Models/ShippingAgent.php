<?php

namespace App\Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingAgent extends Model
{
    public const PAYMENT_STATUSES = ['clear', 'due', 'overdue'];

    protected $table = 'shipping_agents';

    protected $fillable = [
        'workspace_id',
        'name',
        'company_name',
        'phone',
        'whatsapp',
        'carrier',
        'currency',
        'exchange_rate',
        'fuel_surcharge_percent',
        'other_fee',
        'other_fee_label',
        'dim_divisor',
        'weight_rounding_step',
        'payment_status',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate' => 'decimal:8',
            'fuel_surcharge_percent' => 'decimal:3',
            'other_fee' => 'decimal:4',
            'dim_divisor' => 'integer',
            'weight_rounding_step' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function charts(): HasMany
    {
        return $this->hasMany(AgentRateChart::class, 'shipping_agent_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(ShippingInvoice::class, 'shipping_agent_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ShippingPayment::class, 'shipping_agent_id');
    }

    public function carrierLabel(): ?string
    {
        return $this->carrier ? (CarrierAccount::CARRIERS[$this->carrier] ?? strtoupper($this->carrier)) : null;
    }
}
