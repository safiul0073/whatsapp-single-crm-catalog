<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppCustomerRegistration extends Model
{
    protected $table = 'commerce_whatsapp_customer_registrations';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['payload_hash'];

    protected function casts(): array
    {
        return ['consented_at' => 'datetime', 'welcome_sent_at' => 'datetime'];
    }
}
