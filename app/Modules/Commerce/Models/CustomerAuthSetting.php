<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerAuthSetting extends Model
{
    protected $table = 'commerce_customer_auth_settings';

    protected $fillable = ['workspace_id', 'enabled', 'channel_id', 'authentication_template_id', 'welcome_template_id'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public static function forWorkspace(int $workspaceId): self
    {
        return static::query()->firstOrCreate(['workspace_id' => $workspaceId]);
    }
}
