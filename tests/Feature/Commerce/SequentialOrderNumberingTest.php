<?php

use App\Models\User;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\UnifiedOrderService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function orderTestContext(): array
{
    $user = User::factory()->create();
    $workspace = app(WorkspaceResolver::class)->current($user);
    $settings = StoreOrderSetting::forWorkspace($workspace->id);
    $product = Product::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Cotton shirt',
        'slug' => 'shirt-'.Str::uuid(),
        'sku' => 'shirt-'.Str::uuid(),
        'status' => 'active',
        'visibility' => 'published',
        'selling_mode' => 'both',
        'single_piece_price' => '20.00',
        'wholesale_price' => '15.00',
    ]);
    $variant = $product->variants()->create([
        'workspace_id' => $workspace->id,
        'sku' => 'shirt-s-'.Str::uuid(),
        'meta_retailer_id' => 'ret-'.Str::uuid(),
        'size' => 'S',
        'price' => 20,
        'stock_quantity' => 50,
        'status' => 'active',
    ]);

    $makePayload = function () use ($product, $variant): array {
        return [
            'submission_reference' => (string) Str::uuid(),
            'source' => 'manual',
            'customer' => ['name' => 'Jane Doe', 'phone' => '+15555550199', 'email' => 'jane@example.test'],
            'shipping_address' => ['name' => 'Jane Doe', 'phone' => '+15555550199', 'line1' => '100 Main St', 'city' => 'Austin', 'country' => 'US'],
            'groups' => [
                ['product_id' => $product->id, 'mode' => 'retail', 'variant_id' => $variant->id, 'quantity' => 1],
            ],
        ];
    };

    return compact('user', 'workspace', 'settings', 'product', 'variant', 'makePayload');
}

it('starts order numbers at 1 and increments dynamically (ORD-00001, ORD-00002, ORD-00003)', function (): void {
    $c = orderTestContext();
    $service = app(UnifiedOrderService::class);

    // Order 1
    $order1 = $service->create($c['workspace'], ($c['makePayload'])());
    expect($order1->number)->toBe('ORD-00001');

    // Order 2
    $order2 = $service->create($c['workspace'], ($c['makePayload'])());
    expect($order2->number)->toBe('ORD-00002');

    // Order 3
    $order3 = $service->create($c['workspace'], ($c['makePayload'])());
    expect($order3->number)->toBe('ORD-00003');

    // Check store order setting updated sequence
    $settings = StoreOrderSetting::forWorkspace($c['workspace']->id);
    expect($settings->next_order_sequence)->toBe(4);
});

it('detects existing numeric order numbers and increments from highest sequence', function (): void {
    $c = orderTestContext();
    $service = app(UnifiedOrderService::class);

    // Simulate an existing order numbered ORD-00042
    Order::query()->create([
        'workspace_id' => $c['workspace']->id,
        'number' => 'ORD-00042',
        'status' => 'requested',
        'currency' => 'USD',
        'subtotal' => 20,
        'source' => 'legacy',
    ]);

    // Next order should dynamically be ORD-00043
    $order = $service->create($c['workspace'], ($c['makePayload'])());
    expect($order->number)->toBe('ORD-00043');

    // And subsequent is ORD-00044
    $nextOrder = $service->create($c['workspace'], ($c['makePayload'])());
    expect($nextOrder->number)->toBe('ORD-00044');
});

it('starts at ORD-00001 when previous orders only had random non-numeric strings', function (): void {
    $c = orderTestContext();
    $service = app(UnifiedOrderService::class);

    // Simulate an existing old order with random 12-char alphanumeric
    Order::query()->create([
        'workspace_id' => $c['workspace']->id,
        'number' => 'ORD-9KJ4D8A2ZQ1P',
        'status' => 'requested',
        'currency' => 'USD',
        'subtotal' => 20,
        'source' => 'legacy',
    ]);

    // Next dynamic order should start from 1 -> ORD-00001
    $order = $service->create($c['workspace'], ($c['makePayload'])());
    expect($order->number)->toBe('ORD-00001');

    $order2 = $service->create($c['workspace'], ($c['makePayload'])());
    expect($order2->number)->toBe('ORD-00002');
});

it('supports custom prefix and digit padding configured in order settings', function (): void {
    $c = orderTestContext();
    $service = app(UnifiedOrderService::class);

    // Custom configuration: Prefix 'INV-', 6 digits padding, start from 500
    $settings = StoreOrderSetting::forWorkspace($c['workspace']->id);
    $settings->update([
        'order_number_prefix' => 'INV-',
        'order_number_digits' => 6,
        'next_order_sequence' => 500,
    ]);

    $order1 = $service->create($c['workspace'], ($c['makePayload'])());
    expect($order1->number)->toBe('INV-000500');

    $order2 = $service->create($c['workspace'], ($c['makePayload'])());
    expect($order2->number)->toBe('INV-000501');
});

it('persists order numbering configuration through order settings form update', function (): void {
    $c = orderTestContext();

    $this->actingAs($c['user'])
        ->put(route('user.commerce.orders.settings.update'), [
            'currency' => 'USD',
            'reservation_hours' => 48,
            'payment_instructions' => 'Payment via Bank transfer or Card',
            'order_number_prefix' => 'FF-',
            'order_number_digits' => 5,
            'next_order_sequence' => 100,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Order settings saved.');

    $updated = StoreOrderSetting::forWorkspace($c['workspace']->id);
    expect($updated->order_number_prefix)->toBe('FF-')
        ->and($updated->order_number_digits)->toBe(5)
        ->and($updated->next_order_sequence)->toBe(100);

    $service = app(UnifiedOrderService::class);
    $order = $service->create($c['workspace'], ($c['makePayload'])());
    expect($order->number)->toBe('FF-00100');
});
