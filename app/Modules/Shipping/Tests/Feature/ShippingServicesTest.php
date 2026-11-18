<?php

use App\Models\User;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderShipment;
use App\Modules\Commerce\Services\UnifiedOrderService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Shipping\Models\AgentRateChart;
use App\Modules\Shipping\Models\RemoteArea;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Models\ShippingAgent;
use App\Modules\Shipping\Models\ShippingAuditEntry;
use App\Modules\Shipping\Services\QuoteApprovalService;
use App\Modules\Shipping\Services\QuoteService;
use App\Modules\Shipping\Services\RateChartImportService;
use App\Modules\Shipping\Services\RateComparisonService;
use App\Modules\Shipping\Services\RemoteAreaService;
use App\Modules\Shipping\Services\ShipmentService;
use App\Modules\Shipping\Services\ShipmentWeightCalculator;
use App\Modules\SystemNotifications\Models\SystemNotification;
use App\Modules\Workspaces\Models\WorkspaceRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceResolver::class)->current($this->owner);
    $this->actingAs($this->owner);
});

function shippingOrder(int $workspaceId, array $attributes = []): Order
{
    return Order::query()->create(array_replace([
        'workspace_id' => $workspaceId,
        'number' => 'ORD-'.fake()->unique()->numerify('######'),
        'provider_message_id' => fake()->uuid(),
        'status' => 'paid',
        'currency' => 'USD',
        'subtotal' => '500.0000',
        'total' => '500.0000',
        'shipping_address' => ['name' => 'Jane Buyer', 'phone' => '+12025550147', 'line1' => '1 Main St', 'city' => 'Anchorage', 'state' => 'AK', 'postal_code' => '99501', 'country' => 'US'],
    ], $attributes));
}

function shippingAgentWithChart(int $workspaceId, string $name, string $price, array $agent = [], array $chart = []): ShippingAgent
{
    $agent = ShippingAgent::query()->create(array_replace(['workspace_id' => $workspaceId, 'name' => $name, 'carrier' => 'ups', 'currency' => 'USD'], $agent));
    $rateChart = AgentRateChart::query()->create(array_replace(['workspace_id' => $workspaceId, 'shipping_agent_id' => $agent->id, 'name' => $name.' chart', 'currency' => $agent->currency, 'status' => 'active'], $chart));
    $rateChart->rows()->create(['workspace_id' => $workspaceId, 'country_code' => 'US', 'min_weight_kg' => 0, 'max_weight_kg' => 100, 'price' => 0, 'price_per_kg' => $price]);

    return $agent;
}

it('rates every carton separately using dimensional weight and rounding', function () {
    $result = app(ShipmentWeightCalculator::class)->calculate([
        ['quantity' => 2, 'actual_weight_kg' => 10, 'length_cm' => 60, 'width_cm' => 50, 'height_cm' => 40],
        ['quantity' => 1, 'actual_weight_kg' => 12.2, 'length_cm' => 30, 'width_cm' => 20, 'height_cm' => 10],
    ], 5000, '0.5');

    expect($result['packages'])->toBe(3)
        ->and($result['cartons'][0]['dimensional_weight_kg'])->toBe(24.0)
        ->and($result['cartons'][0]['billable_weight_kg'])->toBe(24.0)
        ->and($result['cartons'][1]['dimensional_weight_kg'])->toBe(1.2)
        ->and($result['cartons'][1]['billable_weight_kg'])->toBe(12.5)
        ->and($result['actual_weight_kg'])->toBe(32.2)
        ->and($result['billable_weight_kg'])->toBe(60.5)
        ->and($result['flags'])->toContain('dimensional_weight', 'multiple_packages');
});

it('flags overweight and oversized cartons and honours a different divisor', function () {
    $result = app(ShipmentWeightCalculator::class)->calculate([
        ['quantity' => 1, 'actual_weight_kg' => 75, 'length_cm' => 280, 'width_cm' => 40, 'height_cm' => 40],
    ], 6000, '1');

    expect($result['flags'])->toContain('overweight', 'oversize', 'additional_handling')
        ->and($result['cartons'][0]['dimensional_weight_kg'])->toBe(74.667)
        ->and($result['billable_weight_kg'])->toBe(75.0);
});

it('never treats a postal code as remote without a confirmed entry for that provider', function () {
    $agent = ShippingAgent::query()->create(['workspace_id' => $this->workspace->id, 'name' => 'Agent A', 'carrier' => 'ups']);
    $service = app(RemoteAreaService::class);

    expect($service->statusFor($this->workspace->id, 'US', '99501', 'Anchorage', 'ups')['status'])->toBe('unknown');

    RemoteArea::query()->create(['workspace_id' => $this->workspace->id, 'carrier' => 'ups', 'country_code' => 'US', 'postal_from' => '99500', 'postal_to' => '99599', 'status' => 'remote', 'fee' => 35]);
    expect($service->statusFor($this->workspace->id, 'US', '99501', 'Anchorage', 'ups')['status'])->toBe('unknown');

    RemoteArea::query()->update(['verified_at' => now()]);
    expect($service->statusFor($this->workspace->id, 'US', '99501-1234', 'Anchorage', 'ups'))->toMatchArray(['status' => 'remote', 'fee' => '35.0000'])
        ->and($service->statusFor($this->workspace->id, 'US', '99501', 'Anchorage', 'fedex')['status'])->toBe('unknown')
        ->and($service->statusFor($this->workspace->id, 'US', '99501', 'Anchorage', 'ups', $agent->id)['status'])->toBe('unknown')
        ->and($service->statusFor($this->workspace->id, 'US', '10001', 'New York', 'ups')['status'])->toBe('unknown');
});

it('compares agents, highlights the lowest and adds a confirmed remote surcharge', function () {
    $cheap = shippingAgentWithChart($this->workspace->id, 'Cheap Agent', '5');
    $dear = shippingAgentWithChart($this->workspace->id, 'Dear Agent', '8', ['fuel_surcharge_percent' => 10]);
    RemoteArea::query()->create(['workspace_id' => $this->workspace->id, 'shipping_agent_id' => $cheap->id, 'country_code' => 'US', 'postal_from' => '995', 'status' => 'remote', 'fee' => 30, 'verified_at' => now()]);

    $shipment = app(ShipmentService::class)->create(shippingOrder($this->workspace->id));
    app(ShipmentService::class)->syncCartons($shipment, [['quantity' => 1, 'actual_weight_kg' => 10, 'length_cm' => 40, 'width_cm' => 30, 'height_cm' => 20]]);
    $quotes = app(QuoteService::class)->requestRates($shipment, $this->owner);

    expect($quotes)->toHaveCount(2);
    $rows = app(RateComparisonService::class)->forShipment($shipment);
    expect($rows[0]['quote']->shipping_agent_id)->toBe($cheap->id)
        ->and($rows[0]['quote']->total)->toBe('80.0000')
        ->and($rows[0]['quote']->remote_status)->toBe('remote')
        ->and($rows[0]['charges_by_reason'])->toMatchArray(['remote_area' => '30.00'])
        ->and($rows[0]['is_lowest'])->toBeTrue()
        ->and($rows[1]['quote']->total)->toBe('88.0000')
        ->and($rows[1]['quote']->remote_status)->toBe('unknown')
        ->and($rows[1]['is_lowest'])->toBeFalse()
        ->and($shipment->refresh()->estimated_cost)->toBe('80.0000')
        ->and($shipment->status)->toBe('quoted');
});

it('ignores expired and unreviewed rate charts', function () {
    shippingAgentWithChart($this->workspace->id, 'Expired', '5', [], ['expires_on' => now()->subDay()->toDateString()]);
    shippingAgentWithChart($this->workspace->id, 'Pending', '5', [], ['status' => 'pending_review']);

    $shipment = app(ShipmentService::class)->create(shippingOrder($this->workspace->id));
    app(ShipmentService::class)->syncCartons($shipment, [['actual_weight_kg' => 5, 'length_cm' => 20, 'width_cm' => 20, 'height_cm' => 20]]);

    expect(app(QuoteService::class)->requestRates($shipment))->toBeEmpty();
});

it('refuses to approve an unverified or expired quotation unless the owner overrides', function () {
    $shipment = app(ShipmentService::class)->create(shippingOrder($this->workspace->id));
    $quote = app(QuoteService::class)->store($shipment, ['source' => 'manual_whatsapp', 'carrier' => 'fedex', 'base_rate' => '120', 'currency' => 'USD'], $this->owner);
    $approvals = app(QuoteApprovalService::class);
    $staff = User::factory()->create();

    expect(fn () => $approvals->approve($shipment, $quote, $this->owner))->toThrow(ValidationException::class);
    expect(fn () => $approvals->approve($shipment, $quote, $staff, true))->toThrow(ValidationException::class);

    app(QuoteService::class)->setStatus($quote, 'verified', $this->owner);
    $quote->update(['valid_until' => now()->subHour()]);
    expect(fn () => $approvals->approve($shipment, $quote->refresh(), $this->owner))->toThrow(ValidationException::class);

    $approved = $approvals->approve($shipment, $quote, $this->owner, true);
    expect($approved->approved_cost)->toBe('120.0000')
        ->and($approved->status)->toBe('approved')
        ->and($approved->carrier)->toBe('fedex')
        ->and(ShippingAuditEntry::query()->where('action', 'quote.approved_override')->exists())->toBeTrue();
});

it('converts foreign-currency quotations into the store currency', function () {
    $shipment = app(ShipmentService::class)->create(shippingOrder($this->workspace->id));
    $quote = app(QuoteService::class)->store($shipment, [
        'source' => 'manual_whatsapp', 'base_rate' => '10000', 'currency' => 'BDT', 'exchange_rate' => '0.0085',
        'charges' => [['reason' => 'fuel', 'amount' => '1500']],
    ]);

    expect($quote->total)->toBe('11500.0000')->and($quote->total_store)->toBe('97.7500');
});

it('supports several shipments and tracking numbers on one order and exposes only tracking to customers', function () {
    $order = shippingOrder($this->workspace->id);
    $service = app(ShipmentService::class);
    $first = $service->create($order);
    $second = $service->create($order);

    foreach ([[$first, 'ups', '1Z999'], [$second, 'fedex', 'FX777']] as [$shipment, $carrier, $tracking]) {
        $quote = app(QuoteService::class)->store($shipment, ['source' => 'manual_direct', 'carrier' => $carrier, 'base_rate' => '90', 'currency' => 'USD', 'status' => 'verified']);
        app(QuoteApprovalService::class)->approve($shipment, $quote, $this->owner);
        $service->recordBooking($shipment->refresh(), ['tracking_number' => $tracking, 'tracking_url' => 'https://track.example/'.$tracking, 'status' => 'in_transit']);
    }

    expect($first->refresh()->reference)->toBe($order->number.'-S1')
        ->and($second->refresh()->reference)->toBe($order->number.'-S2')
        ->and(OrderShipment::query()->where('order_id', $order->id)->value('tracking_number'))->toBe('1Z999')
        ->and($order->refresh()->tracking_number)->toBe('1Z999');

    $payload = app(UnifiedOrderService::class)->payload($order);
    expect($payload['shipments'])->toHaveCount(2)
        ->and($payload['shipments'][0])->toBe([
            'carrier' => 'UPS', 'tracking_number' => '1Z999', 'tracking_url' => 'https://track.example/1Z999', 'status' => 'in_transit',
            'shipped_at' => $first->shipped_at->toIso8601String(), 'delivered_at' => null,
        ])
        ->and($payload['shipments'][1]['tracking_number'])->toBe('FX777');

    $json = json_encode($payload);
    foreach (['approved_cost', 'estimated_cost', 'final_cost', 'billed_amount', 'shipping_agent_id', 'cost_price', 'total_store', 'base_rate'] as $internal) {
        expect($json)->not->toContain($internal);
    }
});

it('leaves a tracking number that staff typed on the order by hand untouched', function () {
    $order = shippingOrder($this->workspace->id);
    OrderShipment::query()->create(['order_id' => $order->id, 'carrier' => 'DHL', 'tracking_number' => 'MANUAL-1', 'shipped_at' => now()]);
    $shipment = app(ShipmentService::class)->create($order);
    $quote = app(QuoteService::class)->store($shipment, ['source' => 'manual_direct', 'carrier' => 'ups', 'base_rate' => '90', 'currency' => 'USD', 'status' => 'verified']);
    app(QuoteApprovalService::class)->approve($shipment, $quote, $this->owner);
    app(ShipmentService::class)->recordBooking($shipment->refresh(), ['tracking_number' => '1Z111']);

    expect(OrderShipment::query()->where('order_id', $order->id)->value('tracking_number'))->toBe('MANUAL-1');
});

it('flags a shipment and notifies the owner when the bill exceeds the approved cost', function () {
    $shipment = app(ShipmentService::class)->create(shippingOrder($this->workspace->id));
    $quote = app(QuoteService::class)->store($shipment, ['source' => 'manual_direct', 'carrier' => 'ups', 'base_rate' => '100', 'currency' => 'USD', 'status' => 'verified']);
    app(QuoteApprovalService::class)->approve($shipment, $quote, $this->owner);
    $service = app(ShipmentService::class);

    $billed = $service->recordBilling($shipment->refresh(), [
        'billed_amount' => '135.50', 'billed_currency' => 'USD', 'payment_status' => 'unpaid',
        'charges' => [['reason' => 'remote_area', 'amount' => '30'], ['reason' => 'address_correction', 'amount' => '5.50']],
    ], $this->owner);

    expect($billed->final_cost)->toBe('135.5000')
        ->and($billed->needs_review)->toBeTrue()
        ->and($service->variance($billed))->toBe('35.50')
        ->and($billed->charges()->pluck('amount', 'reason')->all())->toBe(['remote_area' => '30.0000', 'address_correction' => '5.5000'])
        ->and(SystemNotification::query()->where('notifiable_id', $this->owner->id)->where('type', 'shipping')->count())->toBe(1);

    expect(fn () => $service->setStatus($billed, 'closed'))->toThrow(ValidationException::class);
    $service->resolveReview($billed, $this->owner, 'Remote fee accepted');
    expect($service->setStatus($billed->refresh(), 'closed')->status)->toBe('closed');

    $within = $service->recordBilling($billed->refresh(), ['billed_amount' => '99', 'billed_currency' => 'USD']);
    expect($within->needs_review)->toBeFalse()->and($service->variance($within))->toBe('-1.00');
});

it('opens one shipping record when an order is placed and skips pickup orders', function () {
    $order = shippingOrder($this->workspace->id);
    app(UnifiedOrderService::class)->event($order, 'placed', 'Order placed');
    app(UnifiedOrderService::class)->event($order, 'paid', 'Paid');

    $shipment = Shipment::query()->where('order_id', $order->id)->sole();
    expect($shipment->status)->toBe('pending')
        ->and($shipment->only(['recipient_name', 'country_code', 'postal_code', 'city']))->toBe(['recipient_name' => 'Jane Buyer', 'country_code' => 'US', 'postal_code' => '99501', 'city' => 'Anchorage']);

    $pickup = shippingOrder($this->workspace->id, ['fulfillment_type' => 'pickup']);
    app(UnifiedOrderService::class)->event($pickup, 'placed', 'Order placed');
    expect(Shipment::query()->where('order_id', $pickup->id)->exists())->toBeFalse();
});

it('imports a weight-break rate sheet and leaves it pending review', function () {
    $agent = ShippingAgent::query()->create(['workspace_id' => $this->workspace->id, 'name' => 'Sheet Agent']);
    $chart = AgentRateChart::query()->create(['workspace_id' => $this->workspace->id, 'shipping_agent_id' => $agent->id, 'name' => 'Nov rates']);
    $path = tempnam(sys_get_temp_dir(), 'rates').'.csv';
    file_put_contents($path, "Country,Weight (kg),Price,Transit Days\nUS,0.5,12.50,5-7\nUS,1,18,5-7\nGB,1,15,4-6\nUnited States,2,20,5\n,3,,\n");

    $result = app(RateChartImportService::class)->import($chart, $path);
    unlink($path);

    expect($result)->toBe(['imported' => 3, 'skipped' => 2])
        ->and($chart->refresh()->status)->toBe('pending_review')
        ->and($chart->rows()->where('country_code', 'US')->orderBy('max_weight_kg')->get(['min_weight_kg', 'max_weight_kg', 'price'])->toArray())->toBe([
            ['min_weight_kg' => '0.000', 'max_weight_kg' => '0.500', 'price' => '12.5000'],
            ['min_weight_kg' => '0.501', 'max_weight_kg' => '1.000', 'price' => '18.0000'],
        ]);
});

it('adds the shipping team roles to a new workspace with costs hidden from sales', function () {
    $roles = WorkspaceRole::query()->where('workspace_id', $this->workspace->id)->with('rolePermissions')->get()->keyBy('name');

    expect($roles->keys()->all())->toContain('Shipping Manager', 'Accounts', 'Sales Team')
        ->and($roles['Sales Team']->rolePermissions->pluck('permission_name')->all())->toBe(['commerce.orders.view', 'shipping.shipments.view'])
        ->and($roles['Accounts']->rolePermissions->pluck('permission_name')->all())->toContain('shipping.billing.manage')->not->toContain('shipping.quotes.approve');
});
