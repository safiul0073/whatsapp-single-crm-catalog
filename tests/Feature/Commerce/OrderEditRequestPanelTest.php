<?php

use App\Models\User;
use App\Modules\Commerce\Jobs\NotifyOrderEvent;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\UnifiedOrderService;
use App\Modules\Commerce\Storefront\Models\OrderEditRequest;
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
    $product = Product::query()->create([
        'workspace_id' => $this->workspace->id, 'name' => 'Edit shirt', 'slug' => 'edit-shirt', 'sku' => 'edit-shirt',
        'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'retail', 'single_piece_price' => 10,
    ]);
    $variant = $product->variants()->create(['workspace_id' => $this->workspace->id, 'sku' => 'edit-m', 'meta_retailer_id' => 'edit-m', 'size' => 'M', 'price' => 10, 'stock_quantity' => 100, 'status' => 'active']);
    $this->createOrder = fn (): Order => app(UnifiedOrderService::class)->create($this->workspace, [
        'submission_reference' => (string) Str::uuid(), 'source' => 'manual',
        'customer' => ['name' => 'Test buyer', 'phone' => '+15555550100'],
        'shipping_address' => ['name' => 'Test buyer', 'phone' => '+15555550100', 'line1' => '1 Main Street', 'city' => 'Boston', 'country' => 'US'],
        'groups' => [['product_id' => $product->id, 'mode' => 'retail', 'variant_id' => $variant->id, 'quantity' => 2]],
    ]);
    $this->order = ($this->createOrder)();
    $this->order->update(['payment_state' => 'submitted']);
    $this->editRequest = OrderEditRequest::query()->create([
        'workspace_id' => $this->workspace->id, 'order_id' => $this->order->id, 'reference' => 'OE-TEST0001',
        'submission_reference' => (string) Str::uuid(), 'status' => 'pending', 'customer_note' => 'Please add a size L',
    ]);
    $this->decide = fn (string $decision, ?Order $order = null) => $this->put(route('user.commerce.orders.edit-requests.decide', [$order ?? $this->order, $this->editRequest]), ['decision' => $decision, 'owner_note' => 'Sure']);
    $this->actingAs($this->staff);
});

it('shows a pending edit request on the order page and the orders list', function () {
    $this->get(route('user.commerce.orders.show', $this->order))->assertOk()
        ->assertSee('Customer edit request')->assertSee('Please add a size L')->assertSee('Approve edit')->assertSee('Decline');
    $this->get(route('user.commerce.orders.index'))->assertOk()->assertSee('Edit requested');
});

it('approves, then withdraws, an edit request', function () {
    ($this->decide)('approve')->assertRedirect()->assertSessionHas('success');
    expect($this->editRequest->fresh())->status->toBe('approved')->owner_note->toBe('Sure')->decided_by->toBe($this->staff->id);
    $this->get(route('user.commerce.orders.show', $this->order))->assertOk()->assertSee('Waiting for the customer to edit')->assertSee('Withdraw permission');
    ($this->decide)('approve')->assertStatus(422);

    ($this->decide)('revoke')->assertRedirect();
    expect($this->editRequest->fresh()->status)->toBe('revoked');
});

it('declines a request and shows what to collect after a customer edit', function () {
    ($this->decide)('reject')->assertRedirect();
    expect($this->editRequest->fresh()->status)->toBe('rejected');

    $this->editRequest->update(['status' => 'used', 'used_at' => now(), 'previous_total' => '20', 'new_total' => '30', 'difference' => '10']);
    $this->get(route('user.commerce.orders.show', $this->order))->assertOk()->assertSee('Collect from customer')->assertSee('10.00');
});

it('rejects a request that belongs to another order', function () {
    $other = ($this->createOrder)();

    ($this->decide)('approve', $other)->assertNotFound();
    expect($this->editRequest->fresh()->status)->toBe('pending');
});
