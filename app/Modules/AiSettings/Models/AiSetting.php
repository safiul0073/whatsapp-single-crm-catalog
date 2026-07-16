<?php

namespace App\Modules\AiSettings\Models;

use Illuminate\Database\Eloquent\Model;

class AiSetting extends Model
{
    protected $table = 'ai_settings';

    protected $fillable = ['key', 'value'];
}
