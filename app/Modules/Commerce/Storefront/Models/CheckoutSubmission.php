<?php

namespace App\Modules\Commerce\Storefront\Models;

use Illuminate\Database\Eloquent\Model;

class CheckoutSubmission extends Model
{
    protected $table = 'commerce_checkout_submissions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
