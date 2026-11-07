<?php

use App\Models\User;
use App\Modules\Commerce\Jobs\NotifyOrderEvent;
use App\Modules\Commerce\Models\InventoryMovement;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\OrderPackingService;
use App\Modules\Commerce\Services\OrderWorkflowService;
use App\Modules\Commerce\Services\UnifiedOrderService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake([NotifyOrderEvent::class]);
    $this->staff = User::factory()->create();
    $this->workspace = app(WorkspaceResolver::class)->current($this->staff);
    StoreOrderSetting::forWorkspace($this->workspace->id);
    $this->product = Product::query()->create([
        'workspace_id' => $this->workspace->id, 'name' => 'Edit shirt', 'slug' => 'edit-shirt', 'sku' => 'edit-shirt',
        'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'both', 'single_piece_price' => 10,
        'wholesale_price' => 6, 'ws_enabled' => true, 'ws_main_moq' => 3, 'ws_color_moq' => 1,
        'ws_min_sizes' => 1, 'ws_ratio_multiplier' => 1,
    ]);
    $this->color = $this->product->colors()->create(['workspace_id' => $this->workspace->id, 'name' => 'Red']);
    $this->product->update(['ws_size_ratios' => [$this->color->id => ['S' => 1, 'M' => 1, 'L' => 1]]]);
    $this->variants = collect(['S', 'M', 'L'])->map(fn ($size) => $this->product->variants()->create([
        'workspace_id' => $this->workspace->id, 'color_id' => $this->color->id, 'sku' => 'edit-'.$size,
        'meta_retailer_id' => 'edit-'.$size, 'size' => $size, 'price' => 10, 'stock_quantity' => 100, 'status' => 'active',
    ]));
    $this->address = ['name' => 'Test buyer', 'phone' => '+15555550100', 'line1' => '1 Main Street', 'city' => 'Boston', 'country' => 'US'];
    $this->wholesale = fn (int $boxes): array => ['product_id' => $this->product->id, 'mode' => 'wholesale', 'color_id' => $this->color->id, 'box_count' => $boxes];
    $this->createOrder = fn (array $groups): Order => app(UnifiedOrderService::class)->create($this->workspace, [
        'submission_reference' => (string) Str::uuid(), 'source' => 'manual',
        'customer' => ['name' => 'Test buyer', 'phone' => '+15555550100'],
        'shipping_address' => $this->address,
        'groups' => $groups,
    ]);
    $this->edit = fn (Order $order, array $groups, array $extra = []) => $this->put(route('user.commerce.orders.update', $order), array_merge([
        'submission_reference' => (string) Str::uuid(),
        'groups' => $groups,
    ], $extra));
    $this->pay = fn (Order $order): Order => app(OrderWorkflowService::class)->transition($order->fresh(), 'paid');
    $this->actingAs($this->staff);
});

it('opens the edit page with the order lines filled in and offers it only before shipping', function () {
    $order = ($this->createOrder)([($this->wholesale)(2)]);

    $this->get(route('user.commerce.orders.show', $order))->assertOk()->assertSee(route('user.commerce.orders.edit', $order), false)->assertSee('Edit order');
    $this->get(route('user.commerce.orders.edit', $order))->assertOk()
        ->assertSee('Edit order '.$order->number)
        ->assertSee('Save changes')
        ->assertDontSee('Save draft')
        ->assertDontSee('Customer name')
        ->assertDontSee('Recipient same as customer')
        ->assertDontSee('Best available shipping / request quote')
        ->assertSee('Customer, delivery address and shipping method stay as they are.')
        ->assertSee('order-edit-list', false)
        ->assertDontSee('Purchase type</label>', false)
        ->assertSee("body.delete('_method')", false)
        ->assertSee(route('user.commerce.orders.update', $order), false)
        ->assertViewHas('orderGroups', fn (array $groups): bool => $groups === [['product_id' => $this->product->id, 'mode' => 'wholesale', 'color_id' => $this->color->id, 'box_count' => 2]]);

    $this->get(route('user.commerce.orders.create'))->assertOk()->assertSee('Purchase type</label>', false)->assertDontSee('order-edit-list', false);

    $order->update(['status' => 'shipped', 'shipped_at' => now()]);
    $this->get(route('user.commerce.orders.show', $order))->assertOk()->assertDontSee(route('user.commerce.orders.edit', $order), false);
    $this->get(route('user.commerce.orders.edit', $order))->assertStatus(422);
    ($this->edit)($order, [($this->wholesale)(1)])->assertStatus(422);
});

it('reprices an unpaid order and moves its stock hold to the new lines', function () {
    $order = ($this->createOrder)([($this->wholesale)(1)]);

    ($this->edit)($order, [($this->wholesale)(2)])->assertRedirect(route('user.commerce.orders.show', $order))->assertSessionHasNoErrors();
    $order->refresh();
    expect($order->items()->sum('quantity'))->toEqual(6)
        ->and($order->boxes()->count())->toBe(2)
        ->and($order->total)->toBe('36.0000')
        ->and($order->status)->toBe('awaiting_payment')
        ->and($order->reservations()->where('state', 'reserved')->sum('quantity'))->toEqual(6)
        ->and($order->events()->where('key', 'edited_1')->value('label'))->toBe('Order edited · total USD 18.00 → USD 36.00');

    ($this->edit)($order, [['product_id' => $this->product->id, 'mode' => 'retail', 'variant_id' => $this->variants[0]->id, 'quantity' => 2]])->assertSessionHasNoErrors();
    $order->refresh();
    expect($order->groups()->pluck('mode')->all())->toBe(['retail'])
        ->and($order->reservations()->where('variant_id', $this->variants[0]->id)->value('quantity'))->toBe(2)
        ->and($order->reservations()->where('state', 'released')->pluck('variant_id')->sort()->values()->all())->toBe([$this->variants[1]->id, $this->variants[2]->id])
        ->and($order->events()->where('key', 'edited_2')->exists())->toBeTrue();
});

it('takes out or puts back stock when a paid order is edited', function () {
    $order = ($this->pay)(($this->createOrder)([($this->wholesale)(1)]));
    expect($this->variants[0]->fresh()->stock_quantity)->toBe(99);

    ($this->edit)($order, [($this->wholesale)(3)])->assertSessionHasNoErrors();
    expect($this->variants[0]->fresh()->stock_quantity)->toBe(97)
        ->and($order->fresh()->status)->toBe('paid')
        ->and($order->fresh()->payment_state)->toBe('paid');

    ($this->edit)($order, [($this->wholesale)(1)])->assertSessionHasNoErrors();
    expect($this->variants[0]->fresh()->stock_quantity)->toBe(99)
        ->and(InventoryMovement::query()->where('order_id', $order->id)->where('reason', 'order_edited')->count())->toBe(6)
        ->and($order->reservations()->where('state', 'consumed')->sum('quantity'))->toEqual(3);

    app(OrderWorkflowService::class)->transition($order->fresh(), 'cancelled');
    expect($this->variants[0]->fresh()->stock_quantity)->toBe(100);
});

it('refuses to add pieces to a paid order when stock has run out', function () {
    $order = ($this->pay)(($this->createOrder)([($this->wholesale)(1)]));
    $this->variants[0]->update(['stock_quantity' => 0]);

    ($this->edit)($order, [($this->wholesale)(2)])->assertSessionHasErrors('stock');
    expect($order->fresh()->items()->sum('quantity'))->toEqual(3)
        ->and($this->variants[0]->fresh()->stock_quantity)->toBe(0);
});

it('rebuilds the boxes of a packed order and sends it back to processing', function () {
    $order = ($this->pay)(($this->createOrder)([($this->wholesale)(1)]));
    app(OrderWorkflowService::class)->transition($order->fresh(), 'processing');
    foreach ($order->fresh()->boxes as $box) {
        app(OrderPackingService::class)->markPacked($order->fresh(), $box->id);
    }
    expect($order->fresh()->status)->toBe('packed');

    ($this->edit)($order, [($this->wholesale)(2)])->assertSessionHasNoErrors();
    $order->refresh();
    expect($order->status)->toBe('processing')
        ->and($order->packed_at)->toBeNull()
        ->and($order->boxes()->count())->toBe(2)
        ->and($order->boxes()->whereNotNull('packed_at')->count())->toBe(0);
});

it('keeps a customer-built pack as ordered and rejects packs from another order', function () {
    $snapshot = ['pack_instance_id' => 'pack-1', 'template_id' => 1, 'name' => 'Mix pack', 'selection' => ['pack_count' => 1]];
    $order = ($this->createOrder)([['product_id' => $this->product->id, 'mode' => 'wholesale', 'color_id' => $this->color->id, 'box_count' => 1, 'size_quantities' => ['S' => 2, 'L' => 1], 'pack_snapshot' => $snapshot]]);
    $packGroup = $order->groups()->first();
    $other = ($this->createOrder)([($this->wholesale)(1)]);

    $this->get(route('user.commerce.orders.edit', $order))->assertOk()
        ->assertViewHas('orderGroups', fn (array $groups): bool => $groups[0]['keep_group_id'] === $packGroup->id && $groups[0]['locked'] === true);

    $kept = ['product_id' => $this->product->id, 'mode' => 'wholesale', 'keep_group_id' => $packGroup->id];
    ($this->edit)($order, [$kept, ($this->wholesale)(1)])->assertSessionHasNoErrors();
    $groups = $order->fresh()->groups;
    expect($groups)->toHaveCount(2)
        ->and($groups->first()->pack_snapshot['name'])->toBe('Mix pack')
        ->and($groups->first()->ratio)->toEqual(['S' => 2, 'L' => 1]);

    ($this->edit)($other, [array_merge($kept, ['keep_group_id' => $other->groups()->first()->id])])->assertSessionHasErrors('groups');
});

it('edits only the products and keeps the customer address and discounts', function () {
    $order = ($this->createOrder)([($this->wholesale)(2)]);
    $order->update(['adjustments' => [['type' => 'coupon', 'amount' => '2.00', 'currency' => 'USD', 'reference' => 'SAVE2']]]);
    $before = $order->fresh();

    ($this->edit)($order, [($this->wholesale)(1)], [
        'contact_id' => 999999,
        'customer' => ['name' => 'New name', 'phone' => '+15555550199'],
        'shipping_address' => array_merge($this->address, ['line1' => '9 New Road']),
        'shipping_method_id' => 123,
    ])->assertSessionHasNoErrors();
    $order->refresh();
    expect($order->discount_amount)->toBe('2.0000')
        ->and($order->total)->toBe('16.0000')
        ->and($order->items()->sum('quantity'))->toEqual(3)
        ->and($order->customer_snapshot)->toEqual($before->customer_snapshot)
        ->and($order->contact_id)->toBe($before->contact_id)
        ->and($order->shipping_address)->toEqual($before->shipping_address)
        ->and($order->shipping_method_id)->toBe($before->shipping_method_id);
});
