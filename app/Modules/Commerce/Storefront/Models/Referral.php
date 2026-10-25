<?php

namespace App\Modules\Commerce\Storefront\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Referral extends Model
{
    protected $table = 'commerce_referrals';

    protected $guarded = ['id'];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referrer_customer_id');
    }

    public function referee(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referee_customer_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ReferralTransaction::class);
    }
}
