<?php

use App\Models\User;
use App\Modules\Commerce\Database\Seeders\PosDemoSeeder;
use App\Modules\Commerce\Jobs\NotifyOrderEvent;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderPayment;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\OrderInventoryService;
use App\Modules\Commerce\Services\OrderPackingService;
use App\Modules\Commerce\Services\OrderWorkflowService;
use App\Modules\Commerce\Services\PosService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake([NotifyOrderEvent::class]);
});

function posContext(): array
{
    $user = User::factory()->create();
    $workspace = app(WorkspaceResolver::class)->current($user);
    $settings = StoreOrderSetting::forWorkspace($workspace->id);
    $product = Product::query()->create(['workspace_id' => $workspace->id, 'name' => 'POS shirt', 'slug' => 'pos-shirt-'.$workspace->id, 'sku' => 'POS-'.$workspace->id, 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'both', 'single_piece_price' => 10, 'wholesale_price' => 6, 'ws_enabled' => true, 'ws_main_moq' => 12, 'ws_color_moq' => 6, 'ws_min_sizes' => 3, 'ws_ratio_multiplier' => 1]);
    $color = $product->colors()->create(['workspace_id' => $workspace->id, 'name' => 'Red']);
    $product->update(['ws_size_ratios' => [$color->id => ['S' => 2, 'M' => 3, 'L' => 1]]]);
    $variants = collect(['S', 'M', 'L'])->map(fn ($size) => $product->variants()->create(['workspace_id' => $workspace->id, 'color_id' => $color->id, 'sku' => 'POS-'.$workspace->id.'-'.$size, 'meta_retailer_id' => 'POS-'.$workspace->id.'-'.$size, 'size' => $size, 'price' => 10, 'stock_quantity' => 100, 'status' => 'active']));
    $data = ['submission_reference' => (string) Str::uuid(), 'fulfillment_type' => 'pickup', 'walk_in' => false, 'handover' => false, 'customer' => ['name' => 'POS buyer', 'phone' => '+15555550123'], 'groups' => [['product_id' => $product->id, 'mode' => 'retail', 'variant_id' => $variants[0]->id, 'quantity' => 2]]];

    return compact('user', 'workspace', 'settings', 'product', 'color', 'variants', 'data');
}

function posPayment(string $amount = '5.00', string $currency = 'USD'): array
{
    return OrderPayment::factory()->make(['amount' => $amount, 'currency' => $currency])->only(['submission_reference', 'amount', 'currency', 'method']);
}

function posManager(User $user): void
{
    $role = Role::findOrCreate('POS manager', 'web');
    $role->givePermissionTo([Permission::findOrCreate('commerce.manage', 'web'), Permission::findOrCreate('commerce.view', 'web')]);
    $user->assignRole($role);
}

it('prices mixed pickup carts with tier prices and snapshots configured packs', function () {
    $c = posContext();
    $c['product']->tierPrices()->create(['workspace_id' => $c['workspace']->id, 'min_quantity' => 12, 'unit_price' => 5]);
    $c['data']['groups'][] = ['product_id' => $c['product']->id, 'mode' => 'wholesale', 'color_id' => $c['color']->id, 'box_count' => 2];
    $order = app(PosService::class)->checkout($c['workspace'], $c['data'], $c['user']);
    expect($order->source)->toBe('pos')->and($order->fulfillment_type)->toBe('pickup')->and($order->shipping_address)->toBe([])
        ->and($order->shipping_amount)->toBe('0.0000')->and($order->total)->toBe('80.0000')->and($order->items->sum('quantity'))->toBe(14)
        ->and($order->boxes)->toHaveCount(2)->and($order->status)->toBe('processing')->and($order->payment_state)->toBe('unpaid');
    expect($c['variants'][0]->fresh()->stock_quantity)->toBe(100);
    $c['product']->update(['ws_size_ratios' => []]);
    expect($order->groups->where('mode', 'wholesale')->first()->ratio)->toEqual(['S' => 2, 'M' => 3, 'L' => 1]);
});

it('records deposits and later payments independently from fulfillment', function () {
    $c = posContext();
    $c['data']['payment'] = posPayment();
    $c['data']['handover'] = true;
    $service = app(PosService::class);
    $order = $service->checkout($c['workspace'], $c['data'], $c['user']);
    expect($order->status)->toBe('completed')->and($order->payment_state)->toBe('partially_paid')->and($order->paidAmount())->toBe('5.00')->and($order->balanceDue())->toBe('15.00');
    expect($c['variants'][0]->fresh()->stock_quantity)->toBe(98);
    $service->recordPayment($order, posPayment('15.00'), $c['user']);
    expect($order->fresh()->payment_state)->toBe('paid')->and($order->fresh()->balanceDue())->toBe('0.00')->and($order->fresh()->status)->toBe('completed');
    $service->pickup($order);
    expect($c['variants'][0]->fresh()->stock_quantity)->toBe(98);
    expect($service->customerBalances($order->contact))->toEqual([['currency' => 'USD', 'amount' => '0.00']]);
});

it('allows named customers to collect goods without an initial payment', function () {
    $c = posContext();
    $c['data']['handover'] = true;
    $order = app(PosService::class)->checkout($c['workspace'], $c['data'], $c['user']);
    expect($order->payment_state)->toBe('unpaid')->and($order->balanceDue())->toBe('20.00')->and($order->status)->toBe('completed');
});

it('completes fully paid walk-in sales and records cash change separately', function () {
    $c = posContext();
    unset($c['data']['customer']);
    $c['data']['walk_in'] = true;
    $c['data']['handover'] = true;
    $c['data']['payment'] = array_merge(posPayment('20'), ['tendered_amount' => '50']);
    $order = app(PosService::class)->checkout($c['workspace'], $c['data'], $c['user']);
    expect($order->contact_id)->toBeNull()->and($order->status)->toBe('completed')->and($order->paidAmount())->toBe('20.00')
        ->and($order->payments[0]->change_amount)->toBe('30.0000')->and($order->payments[0]->staff_id)->toBe($c['user']->id);
});

it('rolls back walk-in credit sales and uncollected walk-in sales', function (bool $handover, string $amount) {
    $c = posContext();
    unset($c['data']['customer']);
    $c['data']['walk_in'] = true;
    $c['data']['handover'] = $handover;
    $c['data']['payment'] = posPayment($amount);
    expect(fn () => app(PosService::class)->checkout($c['workspace'], $c['data'], $c['user']))->toThrow(ValidationException::class);
    expect(Order::count())->toBe(0)->and(OrderPayment::count())->toBe(0)->and($c['variants'][0]->fresh()->stock_quantity)->toBe(100);
})->with([[true, '5'], [false, '20']]);

it('makes checkout and payment retries idempotent and rejects changed submissions', function () {
    $c = posContext();
    $c['data']['handover'] = true;
    $c['data']['payment'] = posPayment('5');
    $service = app(PosService::class);
    $order = $service->checkout($c['workspace'], $c['data'], $c['user']);
    expect($service->checkout($c['workspace'], $c['data'], $c['user'])->id)->toBe($order->id);
    $service->recordPayment($order, $c['data']['payment'], $c['user']);
    expect(Order::count())->toBe(1)->and(OrderPayment::count())->toBe(1)->and($c['variants'][0]->fresh()->stock_quantity)->toBe(98);
    $changed = array_replace($c['data']['payment'], ['amount' => '6']);
    try {
        $service->recordPayment($order, $changed, $c['user']);
        $this->fail('Expected payment conflict.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(409);
    }
    $c['data']['handover'] = false;
    try {
        $service->checkout($c['workspace'], $c['data'], $c['user']);
        $this->fail('Expected checkout conflict.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(409);
    }
});

it('rejects overpayments wrong currencies inactive methods and insufficient cash', function (array $overrides) {
    $c = posContext();
    $service = app(PosService::class);
    $order = $service->checkout($c['workspace'], $c['data'], $c['user']);
    expect(fn () => $service->recordPayment($order, array_replace(posPayment(), $overrides), $c['user']))->toThrow(ValidationException::class);
    expect(OrderPayment::count())->toBe(0)->and($order->fresh()->payment_state)->toBe('unpaid');
})->with([[['amount' => '21']], [['currency' => 'BDT']], [['method' => 'inactive']], [['tendered_amount' => '1']], [['amount' => '0.001']], [['amount' => '20.001']], [['tendered_amount' => '10.001']]]);

it('allows active configured manual methods and keeps customer currencies separate', function () {
    $c = posContext();
    $c['workspace']->update(['settings' => ['commerce' => ['payment_methods' => [['id' => 'bank', 'name' => 'Bank transfer', 'active' => true, 'recipient_details' => 'Local bank']]]]]);
    $service = app(PosService::class);
    $c['data']['payment'] = array_replace(posPayment(), ['method' => 'bank', 'reference' => 'BANK-1']);
    $first = $service->checkout($c['workspace'], $c['data'], $c['user']);
    $c['settings']->update(['currency' => 'BDT']);
    $c['data']['submission_reference'] = (string) Str::uuid();
    unset($c['data']['payment']);
    $second = $service->checkout($c['workspace'], $c['data'], $c['user']);
    expect($service->customerBalances($first->contact))->toEqual([['currency' => 'BDT', 'amount' => '20.00'], ['currency' => 'USD', 'amount' => '15.00']]);
    expect($second->contact_id)->toBe($first->contact_id);
});

it('blocks payment and handover until a delivery shipping quote exists', function () {
    $c = posContext();
    $c['data']['fulfillment_type'] = 'delivery';
    $c['data']['shipping_address'] = ['name' => 'Buyer', 'phone' => '+15555550123', 'line1' => 'Main Street', 'city' => 'Boston', 'country' => 'US'];
    $c['data']['payment'] = posPayment();
    $service = app(PosService::class);
    expect(fn () => $service->checkout($c['workspace'], $c['data'], $c['user']))->toThrow(ValidationException::class);
    expect(Order::count())->toBe(0);
    unset($c['data']['payment']);
    $order = $service->checkout($c['workspace'], $c['data'], $c['user']);
    expect($order->status)->toBe('requested')->and($order->total)->toBeNull();
    expect(fn () => $service->recordPayment($order, posPayment(), $c['user']))->toThrow(ValidationException::class);
    app(OrderWorkflowService::class)->quote($order, ['shipping_address' => $c['data']['shipping_address'], 'shipping_amount' => '5']);
    expect($order->fresh()->status)->toBe('processing')->and($order->fresh()->total)->toBe('25.0000');
    $service->recordPayment($order->fresh(), posPayment(), $c['user']);
    expect(fn () => app(OrderWorkflowService::class)->quote($order->fresh(), ['shipping_address' => $c['data']['shipping_address'], 'shipping_amount' => '6']))->toThrow(ValidationException::class);
    $box = app(OrderPackingService::class)->addRetailBox($order->fresh(), [$order->items[0]->id => 2]);
    app(OrderPackingService::class)->markPacked($order->fresh(), $box->id);
    $shipped = app(OrderWorkflowService::class)->transition($order->fresh(), 'shipped', ['tracking_number' => 'POS-TRACK']);
    expect($shipped->payment_state)->toBe('partially_paid')->and($c['variants'][0]->fresh()->stock_quantity)->toBe(98);
    app(OrderWorkflowService::class)->transition($shipped, 'shipped', ['tracking_number' => 'RETRY']);
    expect($shipped->shipment()->count())->toBe(1)->and($c['variants'][0]->fresh()->stock_quantity)->toBe(98);
});

it('prevents cancelling confirmed payments or handed-over sales and releases unpaid reservations', function () {
    $c = posContext();
    $service = app(PosService::class);
    $order = $service->checkout($c['workspace'], $c['data'], $c['user']);
    $service->recordPayment($order, posPayment(), $c['user']);
    expect(fn () => app(OrderWorkflowService::class)->transition($order->fresh(), 'cancelled'))->toThrow(ValidationException::class);
    expect(fn () => app(OrderWorkflowService::class)->transition($order->fresh(), 'paid'))->toThrow(ValidationException::class);
    $c['data']['submission_reference'] = (string) Str::uuid();
    $unpaid = $service->checkout($c['workspace'], $c['data'], $c['user']);
    app(OrderWorkflowService::class)->transition($unpaid, 'cancelled');
    expect($unpaid->reservations()->where('state', 'released')->sum('quantity'))->toBe('2');
    expect(fn () => $service->recordPayment($unpaid->fresh(), posPayment(), $c['user']))->toThrow(ValidationException::class);
    $service->pickup($order->fresh());
    expect(fn () => app(OrderWorkflowService::class)->transition($order->fresh(), 'cancelled'))->toThrow(ValidationException::class);
});

it('rechecks expired reservations and never oversells at pickup', function () {
    $c = posContext();
    $c['variants'][0]->update(['stock_quantity' => 2]);
    $service = app(PosService::class);
    $first = $service->checkout($c['workspace'], $c['data'], $c['user']);
    $this->travel(25)->hours();
    app(OrderInventoryService::class)->expire();
    $c['data']['submission_reference'] = (string) Str::uuid();
    $second = $service->checkout($c['workspace'], $c['data'], $c['user']);
    expect(fn () => $service->pickup($first))->toThrow(ValidationException::class);
    expect($c['variants'][0]->fresh()->stock_quantity)->toBe(2);
    $service->pickup($second);
    expect($c['variants'][0]->fresh()->stock_quantity)->toBe(0);
});

it('renders POS screens filters receipts and validates tampered prices', function () {
    $c = posContext();
    posManager($c['user']);
    $this->actingAs($c['user'])->withSession(['active_workspace_id' => $c['workspace']->id]);
    $this->get(route('user.commerce.pos.index'))->assertOk()->assertSee('Point of sale');
    $this->getJson(route('user.commerce.pos.products', ['search' => $c['variants'][0]->sku]))->assertOk()->assertJsonPath('data.0.id', $c['product']->id);
    $this->postJson(route('user.commerce.pos.preview'), $c['data'])->assertOk()->assertJsonPath('data.total', '20.00');
    $c['data']['groups'][0]['unit_price'] = '0.01';
    $this->postJson(route('user.commerce.pos.checkout'), $c['data'])->assertUnprocessable()->assertJsonValidationErrors('groups.0.unit_price');
    unset($c['data']['groups'][0]['unit_price']);
    $c['data']['payment'] = posPayment();
    $response = $this->postJson(route('user.commerce.pos.checkout'), $c['data'])->assertCreated();
    $order = Order::findOrFail($response->json('data.id'));
    $this->get($response->json('data.url'))->assertOk()->assertSee('POS payments and balance')->assertSee('15.00');
    $this->get($response->json('data.receipt_url'))->assertOk()->assertSee('Sales receipt')->assertSee('Payment history')->assertSee('USD 15.00');
    $this->get(route('user.commerce.orders.index', ['source' => 'pos', 'payment_state' => 'partially_paid']))->assertOk()->assertSee($order->number);
    $this->get(route('user.commerce.orders.index', ['payment_state' => 'paid']))->assertOk()->assertDontSee($order->number);
    $this->getJson(route('user.commerce.pos.customer-balance', $order->contact))->assertOk()->assertJsonPath('data.0.amount', '15.00');
});

it('enforces POS permissions and isolates other workspaces on every order action', function () {
    $c = posContext();
    $order = app(PosService::class)->checkout($c['workspace'], $c['data'], $c['user']);
    $outsider = User::factory()->create();
    $c['workspace']->members()->attach($outsider, ['status' => 'active']);
    $this->actingAs($outsider)->withSession(['active_workspace_id' => $c['workspace']->id]);
    $this->get(route('user.commerce.pos.index'))->assertForbidden();
    $this->postJson(route('user.commerce.pos.checkout'), $c['data'])->assertForbidden();
    $this->postJson(route('user.commerce.pos.payment', $order), posPayment())->assertForbidden();
    $this->post(route('user.commerce.pos.pickup', $order))->assertForbidden();
    $this->get(route('user.commerce.pos.receipt', $order))->assertForbidden();
    $otherUser = User::factory()->create();
    $otherWorkspace = app(WorkspaceResolver::class)->current($otherUser);
    posManager($otherUser);
    $this->actingAs($otherUser)->withSession(['active_workspace_id' => $otherWorkspace->id]);
    $this->postJson(route('user.commerce.pos.payment', $order), posPayment())->assertNotFound();
    $this->post(route('user.commerce.pos.pickup', $order))->assertNotFound();
    $this->get(route('user.commerce.pos.receipt', $order))->assertNotFound();
    $this->getJson(route('user.commerce.pos.customer-balance', $order->contact))->assertNotFound();
    $this->postJson(route('user.commerce.pos.checkout'), $c['data'])->assertNotFound();
});

it('requires delivery fields but permits pickup without an address and verifies wholesale minimums', function () {
    $c = posContext();
    posManager($c['user']);
    $this->actingAs($c['user']);
    $delivery = array_replace($c['data'], ['fulfillment_type' => 'delivery']);
    $this->postJson(route('user.commerce.pos.checkout'), $delivery)->assertUnprocessable()->assertJsonValidationErrors('shipping_address.line1');
    $c['data']['groups'] = [['product_id' => $c['product']->id, 'mode' => 'wholesale', 'color_id' => $c['color']->id, 'box_count' => 1]];
    $this->postJson(route('user.commerce.pos.preview'), $c['data'])->assertUnprocessable()->assertJsonValidationErrors('groups');
    $c['data']['groups'][0]['box_count'] = 2;
    $c['data']['groups'][0]['ratio'] = ['S' => 1];
    $this->postJson(route('user.commerce.pos.preview'), $c['data'])->assertUnprocessable()->assertJsonValidationErrors('groups.0.ratio');
});

it('seeds the POS demo once using the real checkout and payment workflows', function () {
    $c = posContext();
    config(['commerce-demo.workspace_id' => $c['workspace']->id]);
    $this->seed(PosDemoSeeder::class);
    $this->seed(PosDemoSeeder::class);
    $order = Order::query()->where('source', 'pos')->firstOrFail();
    expect(Order::count())->toBe(1)->and($order->payments)->toHaveCount(1)->and($order->balanceDue())->toBe('67.00');
    $payment = OrderPayment::factory()->forOrder($order, $c['user'])->make();
    expect($payment->workspace_id)->toBe($c['workspace']->id)->and($payment->order_id)->toBe($order->id);
});

it('uses the order currency precision for payments and remaining balances', function (string $currency, string $amount, string $balance) {
    $c = posContext();
    $c['settings']->update(['currency' => $currency]);
    $c['data']['payment'] = posPayment($amount, $currency);
    $order = app(PosService::class)->checkout($c['workspace'], $c['data'], $c['user']);
    expect($order->balanceDue())->toBe($balance);
})->with([['JPY', '5', '15'], ['KWD', '5.123', '14.877']]);
