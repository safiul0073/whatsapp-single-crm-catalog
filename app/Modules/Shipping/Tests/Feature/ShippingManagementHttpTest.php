<?php

use App\Models\User;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Shipping\Models\CarrierAccount;
use App\Modules\Shipping\Models\RemoteArea;
use App\Modules\Shipping\Models\ShippingAgent;
use App\Modules\Shipping\Models\ShippingAuditEntry;
use App\Modules\Shipping\Models\ShippingQuote;
use App\Modules\Shipping\Services\QuoteApprovalService;
use App\Modules\Shipping\Services\QuoteService;
use App\Modules\Shipping\Services\ShipmentService;
use App\Modules\Workspaces\Enums\WorkspaceMemberStatus;
use App\Modules\Workspaces\Models\WorkspaceRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceResolver::class)->current($this->owner);
    $this->order = Order::query()->create([
        'workspace_id' => $this->workspace->id,
        'number' => 'ORD-00042',
        'provider_message_id' => fake()->uuid(),
        'status' => 'paid',
        'currency' => 'USD',
        'subtotal' => '500.0000',
        'total' => '500.0000',
        'shipping_address' => ['name' => 'Jane Buyer', 'phone' => '+12025550147', 'line1' => '1 Main St', 'city' => 'Austin', 'state' => 'TX', 'postal_code' => '73301', 'country' => 'US'],
    ]);
    $this->shipment = app(ShipmentService::class)->create($this->order);
});

function shippingTeamMember(object $test, string $roleName): User
{
    $member = User::factory()->create();
    $role = WorkspaceRole::query()->where('workspace_id', $test->workspace->id)->where('name', $roleName)->firstOrFail();
    $test->workspace->members()->attach($member->id, ['workspace_role_id' => $role->id, 'status' => WorkspaceMemberStatus::Active->value]);

    return $member;
}

function approvedShippingQuote(object $test, string $amount = '100'): ShippingQuote
{
    $quote = app(QuoteService::class)->store($test->shipment, ['source' => 'manual_direct', 'carrier' => 'ups', 'base_rate' => $amount, 'currency' => 'USD', 'status' => 'verified']);
    app(QuoteApprovalService::class)->approve($test->shipment, $quote, $test->owner);

    return $quote;
}

it('renders every shipping management screen for the owner', function () {
    approvedShippingQuote($this);
    $agent = ShippingAgent::query()->create(['workspace_id' => $this->workspace->id, 'name' => 'Agent A']);
    $account = CarrierAccount::query()->create(['workspace_id' => $this->workspace->id, 'name' => 'UPS main', 'carrier' => 'ups']);

    $this->actingAs($this->owner);
    foreach ([
        route('user.shipping.overview'),
        route('user.shipping.shipments.index'),
        route('user.shipping.shipments.create'),
        route('user.shipping.shipments.show', $this->shipment),
        route('user.shipping.calculator'),
        route('user.shipping.comparison'),
        route('user.shipping.remote-areas.index', ['country' => 'US', 'postal_code' => '73301']),
        route('user.shipping.agents.index'),
        route('user.shipping.agents.create'),
        route('user.shipping.agents.show', $agent),
        route('user.shipping.agents.edit', $agent),
        route('user.shipping.carriers.index'),
        route('user.shipping.carriers.create'),
        route('user.shipping.carriers.edit', $account),
        route('user.shipping.billing.index'),
        route('user.shipping.reports.index', ['period' => 'weekly']),
        route('user.shipping.product-costs.index'),
        route('user.shipping.audit.index'),
        route('user.commerce.orders.show', $this->order),
    ] as $url) {
        $this->get($url)->assertOk();
    }
});

it('searches shipments by order, customer, courier, agent and tracking number', function () {
    $agent = ShippingAgent::query()->create(['workspace_id' => $this->workspace->id, 'name' => 'Dhaka Cargo', 'carrier' => 'fedex']);
    $this->shipment->update(['shipping_agent_id' => $agent->id, 'carrier' => 'fedex', 'tracking_number' => 'FX-998877']);
    $other = app(ShipmentService::class)->create(Order::query()->create(['workspace_id' => $this->workspace->id, 'number' => 'ORD-00077', 'provider_message_id' => fake()->uuid(), 'status' => 'paid', 'shipping_address' => ['name' => 'Someone Else']]));

    $this->actingAs($this->owner);
    foreach (['ORD-00042', 'Jane', 'fedex', 'Dhaka', 'FX-9988'] as $term) {
        $this->get(route('user.shipping.shipments.index', ['search' => $term]))
            ->assertOk()->assertSee($this->shipment->reference)->assertDontSee($other->reference);
    }
});

it('hides shipments of another workspace', function () {
    $stranger = User::factory()->create();
    app(WorkspaceResolver::class)->current($stranger);

    $this->actingAs($stranger);
    $this->get(route('user.shipping.shipments.show', $this->shipment))->assertNotFound();
    $this->put(route('user.shipping.shipments.cartons', $this->shipment), ['cartons' => [['actual_weight_kg' => 1, 'length_cm' => 1, 'width_cm' => 1, 'height_cm' => 1]]])->assertNotFound();
    $this->post(route('user.shipping.shipments.store'), ['order_id' => $this->order->id])->assertNotFound();
    $this->get(route('user.shipping.shipments.index'))->assertOk()->assertDontSee($this->shipment->reference);
});

it('lets the sales team follow shipments without any cost details', function () {
    approvedShippingQuote($this, '123.45');
    $agent = ShippingAgent::query()->create(['workspace_id' => $this->workspace->id, 'name' => 'Secret Agent Co', 'carrier' => 'ups']);
    $this->shipment->update(['shipping_agent_id' => $agent->id, 'tracking_number' => '1Z-SALES', 'final_cost' => '150.9900']);

    $this->actingAs(shippingTeamMember($this, 'Sales Team'));
    foreach ([route('user.shipping.shipments.show', $this->shipment), route('user.shipping.shipments.index'), route('user.shipping.overview'), route('user.commerce.orders.show', $this->order)] as $url) {
        $this->get($url)->assertOk()->assertSee('1Z-SALES')->assertDontSee('123.45')->assertDontSee('150.99')->assertDontSee('Secret Agent Co')->assertDontSee('Rate comparison');
    }

    $this->get(route('user.shipping.remote-areas.index', ['country' => 'US', 'postal_code' => '73301']))->assertOk()->assertDontSee('Secret Agent Co');
    $this->get(route('user.shipping.comparison'))->assertForbidden();
    $this->get(route('user.shipping.billing.index'))->assertForbidden();
    $this->get(route('user.shipping.reports.index'))->assertForbidden();
    $this->get(route('user.shipping.agents.index'))->assertForbidden();
    $this->get(route('user.shipping.carriers.index'))->assertForbidden();
    $this->get(route('user.shipping.audit.index'))->assertForbidden();
    $this->put(route('user.shipping.shipments.booking', $this->shipment), ['tracking_number' => 'X'])->assertForbidden();
});

it('lets a shipping manager quote but keeps final approval for approvers', function () {
    $manager = shippingTeamMember($this, 'Shipping Manager');
    $this->actingAs($manager);

    $this->post(route('user.shipping.quotes.store', $this->shipment), [
        'provider' => 'carrier:aramex', 'base_rate' => '80', 'currency' => 'USD', 'remote_status' => 'unknown', 'charges' => ['fuel' => '8', 'remote_area' => ''],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $quote = ShippingQuote::query()->sole();
    expect($quote->status)->toBe('unverified')->and($quote->total)->toBe('88.0000')->and($quote->source)->toBe('manual_direct')->and($quote->valid_until)->not->toBeNull();

    $this->post(route('user.shipping.quotes.approve', [$this->shipment, $quote]))->assertForbidden();

    $this->actingAs($this->owner);
    $this->post(route('user.shipping.quotes.approve', [$this->shipment, $quote]))->assertSessionHasErrors('quote');
    $this->post(route('user.shipping.quotes.approve', [$this->shipment, $quote]), ['override' => 1])->assertSessionHasNoErrors();
    expect($this->shipment->refresh()->approved_cost)->toBe('88.0000')->and($this->shipment->carrier)->toBe('aramex');
});

it('saves the address with a default +1 for USA numbers and validates cartons', function () {
    $this->actingAs($this->owner);
    $address = ['recipient_name' => 'Jane Buyer', 'phone' => '(202) 555-0147', 'country_code' => 'us', 'city' => 'Austin', 'postal_code' => '73301', 'address_line1' => '1 Main St', 'address_type' => 'residential', 'total_sets' => 4, 'total_pieces' => 24];

    $this->put(route('user.shipping.shipments.update', $this->shipment), $address)->assertSessionHasNoErrors();
    expect($this->shipment->refresh()->phone)->toBe('+12025550147')->and($this->shipment->address_type)->toBe('residential')->and($this->shipment->address_validated_at)->not->toBeNull();

    $this->put(route('user.shipping.shipments.update', $this->shipment), ['phone' => '12345', 'country_code' => 'BD'] + $address)->assertSessionHasErrors('phone');

    $this->put(route('user.shipping.shipments.cartons', $this->shipment), ['cartons' => [
        ['quantity' => 2, 'actual_weight_kg' => '10', 'length_cm' => '50', 'width_cm' => '40', 'height_cm' => '30', 'packaging_type' => 'carton'],
        ['quantity' => '', 'actual_weight_kg' => '', 'length_cm' => '', 'width_cm' => '', 'height_cm' => '', 'packaging_type' => 'carton'],
    ]])->assertSessionHasNoErrors();
    expect($this->shipment->cartons()->count())->toBe(1);

    $this->put(route('user.shipping.shipments.cartons', $this->shipment), ['cartons' => [['actual_weight_kg' => '10', 'length_cm' => '50']]])->assertSessionHasErrors();
    $this->get(route('user.shipping.shipments.show', $this->shipment))->assertOk()->assertSee('20 kg')->assertSee('24 kg');
});

it('records a bill, flags the overrun and lets only an approver clear it', function () {
    approvedShippingQuote($this, '100');
    $accounts = shippingTeamMember($this, 'Accounts');

    $this->actingAs($accounts);
    $this->put(route('user.shipping.billing.update', $this->shipment), ['billed_amount' => '140', 'billed_currency' => 'USD', 'payment_status' => 'unpaid', 'charges' => ['remote_area' => '40', 'fuel' => '']])
        ->assertSessionHasNoErrors()->assertSessionHas('error');
    expect($this->shipment->refresh()->needs_review)->toBeTrue()->and($this->shipment->charges()->count())->toBe(1);

    $this->put(route('user.shipping.billing.review', $this->shipment))->assertForbidden();
    $this->get(route('user.shipping.billing.index', ['filter' => 'review']))->assertOk()->assertSee($this->shipment->reference)->assertSee('40.00');

    $this->actingAs($this->owner);
    $this->put(route('user.shipping.billing.review', $this->shipment), ['note' => 'ok'])->assertSessionHasNoErrors();
    expect($this->shipment->refresh()->needs_review)->toBeFalse()->and($this->shipment->reviewed_by)->toBe($this->owner->id);
});

it('stores courier credentials encrypted and never prints them', function () {
    $this->actingAs($this->owner);
    $this->post(route('user.shipping.carriers.store'), [
        'name' => 'FedEx BD', 'carrier' => 'fedex', 'account_number' => '740561073', 'environment' => 'sandbox', 'dim_divisor' => 5000, 'weight_rounding_step' => '0.5', 'is_active' => 1,
        'credentials' => ['client_id' => 'l7-key', 'client_secret' => 'super-secret-value', 'evil' => 'x'],
    ])->assertRedirect(route('user.shipping.carriers.index'));

    $account = CarrierAccount::query()->sole();
    expect($account->credentials)->toBe(['client_id' => 'l7-key', 'client_secret' => 'super-secret-value'])
        ->and(DB::table('shipping_carrier_accounts')->value('credentials'))->not->toContain('super-secret-value')
        ->and($account->toArray())->not->toHaveKey('credentials');

    $this->get(route('user.shipping.carriers.edit', $account))->assertOk()->assertDontSee('super-secret-value')->assertDontSee('l7-key');
    $this->get(route('user.shipping.carriers.index'))->assertDontSee('super-secret-value');

    $this->put(route('user.shipping.carriers.update', $account), ['name' => 'FedEx BD', 'carrier' => 'fedex', 'environment' => 'production', 'dim_divisor' => 5000, 'weight_rounding_step' => '0.5', 'credentials' => ['client_id' => '', 'client_secret' => 'rotated']])->assertSessionHasNoErrors();
    expect($account->refresh()->credentials)->toBe(['client_id' => 'l7-key', 'client_secret' => 'rotated'])->and($account->environment)->toBe('production');
});

it('imports an uploaded rate sheet, requires review, then quotes from it', function () {
    Storage::fake('local');
    $this->actingAs($this->owner);
    $this->post(route('user.shipping.agents.store'), ['name' => 'Dhaka Cargo', 'carrier' => 'ups', 'currency' => 'USD', 'exchange_rate' => 1, 'fuel_surcharge_percent' => 0, 'other_fee' => 0, 'dim_divisor' => 5000, 'weight_rounding_step' => '0.5', 'payment_status' => 'clear', 'is_active' => 1])->assertSessionHasNoErrors();
    $agent = ShippingAgent::query()->sole();

    $file = UploadedFile::fake()->createWithContent('rates.csv', "country,min weight,max weight,price per kg\nUS,0,100,6\n");
    $this->post(route('user.shipping.charts.store', $agent), ['name' => 'Nov', 'currency' => 'USD', 'file' => $file])->assertSessionHasNoErrors();
    $chart = $agent->charts()->sole();
    expect($chart->status)->toBe('pending_review')->and($chart->rows()->count())->toBe(1);

    app(ShipmentService::class)->syncCartons($this->shipment, [['actual_weight_kg' => 10, 'length_cm' => 10, 'width_cm' => 10, 'height_cm' => 10]]);
    $this->post(route('user.shipping.quotes.request', $this->shipment))->assertSessionHas('error');

    $this->put(route('user.shipping.charts.status', $chart), ['status' => 'active'])->assertSessionHasNoErrors();
    $this->post(route('user.shipping.quotes.request', $this->shipment))->assertSessionHas('success');
    expect(ShippingQuote::query()->sole()->total)->toBe('60.0000');

    $pdf = UploadedFile::fake()->create('rates.pdf', 20, 'application/pdf');
    $this->post(route('user.shipping.charts.store', $agent), ['name' => 'PDF chart', 'currency' => 'USD', 'file' => $pdf])->assertSessionHasNoErrors();
    $pdfChart = $agent->charts()->where('source_type', 'pdf')->sole();
    $this->put(route('user.shipping.charts.status', $pdfChart), ['status' => 'active'])->assertSessionHasErrors('status');
});

it('keeps unconfirmed remote-area entries out of the checker', function () {
    $this->actingAs($this->owner);
    $entry = ['provider' => 'carrier:ups', 'country_code' => 'US', 'postal_from' => '733', 'status' => 'extended_remote', 'fee' => '45', 'currency' => 'USD'];

    $this->post(route('user.shipping.remote-areas.store'), $entry)->assertSessionHasNoErrors();
    $this->get(route('user.shipping.remote-areas.index', ['country' => 'US', 'postal_code' => '73301']))->assertOk()->assertSee('Unconfirmed');
    $this->get(route('user.shipping.shipments.show', $this->shipment))->assertDontSee('A remote-area charge is confirmed');

    RemoteArea::query()->delete();
    $this->post(route('user.shipping.remote-areas.store'), $entry + ['confirmed' => 1])->assertSessionHasNoErrors();
    $this->get(route('user.shipping.remote-areas.index', ['country' => 'US', 'postal_code' => '73301']))->assertOk()->assertSee('Extended Remote')->assertSee('USD 45.00');
    $this->get(route('user.shipping.shipments.show', $this->shipment))->assertSee('A remote-area charge is confirmed');
});

it('reports order profit from cost prices and actual shipping, and audits cost changes', function () {
    $product = Product::query()->create(['workspace_id' => $this->workspace->id, 'name' => 'Hoodie Set', 'slug' => 'hoodie-set']);
    $variant = ProductVariant::query()->create(['workspace_id' => $this->workspace->id, 'product_id' => $product->id, 'sku' => 'HS-M', 'meta_retailer_id' => 'HS-M', 'price' => 50, 'stock_quantity' => 10, 'status' => 'active']);
    $this->order->items()->create(['workspace_id' => $this->workspace->id, 'variant_id' => $variant->id, 'retailer_id' => 'HS-M', 'sku' => 'HS-M', 'product_name' => 'Hoodie Set', 'quantity' => 10, 'unit_price' => 50, 'line_total' => 500]);
    $this->shipment->update(['total_sets' => 10]);
    approvedShippingQuote($this, '100');
    app(ShipmentService::class)->recordBilling($this->shipment->refresh(), ['billed_amount' => '120', 'billed_currency' => 'USD']);

    $this->actingAs($this->owner);
    $this->get(route('user.shipping.reports.index'))->assertOk()->assertSee('Incomplete')->assertSee('Missing');

    $this->put(route('user.shipping.product-costs.update'), ['costs' => [$variant->id => '20']])->assertSessionHasNoErrors();
    expect($variant->refresh()->cost_price)->toBe('20.0000')
        ->and(ShippingAuditEntry::query()->where('action', 'product_cost.updated')->exists())->toBeTrue()
        ->and($variant->toArray())->not->toHaveKey('cost_price');

    $this->put(route('user.shipping.other-costs.update', $this->order), ['other_costs' => '30'])->assertSessionHasNoErrors();

    $response = $this->get(route('user.shipping.reports.index', ['period' => 'monthly']))->assertOk();
    $response->assertSee('150.00')->assertSee('12.00')->assertSee('24%')->assertSee('20.00');
});

it('groups shipment products by product and colour with size chips', function () {
    foreach ([['Red', 'S', 1], ['Red', 'M', 2], ['Blue', 'L', 3]] as [$color, $size, $qty]) {
        $this->order->items()->create(['workspace_id' => $this->workspace->id, 'retailer_id' => "T-{$color}-{$size}", 'sku' => "T-{$color}-{$size}", 'product_name' => 'Classic Tee', 'attributes' => ['color' => $color, 'size' => $size, 'material' => 'Cotton'], 'quantity' => $qty, 'unit_price' => 10, 'line_total' => 10 * $qty]);
    }

    $html = $this->actingAs($this->owner)->get(route('user.shipping.shipments.show', $this->shipment))->assertOk()->getContent();

    expect(substr_count($html, 'data-shipment-product>'))->toBe(1)
        ->and(substr_count($html, 'class="size-chip"'))->toBe(3)
        ->and(substr_count($html, 'Cotton'))->toBe(2)
        ->and($html)->toContain('6 pcs')->toContain('3 pcs')->toContain('×2');
});
