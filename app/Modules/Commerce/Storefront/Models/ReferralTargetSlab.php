<?php

namespace App\Modules\Commerce\Storefront\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralTargetSlab extends Model
{
    protected $table = 'commerce_referral_target_slabs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'min_referrals' => 'integer'];
    }
}
