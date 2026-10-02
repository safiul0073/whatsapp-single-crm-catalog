<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;

class StoreOrderSetting extends Model
{
    protected $hidden = ['integration_token_hash'];

    protected $table = 'commerce_store_order_settings';

    protected $fillable = ['whatsapp_notifications', 'whatsapp_channel_id', 'whatsapp_template_id', 'workspace_id', 'currency', 'reservation_hours', 'payment_instructions', 'integration_token_hash'];

    protected function casts(): array
    {
        return ['whatsapp_notifications' => 'boolean', 'reservation_hours' => 'integer'];
    }

    public static function forWorkspace(int $workspaceId): self
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $currency = $workspace->settings['commerce']['currency'] ?? Catalog::query()->where('workspace_id', $workspaceId)->value('currency') ?? 'USD';

        return static::query()->firstOrCreate(['workspace_id' => $workspaceId], ['currency' => strtoupper($currency)]);
    }

    public static function catalogCurrency(int $workspaceId): string
    {
        $workspace = Workspace::findOrFail($workspaceId);

        return strtoupper($workspace->settings['commerce']['currency'] ?? Catalog::query()->where('workspace_id', $workspaceId)->value('currency') ?? 'USD');
    }

    public function precision(): int
    {
        return match ($this->currency) {
            'JPY' => 0,
            'KWD', 'BHD', 'OMR' => 3,
            default => 2,
        };
    }
}
