<?php

namespace App\Modules\Frontend\Models;

use Illuminate\Database\Eloquent\Model;

class FrontendThemeSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];
}
