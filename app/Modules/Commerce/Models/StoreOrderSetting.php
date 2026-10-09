<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class StoreOrderSetting extends Model
{
    protected $hidden = ['integration_token_hash'];

    protected $table = 'commerce_store_order_settings';

    protected $fillable = ['whatsapp_notifications', 'whatsapp_channel_id', 'whatsapp_template_id', 'owner_whatsapp_number', 'workspace_id', 'currency', 'reservation_hours', 'payment_instructions'];

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
        $methods = $workspace->settings['commerce']['payment_methods'] ?? self::defaultPaymentMethods();

        return collect($methods)->filter(fn ($method) => ! $activeOnly || (! empty($method['active']) && filled($method['recipient_details'] ?? null)))
            ->sortBy('sort_order')->map(fn ($method) => array_replace($method, ['icon_url' => self::paymentIconUrl($workspaceId, $method), 'active' => ! empty($method['active']) ? '1' : '0', 'fields' => array_map(fn ($field) => array_replace($field, ['required' => ! empty($field['required']) ? '1' : '0']), $method['fields'] ?? [])]))->values()->all();
    }

    public static function defaultPaymentMethods(): array
    {
        return [
            [
                'id' => 'remitly',
                'name' => 'Remitly',
                'recipient_details' => "Account Name: Global Garments Export Ltd\nBank: Standard Chartered Bank\nAccount Number: 01-8492049-01\nBranch: Dhaka Main Branch, Bangladesh\nSwift/BIC: SCBLBDDX\nPhone: +880 1712 345678",
                'instructions' => "1. Open Remitly and select send to Bangladesh.\n2. Choose Bank Deposit and enter the details above.\n3. Send the exact order total amount.\n4. Enter your transaction ID and upload payment screenshot below.",
                'active' => '0',
                'sort_order' => 0,
                'fields' => [
                    ['name' => 'sender_name', 'label' => 'Sender Full Name', 'required' => '1'],
                    ['name' => 'sender_phone', 'label' => 'Sender Phone Number', 'required' => '0'],
                ],
            ],
            [
                'id' => 'taptap-send',
                'name' => 'Taptap Send',
                'recipient_details' => "Recipient Name: Global Garments Export Ltd\nWallet / bKash Number: +880 1819 876543\nAccount Type: Merchant / Personal\nCountry: Bangladesh",
                'instructions' => "1. Open the Taptap Send app on your mobile device.\n2. Select Bangladesh and enter the recipient mobile number above.\n3. Transfer the exact order total.\n4. Enter the transfer reference / transaction ID and upload screenshot below.",
                'active' => '0',
                'sort_order' => 1,
                'fields' => [
                    ['name' => 'sender_name', 'label' => 'Sender Full Name', 'required' => '1'],
                ],
            ],
            [
                'id' => 'moneygram',
                'name' => 'MoneyGram',
                'recipient_details' => "Receiver Name: MD SAFIUL ISLAM\nCountry: Bangladesh\nCity: Dhaka\nPhone: +880 1911 223344",
                'instructions' => "1. Send money online or visit any MoneyGram agent location.\n2. Use the exact receiver name and country shown above.\n3. Enter the 8-digit reference number (MTCN) as Transaction ID and upload the receipt.",
                'active' => '0',
                'sort_order' => 2,
                'fields' => [
                    ['name' => 'sender_name', 'label' => 'Sender Name', 'required' => '1'],
                    ['name' => 'mtcn_number', 'label' => '8-Digit MTCN', 'required' => '1'],
                ],
            ],
        ];
    }

    private static function paymentIconUrl(int $workspaceId, array $method): ?string
    {
        $path = $method['icon_path'] ?? null;
        if (is_string($path) && preg_match('#^payment-icons/'.$workspaceId.'/[a-zA-Z0-9]+\.(png|jpg|jpeg|webp)$#', $path)) {
            return Storage::disk('public')->url($path);
        }

        return asset('images/payment-services/'.(in_array($method['id'], ['remitly', 'taptap-send', 'moneygram']) ? $method['id'] : 'manual').'.svg');
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
