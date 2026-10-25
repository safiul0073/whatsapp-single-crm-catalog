<?php

namespace App\Modules\Commerce\Storefront\Models;

use App\Modules\Commerce\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralTransaction extends Model
{
    protected $table = 'commerce_referral_transactions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['order_amount_minor' => 'integer', 'reward_minor' => 'integer', 'credited_at' => 'datetime'];
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
