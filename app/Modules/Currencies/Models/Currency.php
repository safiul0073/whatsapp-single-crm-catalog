<?php

namespace App\Modules\Currencies\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $table = 'currencies';

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'exchange_rate',
        'rate_synced_at',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate' => 'decimal:8',
            'rate_synced_at' => 'datetime',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get active currencies as code => "CODE - Name" for select options.
     */
    public static function getActiveForSelect(): array
    {
        return static::active()
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (self $c) => [$c->code => "{$c->code} - {$c->name}"])
            ->toArray();
    }

    /**
     * Get active currency codes as a flat array.
     */
    public static function getActiveCodes(): array
    {
        return static::active()->orderBy('code')->pluck('code')->toArray();
    }

    public function isDefault(): bool
    {
        return $this->code === currency_default_code();
    }
}
