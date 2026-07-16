<?php

namespace App\Modules\PaymentGateways\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    protected $table = 'payments';

    protected $fillable = [
        'uuid',
        'user_type',
        'user_id',
        'gateway',
        'gateway_payment_id',
        'amount',
        'currency',
        'status',
        'payment_method',
        'description',
        'metadata',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
            'paid_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeByGateway(Builder $query, string $gateway): Builder
    {
        return $query->where('gateway', $gateway);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function getFormattedAmountAttribute(): string
    {
        return currency_format($this->amount, $this->currency, true);
    }

    public function getDefaultAmountAttribute(): float
    {
        return currency_convert($this->amount, $this->currency, currency_default_code());
    }

    public function getFormattedDefaultAmountAttribute(): string
    {
        return currency_format($this->default_amount, currency_default_code(), true);
    }
}
