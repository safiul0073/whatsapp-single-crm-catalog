<?php

namespace App\Modules\Commerce\Storefront\Models;

use App\Modules\Contacts\Models\Contact;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        return ['password' => 'hashed', 'verified_at' => 'datetime', 'registered_at' => 'datetime'];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
