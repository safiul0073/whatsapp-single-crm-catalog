<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppAuthChallenge extends Model
{
    protected $table = 'commerce_whatsapp_auth_challenges';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['code_hash', 'grant_hash', 'session_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'invalidated_at' => 'datetime', 'verified_at' => 'datetime', 'grant_expires_at' => 'datetime', 'consumed_at' => 'datetime', 'consented_at' => 'datetime'];
    }
}
