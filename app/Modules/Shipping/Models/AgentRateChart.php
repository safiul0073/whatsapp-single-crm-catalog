<?php

namespace App\Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentRateChart extends Model
{
    public const STATUSES = ['pending_review', 'active', 'archived'];

    protected $table = 'shipping_agent_rate_charts';

    protected $fillable = [
        'workspace_id',
        'shipping_agent_id',
        'name',
        'source_type',
        'file_path',
        'original_name',
        'currency',
        'effective_from',
        'expires_on',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'expires_on' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(ShippingAgent::class, 'shipping_agent_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(AgentRateRow::class, 'shipping_agent_rate_chart_id');
    }

    /**
     * Charts that are reviewed, already effective and not past their expiry date.
     */
    public function scopeUsable($query)
    {
        $today = now()->toDateString();

        return $query->where('status', 'active')
            ->where(fn ($dates) => $dates->whereNull('effective_from')->orWhereDate('effective_from', '<=', $today))
            ->where(fn ($dates) => $dates->whereNull('expires_on')->orWhereDate('expires_on', '>=', $today));
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->lt(now()->startOfDay());
    }
}
