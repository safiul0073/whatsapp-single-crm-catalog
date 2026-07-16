<?php

namespace App\Modules\PaymentGatewaySettings\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\ManualPaymentMethods\Models\ManualPaymentMethod;
use App\Modules\PaymentGatewaySettings\Services\PaymentGatewaySettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class PaymentGatewaySettingsController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:payment-gateway-settings.view', only: ['index']),
            new Middleware('permission:payment-gateway-settings.edit', only: ['update']),
        ];
    }

    public function __construct(
        protected PaymentGatewaySettingsService $service
    ) {}

    public function index(): View
    {
        $groups = $this->service->getGroupedDefinitions();
        $manualMethods = ManualPaymentMethod::orderBy('sort_order')->get();

        foreach ($manualMethods as $method) {
            $groups[$method->slug] = $this->buildManualGroup($method);
        }

        return view('payment-gateway-settings::admin.index', compact('groups'));
    }

    public function update(Request $request): RedirectResponse
    {
        $settings = $request->input('settings', []);
        $rules = [];
        $attributes = [];

        foreach (config('payment-gateway-settings', []) as $group) {
            foreach ($group['settings'] as $key => $definition) {
                if (isset($definition['rules'])) {
                    $rules["settings.{$key}"] = $definition['rules'];
                    $attributes["settings.{$key}"] = $definition['label'];
                }
            }
        }

        $request->validate($rules, [], $attributes);

        foreach ($settings as $key => $value) {
            $this->service->set($key, $value);
        }

        $tab = $request->input('_active_tab', array_key_first(config('payment-gateway-settings', [])));

        return redirect()->to(route('admin.payment-gateway-settings.index').'#'.$tab)
            ->with('success', __('Payment gateway settings updated successfully.'));
    }

    protected function buildManualGroup(ManualPaymentMethod $method): array
    {
        $slug = $method->slug;
        $dbValues = $this->service->getAllValues();

        $settings = [
            "{$slug}_enabled" => [
                'type' => 'feature',
                'label' => 'Enable '.$method->name,
                'hint' => 'Accept payments via '.$method->name,
                'default' => false,
                'key' => "{$slug}_enabled",
                'value' => (bool) ($dbValues["{$slug}_enabled"] ?? false),
            ],
            "{$slug}_instructions" => [
                'type' => 'editor',
                'label' => 'Pay Instructions',
                'hint' => 'Instructions shown to the customer (account details, steps, etc.)',
                'default' => '',
                'key' => "{$slug}_instructions",
                'value' => $dbValues["{$slug}_instructions"] ?? '',
            ],
            "{$slug}_user_fields" => [
                'type' => 'user_fields',
                'label' => 'User Input Fields',
                'hint' => 'Custom fields the customer must fill during checkout (e.g. Transaction ID, Account Number).',
                'default' => '[]',
                'key' => "{$slug}_user_fields",
                'value' => json_decode($dbValues["{$slug}_user_fields"] ?? '[]', true) ?: [],
            ],
            "{$slug}_supported_currencies" => [
                'type' => 'tags',
                'label' => 'Supported Currencies',
                'default' => '',
                'layout' => 'sidebar',
                'key' => "{$slug}_supported_currencies",
                'value' => isset($dbValues["{$slug}_supported_currencies"]) && $dbValues["{$slug}_supported_currencies"]
                    ? explode(',', $dbValues["{$slug}_supported_currencies"])
                    : [],
            ],
            "{$slug}_logo" => [
                'type' => 'media',
                'label' => 'Gateway Logo',
                'default' => null,
                'accept' => 'image',
                'layout' => 'sidebar',
                'key' => "{$slug}_logo",
                'value' => $dbValues["{$slug}_logo"] ?? null,
            ],
            "{$slug}_min_amount" => [
                'type' => 'number',
                'label' => 'Minimum Amount',
                'default' => '0',
                'layout' => 'sidebar',
                'key' => "{$slug}_min_amount",
                'value' => $dbValues["{$slug}_min_amount"] ?? '0',
            ],
            "{$slug}_max_amount" => [
                'type' => 'number',
                'label' => 'Maximum Amount',
                'default' => '0',
                'layout' => 'sidebar',
                'key' => "{$slug}_max_amount",
                'value' => $dbValues["{$slug}_max_amount"] ?? '0',
            ],
            "{$slug}_fixed_charge" => [
                'type' => 'number',
                'label' => 'Fixed Charge',
                'default' => '0',
                'layout' => 'sidebar',
                'key' => "{$slug}_fixed_charge",
                'value' => $dbValues["{$slug}_fixed_charge"] ?? '0',
            ],
            "{$slug}_percent_charge" => [
                'type' => 'number',
                'label' => 'Percent Charge',
                'default' => '0',
                'layout' => 'sidebar',
                'key' => "{$slug}_percent_charge",
                'value' => $dbValues["{$slug}_percent_charge"] ?? '0',
            ],
        ];

        return [
            'label' => $method->name,
            'icon' => $method->icon,
            'description' => 'Manual payment gateway',
            'layout' => 'full',
            'is_manual' => true,
            'manual_method_id' => $method->id,
            'webhook_url' => false,
            'settings' => $settings,
        ];
    }
}
