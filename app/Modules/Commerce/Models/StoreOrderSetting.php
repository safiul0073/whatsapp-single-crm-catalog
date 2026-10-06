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

    public static function paymentMethods(int $workspaceId, bool $activeOnly = false): array
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $methods = $workspace->settings['commerce']['payment_methods'] ?? array_map(fn ($name, $id) => [
            'id' => $id, 'name' => $name, 'recipient_details' => '', 'instructions' => '', 'active' => '0', 'sort_order' => 0, 'fields' => [],
        ], ['Remitly', 'Taptap Send', 'MoneyGram'], ['remitly', 'taptap-send', 'moneygram']);

        return collect($methods)->filter(fn ($method) => ! $activeOnly || (! empty($method['active']) && filled($method['recipient_details'] ?? null)))
            ->sortBy('sort_order')->map(fn ($method) => array_replace($method, ['active' => ! empty($method['active']) ? '1' : '0', 'fields' => array_map(fn ($field) => array_replace($field, ['required' => ! empty($field['required']) ? '1' : '0']), $method['fields'] ?? [])]))->values()->all();
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
