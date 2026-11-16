<?php

namespace App\Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ShippingCharge extends Model
{
    public const REASONS = [
        'remote_area' => 'Remote Area',
        'fuel' => 'Fuel',
        'additional_handling' => 'Additional Handling',
        'overweight' => 'Overweight',
        'oversize' => 'Oversize',
        'address_correction' => 'Address Correction',
        'customs' => 'Customs/Brokerage',
        'other' => 'Other',
    ];

    protected $table = 'shipping_charges';

    protected $fillable = ['workspace_id', 'chargeable_type', 'chargeable_id', 'reason', 'label', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:4'];
    }

    public function chargeable(): MorphTo
    {
        return $this->morphTo();
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? ucfirst($this->reason);
    }
}
