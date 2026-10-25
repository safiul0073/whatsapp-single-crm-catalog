<?php

namespace App\Modules\Commerce\Storefront\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralSetting extends Model
{
    protected $table = 'commerce_referral_settings';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'cashin_enabled' => 'boolean', 'cashin_methods' => 'array', 'max_redeem_percent' => 'integer', 'max_referrals_per_customer' => 'integer'];
    }
}
