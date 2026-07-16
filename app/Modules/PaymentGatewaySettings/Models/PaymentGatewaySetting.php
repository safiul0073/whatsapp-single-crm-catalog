<?php

namespace App\Modules\PaymentGatewaySettings\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGatewaySetting extends Model
{
    protected $table = 'payment_gateway_settings';

    protected $fillable = ['key', 'value'];
}
