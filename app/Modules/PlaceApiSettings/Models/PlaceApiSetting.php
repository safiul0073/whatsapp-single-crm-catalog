<?php

namespace App\Modules\PlaceApiSettings\Models;

use Illuminate\Database\Eloquent\Model;

class PlaceApiSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];
}
