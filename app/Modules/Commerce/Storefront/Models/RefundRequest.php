<?php

namespace App\Modules\Commerce\Storefront\Models;

use App\Modules\Commerce\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RefundRequest extends Model
{
    public const OPEN_STATUSES = ['pending_referrer', 'awaiting_admin', 'processing'];

    public const REASONS = ['damaged', 'wrong_item', 'not_as_described', 'not_received', 'changed_mind', 'other'];

    protected $table = 'commerce_refund_requests';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'commission_reversed_minor' => 'integer',
            'commission_shortfall_minor' => 'integer',
            'referrer_decided_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referrer_customer_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(RefundRequestEvent::class)->orderBy('id');
    }
}
