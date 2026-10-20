<?php

namespace App\Modules\Commerce\Storefront\Models;

use App\Modules\Commerce\Models\Order;
use App\Modules\Contacts\Models\Contact;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'commerce_customers';

    protected $guarded = ['id'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'verified_at' => 'datetime',
            'registered_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'contact_id', 'contact_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class, 'customer_id');
    }

    public function channelLabel(): string
    {
        return match ($this->source) {
            'mobile_app' => __('Mobile App'),
            default => __('Storefront'),
        };
    }

    public function channelIcon(): string
    {
        return match ($this->source) {
            'mobile_app' => 'ph-device-mobile',
            default => 'ph-globe',
        };
    }
}
