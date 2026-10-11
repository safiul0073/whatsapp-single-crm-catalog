<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class MobileCustomerDevice extends Model
{
    use Notifiable;

    protected $fillable = ['workspace_id', 'customer_reference', 'token', 'platform'];

    public function routeNotificationForFcm(): string
    {
        return $this->token;
    }
}
