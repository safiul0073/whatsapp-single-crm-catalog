<?php

use App\Models\User;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Services\OrderWorkflowService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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

it('attaches courier link and image to the shipped step only', function () {
    Storage::fake('public');
    $order = marketingTrackingOrder();
    $order->shipment()->create([
        'carrier' => 'DHL',
        'tracking_number' => 'TRACK-100',
        'tracking_url' => 'https://carrier.example/track/TRACK-100',
        'proof_image' => 'commerce/shipments/1/receipt.jpg',
        'shipped_at' => now(),
    ]);

    $timeline = $order->fresh()->trackingTimeline();
    $shipped = collect($timeline)->firstWhere('key', 'shipped');

    expect($shipped['shipment'])->toMatchArray([
        'carrier' => 'DHL',
        'tracking_number' => 'TRACK-100',
        'tracking_url' => 'https://carrier.example/track/TRACK-100',
    ])->and($shipped['shipment']['proof_image_url'])->toEndWith('commerce/shipments/1/receipt.jpg')
        ->and(collect($timeline)->where('key', '!=', 'shipped')->every(fn (array $step): bool => ! isset($step['shipment'])))->toBeTrue();
});

it('does not show courier details before the order ships', function () {
    $order = marketingTrackingOrder(['status' => 'processing', 'shipped_at' => null]);
    expect(collect($order->trackingTimeline())->firstWhere('key', 'shipped'))->not->toHaveKey('shipment');
});

it('stores, replaces and removes the courier image through shipment updates', function () {
    Storage::fake('public');
    $order = marketingTrackingOrder();
    $workflow = app(OrderWorkflowService::class);

    $workflow->updateShipment($order, ['carrier' => 'FedEx', 'tracking_number' => 'TRACK-101', 'proof_image' => UploadedFile::fake()->image('first.jpg')]);
    $first = $order->fresh()->shipment->proof_image;
    Storage::disk('public')->assertExists($first);
    expect($order->fresh()->tracking_number)->toBe('TRACK-101');

    $workflow->updateShipment($order, ['tracking_number' => 'TRACK-101', 'proof_image' => UploadedFile::fake()->image('second.png')]);
    $second = $order->fresh()->shipment->proof_image;
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);
    expect($order->fresh()->shipment->carrier)->toBe('FedEx');

    $workflow->updateShipment($order, ['tracking_number' => 'TRACK-101', 'remove_proof_image' => true]);
    Storage::disk('public')->assertMissing($second);
    expect($order->fresh()->shipment->proof_image)->toBeNull();
});

it('rejects shipment updates before the order is shipped', function () {
    $order = marketingTrackingOrder(['status' => 'processing', 'shipped_at' => null]);
    expect(fn () => app(OrderWorkflowService::class)->updateShipment($order, ['tracking_number' => 'X-1']))
        ->toThrow(ValidationException::class);
});

it('validates courier image uploads on the owner shipment form', function () {
    Storage::fake('public');
    $order = marketingTrackingOrder();
    $user = User::query()->latest('id')->firstOrFail();
    Permission::findOrCreate('commerce.orders.update', 'web');
    $user->givePermissionTo('commerce.orders.update');

    $this->actingAs($user)->from(route('user.commerce.orders.show', $order))
        ->put(route('user.commerce.orders.shipment', $order), ['tracking_number' => 'TRACK-100', 'proof_image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('proof_image');

    $this->actingAs($user)->from(route('user.commerce.orders.show', $order))
        ->put(route('user.commerce.orders.shipment', $order), ['carrier' => 'Pathao', 'tracking_number' => 'TRACK-100', 'tracking_url' => 'https://carrier.example/t/1', 'proof_image' => UploadedFile::fake()->image('label.webp')])
        ->assertSessionHasNoErrors()->assertRedirect();

    expect($order->fresh()->shipment->carrier)->toBe('Pathao');
    Storage::disk('public')->assertExists($order->fresh()->shipment->proof_image);
});
