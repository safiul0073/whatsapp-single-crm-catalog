<?php

namespace App\Modules\Commerce\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Http\Requests\CreateUnifiedOrderRequest;
use App\Modules\Commerce\Http\Requests\PackOrderRequest;
use App\Modules\Commerce\Http\Requests\StoreCustomerAuthSettingsRequest;
use App\Modules\Commerce\Http\Requests\StoreOrderSettingsRequest;
use App\Modules\Commerce\Http\Requests\StorePaymentServicesRequest;
use App\Modules\Commerce\Models\Catalog;
use App\Modules\Commerce\Models\CustomerAuthSetting;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Models\WhatsAppAuthChallenge;
use App\Modules\Commerce\Models\WhatsAppCustomerRegistration;
use App\Modules\Commerce\Services\OrderPackingService;
use App\Modules\Commerce\Services\UnifiedOrderService;
use App\Modules\Commerce\Services\WhatsAppCustomerAuthService;
use App\Modules\Contacts\Models\Contact;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OrderManagementController extends Controller
{
    public function __construct(protected WorkspaceResolver $workspaces) {}

    public function create(Request $request, ?Order $order = null): View
    {
        $workspace = $this->workspaces->current($request->user());
        if ($order) {
            $this->assertOrder($request, $order);
            abort_unless($order->status === 'needs_details' && ! $order->groups()->exists(), 422);
        }
        $products = Product::query()->where('workspace_id', $workspace->id)->where('status', 'active')->where('visibility', 'published')->with(['variants', 'colors'])->get()->map(fn ($product) => [
            'id' => $product->id, 'name' => $product->name, 'mode' => $product->selling_mode,
            'variants' => $product->variants->where('status', 'active')->map(fn ($variant) => ['id' => $variant->id, 'name' => $variant->sku.' · '.$variant->size, 'stock' => $variant->stock_quantity])->values(),
            'colors' => $product->colors->map(fn ($color) => ['id' => $color->id, 'name' => $color->name]),
            'ratios' => $product->getEffectiveSizeRatios(), 'multiplier' => $product->ws_ratio_multiplier ?? 1, 'minimum' => $product->ws_main_moq ?? 1,
        ]);

        return view('commerce::user.create-order', ['completingOrder' => $order, 'products' => $products, 'contacts' => Contact::query()->where('workspace_id', $workspace->id)->orderBy('name')->get(), 'settings' => StoreOrderSetting::forWorkspace($workspace->id)]);
    }

    public function preview(CreateUnifiedOrderRequest $request, UnifiedOrderService $orders): JsonResponse
    {
        return response()->json(['data' => $orders->preview($this->workspaces->current($request->user()), $request->validated())]);
    }

    public function store(CreateUnifiedOrderRequest $request, UnifiedOrderService $orders): RedirectResponse
    {
        $data = $request->validated();
        $data['source'] = 'manual';
        unset($data['adjustments'], $data['customer_reference']);
        $order = $orders->create($this->workspaces->current($request->user()), $data);

        return redirect()->route('user.commerce.orders.show', $order)->with('success', 'Order saved.');
    }

    public function complete(CreateUnifiedOrderRequest $request, Order $order, UnifiedOrderService $orders): RedirectResponse
    {
        $this->assertOrder($request, $order);
        $data = $request->validated();
        unset($data['adjustments'], $data['customer_reference']);
        $orders->completeRequest($order, $this->workspaces->current($request->user()), $data);

        return redirect()->route('user.commerce.orders.show', $order)->with('success', 'Order details confirmed.');
    }

    public function settings(Request $request): View
    {
        $workspaceId = $this->workspaces->current($request->user())->id;

        return view('commerce::user.order-settings', ['channels' => ChannelAccount::query()->where('workspace_id', $workspaceId)->where('provider', 'whatsapp')->where('status', 'connected')->get(), 'templates' => MessageTemplate::query()->where('workspace_id', $workspaceId)->where('provider', 'whatsapp')->where('category', 'utility')->get(), 'settings' => StoreOrderSetting::forWorkspace($workspaceId), 'paymentMethods' => StoreOrderSetting::paymentMethods($workspaceId), 'defaultPaymentMethods' => StoreOrderSetting::defaultPaymentMethods()]);
    }

    public function customerAuthSettings(Request $request): View
    {
        $workspaceId = $this->workspaces->current($request->user())->id;
        $welcomeAttempts = WhatsAppCustomerRegistration::query()->where('workspace_id', $workspaceId)->latest()->limit(10)->get();

        return view('whatsapp-cloud::user.customer-login', ['welcomeAttempts' => $welcomeAttempts, 'channels' => ChannelAccount::query()->where('workspace_id', $workspaceId)->where('provider', 'whatsapp')->where('status', 'connected')->get(), 'authTemplates' => MessageTemplate::query()->where('workspace_id', $workspaceId)->where('provider', 'whatsapp')->where('category', 'authentication')->get(), 'welcomeTemplates' => MessageTemplate::query()->where('workspace_id', $workspaceId)->where('provider', 'whatsapp')->whereIn('category', ['utility', 'marketing'])->get(), 'authSettings' => CustomerAuthSetting::forWorkspace($workspaceId)]);
    }

    public function updateAuthSettings(StoreCustomerAuthSettingsRequest $request, WhatsAppCustomerAuthService $auth): RedirectResponse
    {
        $workspaceId = $this->workspaces->current($request->user())->id;
        $data = $request->validated();
        foreach (['channel_id' => ChannelAccount::class, 'authentication_template_id' => MessageTemplate::class, 'welcome_template_id' => MessageTemplate::class] as $field => $model) {
            if (! empty($data[$field])) {
                abort_unless($model::query()->where('workspace_id', $workspaceId)->whereKey($data[$field])->exists(), 404);
            }
        }
        $settings = CustomerAuthSetting::forWorkspace($workspaceId);
        $settings->fill($data);
        if ($settings->enabled) {
            try {
                $auth->configuration($workspaceId, $settings);
            } catch (HttpException $exception) {
                throw ValidationException::withMessages(['enabled' => $exception->getMessage()]);
            }
        }
        $settings->save();
        if (! $settings->enabled) {
            WhatsAppAuthChallenge::query()->where('workspace_id', $workspaceId)->whereNull('consumed_at')->whereNull('invalidated_at')->update(['invalidated_at' => now()]);
        }

        return back()->with('success', 'WhatsApp customer login settings saved.');
    }

    public function paymentServices(Request $request): View
    {
        $workspaceId = $this->workspaces->current($request->user())->id;

        return view('commerce::user.payment-services', [
            'paymentMethods' => StoreOrderSetting::paymentMethods($workspaceId),
            'defaultPaymentMethods' => StoreOrderSetting::defaultPaymentMethods(),
        ]);
    }

    public function updatePaymentServices(StorePaymentServicesRequest $request): RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $data = $request->validated();
        $this->persistPaymentMethods($workspace, $data['payment_methods'], $request);

        return back()->with('success', 'Payment services saved successfully.');
    }

    public function updateSettings(StoreOrderSettingsRequest $request): RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $settings = StoreOrderSetting::forWorkspace($workspace->id);
        $data = $request->validated();
        foreach (['whatsapp_channel_id' => ChannelAccount::class, 'whatsapp_template_id' => MessageTemplate::class] as $field => $model) {
            if (! empty($data[$field])) {
                abort_unless($model::query()->where('workspace_id', $settings->workspace_id)->whereKey($data[$field])->exists(), 404);
            }
        }
        $methods = $data['payment_methods'] ?? null;
        unset($data['payment_methods'], $data['payment_icons']);

        if ($methods !== null) {
            $this->persistPaymentMethods($workspace, $methods, $request);
            $workspace = $workspace->fresh();
        }

        $settings->update($data);
        Catalog::query()->where('workspace_id', $settings->workspace_id)->update(['currency' => $settings->currency]);
        $workspaceSettings = $workspace->settings ?? [];
        $workspaceSettings['commerce']['currency'] = $settings->currency;
        $workspace->update(['settings' => $workspaceSettings]);

        return back()->with('success', 'Store order settings saved. Currency changes apply to new orders. Review product and shipping prices when changing currency.');
    }

    private function persistPaymentMethods(Workspace $workspace, array $methods, Request $request): void
    {
        $oldMethods = collect(StoreOrderSetting::paymentMethods($workspace->id))->keyBy('id');
        $obsoleteIcons = [];

        foreach ($methods as &$method) {
            $oldIcon = $oldMethods->get($method['id'])['icon_path'] ?? null;
            if ($oldIcon && empty($method['remove_icon'])) {
                $method['icon_path'] = $oldIcon;
            }
            if ($request->hasFile('payment_icons.'.$method['id'])) {
                $method['icon_path'] = $request->file('payment_icons.'.$method['id'])->store('payment-icons/'.$workspace->id, 'public');
                if (! $method['icon_path']) {
                    throw ValidationException::withMessages(['payment_icons' => 'The icon could not be saved. Please retry.']);
                }
            }
            if ($oldIcon && ($method['icon_path'] ?? null) !== $oldIcon) {
                $obsoleteIcons[] = $oldIcon;
            }
            unset($method['remove_icon']);
        }
        unset($method);

        foreach ($oldMethods as $oldMethod) {
            if (! in_array($oldMethod['id'], array_column($methods, 'id')) && ! empty($oldMethod['icon_path'])) {
                $obsoleteIcons[] = $oldMethod['icon_path'];
            }
        }

        $workspaceSettings = $workspace->settings ?? [];
        $workspaceSettings['commerce']['payment_methods'] = $methods;
        $workspace->update(['settings' => $workspaceSettings]);

        foreach ($obsoleteIcons as $path) {
            if (str_starts_with($path, 'payment-icons/'.$workspace->id.'/')) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    public function retailBox(PackOrderRequest $request, Order $order, OrderPackingService $packing): RedirectResponse
    {
        $this->assertOrder($request, $order);
        $packing->addRetailBox($order, $request->validated('quantities'));

        return back()->with('success', 'Retail box created.');
    }

    public function packed(Request $request, Order $order, int $box, OrderPackingService $packing): RedirectResponse
    {
        $this->assertOrder($request, $order);
        $packing->markPacked($order, $box);

        return back()->with('success', 'Box marked packed.');
    }

    public function slip(Request $request, Order $order): View
    {
        $this->assertOrder($request, $order);

        return view('commerce::user.packing-slip', ['order' => $order->load('boxes.contents.item')]);
    }

    public function receipt(Request $request, Order $order): BinaryFileResponse
    {
        $this->assertOrder($request, $order);
        $index = $request->integer('index', 0);
        $path = $index >= 0 ? ($order->payment_evidence['receipts'][$index]['path'] ?? ($index === 0 ? ($order->payment_evidence['receipt_path'] ?? null) : null)) : null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Content-Disposition' => 'attachment']);
    }

    private function assertOrder(Request $request, Order $order): void
    {
        abort_unless($order->workspace_id === $this->workspaces->current($request->user())->id, 404);
    }
}
