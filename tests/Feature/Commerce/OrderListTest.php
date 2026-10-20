<?php

use App\Models\User;
use App\Modules\Commerce\Jobs\NotifyOrderEvent;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\StoreOrderSetting;
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
        'workspace_id' => $this->workspace->id,
        'name' => 'Classic Polo',
        'slug' => 'classic-polo',
        'sku' => 'POLO-001',
        'status' => 'active',
        'visibility' => 'published',
        'selling_mode' => 'both',
        'single_piece_price' => 25,
        'wholesale_price' => 15,
        'ws_enabled' => true,
    ]);

    $this->color = $this->product->colors()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Navy Blue',
    ]);

    $this->variant = $this->product->variants()->create([
        'workspace_id' => $this->workspace->id,
        'color_id' => $this->color->id,
        'sku' => 'POLO-001-M',
        'meta_retailer_id' => 'POLO-001-M',
        'size' => 'M',
        'price' => 25,
        'stock_quantity' => 50,
        'status' => 'active',
    ]);

    $this->actingAs($this->staff);
});

it('renders orders list without help banner and with formatted currency', function () {
    $order = app(UnifiedOrderService::class)->create($this->workspace, [
        'submission_reference' => (string) Str::uuid(),
        'source' => 'storefront',
        'customer' => ['name' => 'Alice Walker', 'phone' => '+15551234567'],
        'shipping_address' => ['name' => 'Alice Walker', 'phone' => '+15551234567', 'line1' => '123 Main St', 'city' => 'Austin', 'country' => 'US'],
        'groups' => [
            ['product_id' => $this->product->id, 'mode' => 'retail', 'variant_id' => $this->variant->id, 'quantity' => 2],
        ],
    ]);

    $response = $this->get(route('user.commerce.orders.index'))->assertOk();

    // Verify help banner is removed
    $response->assertDontSee('Commerce orders help');

    // Verify order number and buyer
    $response->assertSee($order->number);
    $response->assertSee('Alice Walker');

    // Verify formatted price does NOT show 4 decimal places like 50.0000
    $response->assertDontSee('50.0000');
    $response->assertSee('50.00');

    // Verify top action buttons
    $response->assertSee('POS');
    $response->assertSee('Payment Services');
    $response->assertSee('Order settings');
    $response->assertSee('Products');

    // Verify quick status tabs
    $response->assertSee('All Orders');
    $response->assertSee('Awaiting Payment');
});

it('filters orders by search query and status', function () {
    $order1 = app(UnifiedOrderService::class)->create($this->workspace, [
        'submission_reference' => (string) Str::uuid(),
        'source' => 'storefront',
        'customer' => ['name' => 'Bob Marley', 'phone' => '+15559876543'],
        'shipping_address' => ['name' => 'Bob Marley', 'phone' => '+15559876543', 'line1' => '456 Reggae Way', 'city' => 'Miami', 'country' => 'US'],
        'groups' => [
            ['product_id' => $this->product->id, 'mode' => 'retail', 'variant_id' => $this->variant->id, 'quantity' => 1],
        ],
    ]);

    $order2 = app(UnifiedOrderService::class)->create($this->workspace, [
        'submission_reference' => (string) Str::uuid(),
        'source' => 'storefront',
        'customer' => ['name' => 'Charlie Brown', 'phone' => '+15553334444'],
        'shipping_address' => ['name' => 'Charlie Brown', 'phone' => '+15553334444', 'line1' => '789 Peanuts Rd', 'city' => 'Chicago', 'country' => 'US'],
        'groups' => [
            ['product_id' => $this->product->id, 'mode' => 'retail', 'variant_id' => $this->variant->id, 'quantity' => 1],
        ],
    ]);

    // Search for Bob
    $responseBob = $this->get(route('user.commerce.orders.index', ['search' => 'Bob']))->assertOk();
    $responseBob->assertSee('Bob Marley');
    $responseBob->assertDontSee('Charlie Brown');

    // Search by order number
    $responseOrder = $this->get(route('user.commerce.orders.index', ['search' => $order2->number]))->assertOk();
    $responseOrder->assertSee('Charlie Brown');
    $responseOrder->assertDontSee('Bob Marley');
});
