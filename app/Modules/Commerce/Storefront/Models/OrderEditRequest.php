<?php

namespace App\Modules\Commerce\Storefront\Models;

use App\Models\User;
use App\Modules\Commerce\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderEditRequest extends Model
{
    /** A request that still blocks a new one: waiting for the owner, or approved and not yet used. */
    public const OPEN_STATUSES = ['pending', 'approved'];

    protected $table = 'commerce_order_edit_requests';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
            'used_at' => 'datetime',
            'previous_total' => 'decimal:4',
            'new_total' => 'decimal:4',
            'difference' => 'decimal:4',
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

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
