<?php

namespace App\Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemoteArea extends Model
{
    public const STATUSES = ['normal' => 'Normal', 'remote' => 'Remote', 'extended_remote' => 'Extended Remote'];

    public const UNKNOWN = 'unknown';

    protected $table = 'shipping_remote_areas';

    protected $fillable = [
        'workspace_id',
        'carrier',
        'shipping_agent_id',
        'country_code',
        'postal_from',
        'postal_to',
        'city',
        'status',
        'fee',
        'currency',
        'verified_by',
        'verified_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'fee' => 'decimal:4',
            'verified_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(ShippingAgent::class, 'shipping_agent_id');
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUSES[$status] ?? 'Unknown / Pending Verification';
    }
}
