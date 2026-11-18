<?php

namespace App\Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentRateRow extends Model
{
    protected $table = 'shipping_agent_rate_rows';

    protected $fillable = [
        'workspace_id',
        'shipping_agent_rate_chart_id',
        'country_code',
        'zone',
        'min_weight_kg',
        'max_weight_kg',
        'price',
        'price_per_kg',
        'transit_days',
    ];

    protected function casts(): array
    {
        return [
            'min_weight_kg' => 'decimal:3',
            'max_weight_kg' => 'decimal:3',
            'price' => 'decimal:4',
            'price_per_kg' => 'decimal:4',
        ];
    }

    public function chart(): BelongsTo
    {
        return $this->belongsTo(AgentRateChart::class, 'shipping_agent_rate_chart_id');
    }
}
