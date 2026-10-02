<?php

use App\Models\User;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Services\OrderWorkflowService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function marketingTrackingOrder(array $attributes = []): Order
{
    $user = User::factory()->create();
    $workspace = app(WorkspaceResolver::class)->current($user);

    return Order::query()->create(array_replace([
        'workspace_id' => $workspace->id,
        'number' => 'ORD-'.fake()->unique()->numerify('######'),
        'provider_message_id' => fake()->uuid(),
        'status' => 'shipped',
        'tracking_number' => 'TRACK-100',
        'tracking_url' => 'https://carrier.example/track/TRACK-100',
        'paid_at' => now()->subDays(2),
        'shipped_at' => now()->subDay(),
        'shipping_address' => ['name' => 'Private Customer', 'line1' => 'Private address'],
    ], $attributes));
}

it('returns public order progress without customer or payment details', function () {
    $order = marketingTrackingOrder();
    $response = $this->getJson('/api/commerce/track/TRACK-100')
        ->assertOk()->assertJsonPath('success', true)
        ->assertJsonPath('order.number', $order->number)
        ->assertJsonPath('order.timeline.0.state', 'complete')
        ->assertJsonPath('order.timeline.3.state', 'current')
        ->assertJsonPath('order.timeline.4.state', 'upcoming');

    expect($response->json('order'))->not->toHaveKeys(['shipping_address', 'contact_id', 'payment_url', 'total', 'items']);
    $response->assertDontSee('Private Customer')->assertDontSee('Private address');
});

it('returns not found for unknown tracking numbers and order numbers', function () {
    $order = marketingTrackingOrder();
    $this->getJson('/api/commerce/track/UNKNOWN')->assertNotFound()->assertJsonPath('success', false);
    $this->getJson('/api/commerce/track/'.$order->number)->assertNotFound();
});

it('does not expose an arbitrary order when tracking numbers are duplicated', function () {
    marketingTrackingOrder();
    marketingTrackingOrder();
    $this->getJson('/api/commerce/track/TRACK-100')->assertNotFound();
});

it('marks all milestones complete only after marketing confirms delivery', function () {
    marketingTrackingOrder(['status' => 'completed']);
    $response = $this->getJson('/api/commerce/track/TRACK-100')
        ->assertOk()->assertJsonPath('order.status_label', 'Delivered');
    expect(collect($response->json('order.timeline'))->pluck('state')->unique()->all())->toBe(['complete']);
});

it('stops cancelled orders without inventing shipping or delivery dates', function () {
    marketingTrackingOrder(['status' => 'cancelled', 'paid_at' => null, 'shipped_at' => null]);
    $this->getJson('/api/commerce/track/TRACK-100')->assertOk()
        ->assertJsonPath('order.timeline.0.state', 'complete')
        ->assertJsonPath('order.timeline.1.state', 'stopped')
        ->assertJsonPath('order.timeline.3.date', null)
        ->assertJsonPath('order.timeline.4.state', 'stopped');
});

it('uses the marketing workflow to publish shipping and delivery updates', function () {
    $order = marketingTrackingOrder(['status' => 'processing', 'tracking_number' => null, 'tracking_url' => null, 'shipped_at' => null]);
    $workflow = app(OrderWorkflowService::class);
    $shipped = $workflow->transition($order, 'shipped', [
        'tracking_number' => 'SHIP-200', 'tracking_url' => 'https://carrier.example/SHIP-200',
    ]);
    $this->getJson('/api/commerce/track/SHIP-200')->assertOk()
        ->assertJsonPath('order.status', 'shipped')
        ->assertJsonPath('order.tracking_url', 'https://carrier.example/SHIP-200');

    $workflow->transition($shipped, 'completed');
    $this->getJson('/api/commerce/track/SHIP-200')->assertOk()
        ->assertJsonPath('order.status_label', 'Delivered')->assertJsonPath('order.timeline.1.label', 'Delivered')->assertJsonPath('order.timeline.1.state', 'complete');
});

it('shows tracking details and prefilled shipping fields in marketing order management', function () {
    $order = marketingTrackingOrder();
    $user = User::query()->latest('id')->firstOrFail();
    Permission::findOrCreate('commerce.view', 'web');
    $user->givePermissionTo('commerce.view');

    $this->actingAs($user)->get(route('user.commerce.orders.show', $order))
        ->assertOk()->assertSee('storefront Order Tracking page')
        ->assertSee('value="TRACK-100"', false)
        ->assertSee('value="https://carrier.example/track/TRACK-100"', false)
        ->assertSee('Shipped / in transit');
});

it('rejects blank tracking numbers even when an order has an empty number', function () {
    marketingTrackingOrder(['tracking_number' => '']);
    $this->getJson('/api/commerce/track/%20')->assertNotFound();
});

it('maps each active status to the appropriate progress step', function (string $status, int $current) {
    $order = new Order(['status' => $status]);
    $timeline = $order->trackingTimeline();
    expect($timeline[$current]['state'])->toBe('current');
    foreach ($timeline as $index => $step) {
        if ($index > $current) {
            expect($step['state'])->toBe('upcoming')->and($step['date'])->toBeNull();
        }
    }
})->with([
    ['requested', 0], ['needs_details', 0], ['quoted', 0], ['awaiting_payment', 0],
    ['paid', 1], ['processing', 2], ['shipped', 3],
]);
