<?php

namespace App\Modules\PaymentGateways\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    protected $table = 'webhook_logs';

    protected $fillable = [
        'gateway',
        'event_type',
        'gateway_event_id',
        'payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function scopeByGateway(Builder $query, string $gateway): Builder
    {
        return $query->where('gateway', $gateway);
    }

    public function scopeProcessed(Builder $query): Builder
    {
        return $query->whereNotNull('processed_at');
    }

    public function scopeUnprocessed(Builder $query): Builder
    {
        return $query->whereNull('processed_at');
    }
}
