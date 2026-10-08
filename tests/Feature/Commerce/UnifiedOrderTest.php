<?php

use App\Models\User;
use App\Modules\Commerce\Jobs\NotifyOrderEvent;
use App\Modules\Commerce\Models\CommerceMessageAttempt;
use App\Modules\Commerce\Models\InventoryMovement;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\OrderInventoryService;
use App\Modules\Commerce\Services\OrderPackingService;
use App\Modules\Commerce\Services\OrderWhatsAppNotificationService;
use App\Modules\Commerce\Services\OrderWorkflowService;
use App\Modules\Commerce\Services\UnifiedOrderService;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Shipping\Models\ShippingMethod;
use App\Modules\Shipping\Models\ShippingZone;
use App\Modules\SystemNotifications\Models\SystemNotification;
use App\Modules\SystemNotifications\Services\SystemNotificationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function unifiedContext(): array
{
    $user = User::factory()->create();
    $workspace = app(WorkspaceResolver::class)->current($user);
    $settings = StoreOrderSetting::forWorkspace($workspace->id);
    $settings->update(['payment_instructions' => 'Bank transfer']);
    config(['commerce.store_workspace_id' => config('commerce.store_workspace_id') ?? $workspace->id]);
    $product = Product::query()->create(['workspace_id' => $workspace->id, 'name' => 'Cotton shirt', 'slug' => 'shirt-'.$workspace->id, 'sku' => 'shirt-'.$workspace->id, 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'both', 'single_piece_price' => '10.00', 'wholesale_price' => '6.00', 'ws_enabled' => true, 'ws_main_moq' => 12, 'ws_color_moq' => 6, 'ws_min_sizes' => 3, 'ws_ratio_multiplier' => 1]);
    $color = $product->colors()->create(['workspace_id' => $workspace->id, 'name' => 'Red', 'hex_code' => '#ff0000']);
    $product->update(['ws_size_ratios' => [$color->id => ['S' => 2, 'M' => 3, 'L' => 1]]]);
    $variants = collect(['S', 'M', 'L'])->map(fn ($size) => $product->variants()->create(['workspace_id' => $workspace->id, 'color_id' => $color->id, 'sku' => $workspace->id.'-'.$size, 'meta_retailer_id' => $workspace->id.'-'.$size, 'size' => $size, 'price' => 10, 'stock_quantity' => 100, 'status' => 'active']));
    $data = ['submission_reference' => (string) Str::uuid(), 'source' => 'manual', 'customer' => ['name' => 'Buyer', 'phone' => '+15555550100', 'email' => 'buyer@example.test'], 'shipping_address' => ['name' => 'Buyer', 'phone' => '+15555550100', 'line1' => '1 Main Street', 'city' => 'Boston', 'country' => 'US'], 'groups' => [['product_id' => $product->id, 'mode' => 'wholesale', 'color_id' => $color->id, 'box_count' => 3]]];

    return compact('user', 'workspace', 'settings', 'product', 'color', 'variants', 'data');
}

beforeEach(function () {
    Queue::fake([NotifyOrderEvent::class]);
});

it('snapshots three ratio boxes and reserves their exact variant pieces', function () {
    $c = unifiedContext();
    $order = app(UnifiedOrderService::class)->create($c['workspace'], $c['data']);
    expect($order->boxes)->toHaveCount(3)->and($order->items->sum('quantity'))->toBe(18)
        ->and($order->subtotal)->toBe('108.0000')->and($order->shipping_quote_required)->toBeTrue()
        ->and($order->total)->toBeNull()->and($order->reservations()->sum('quantity'))->toBe('18');
    foreach ($order->boxes as $box) {
        expect($box->contents->sum('quantity'))->toBe(6);
    }
    expect($c['variants'][0]->fresh()->stock_quantity)->toBe(100);
    $c['product']->update(['ws_size_ratios' => [$c['color']->id => ['S' => 100]]]);
    expect($order->groups->first()->ratio)->toEqual(['S' => 2, 'M' => 3, 'L' => 1]);
});

it('creates retail and mixed orders with server prices', function () {
    $c = unifiedContext();
    $c['data']['groups'][] = ['product_id' => $c['product']->id, 'mode' => 'retail', 'variant_id' => $c['variants'][0]->id, 'quantity' => 2, 'price' => '0.01'];
    $order = app(UnifiedOrderService::class)->create($c['workspace'], $c['data']);
    expect($order->items->sum('quantity'))->toBe(20)->and($order->subtotal)->toBe('128.0000');
});

it('rejects duplicate payload changes but returns an identical submission', function () {
    $c = unifiedContext();
    $service = app(UnifiedOrderService::class);
    $order = $service->create($c['workspace'], $c['data']);
    expect($service->create($c['workspace'], $c['data'])->id)->toBe($order->id)->and(Order::count())->toBe(1);
    $c['data']['groups'][0]['box_count'] = 4;
    try {
        $service->create($c['workspace'], $c['data']);
        $this->fail('Expected conflict');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(409);
    }
});

it('enforces minimum quantities and owner ratios', function () {
    $c = unifiedContext();
    $c['data']['groups'][0]['box_count'] = 1;
    expect(fn () => app(UnifiedOrderService::class)->create($c['workspace'], $c['data']))->toThrow(ValidationException::class);
    $c['data']['groups'][0]['box_count'] = 3;
    $c['data']['groups'][0]['ratio'] = ['S' => 1];
    expect(fn () => app(UnifiedOrderService::class)->create($c['workspace'], $c['data']))->toThrow(ValidationException::class);
    expect(Order::count())->toBe(0);
});

it('does not oversell stock already reserved by another order', function () {
    $c = unifiedContext();
    $c['variants'][0]->update(['stock_quantity' => 6]);
    app(UnifiedOrderService::class)->create($c['workspace'], $c['data']);
    $c['data']['submission_reference'] = (string) Str::uuid();
    expect(fn () => app(UnifiedOrderService::class)->create($c['workspace'], $c['data']))->toThrow(ValidationException::class);
    expect(Order::count())->toBe(1);
});

it('keeps drafts unreserved and submits them through the workflow', function () {
    $c = unifiedContext();
    $c['data']['draft'] = true;
    $order = app(UnifiedOrderService::class)->create($c['workspace'], $c['data']);
    expect($order->status)->toBe('draft')->and($order->reservations()->count())->toBe(0);
    app(OrderWorkflowService::class)->transition($order, 'requested');
    expect($order->fresh()->status)->toBe('requested')->and($order->reservations()->sum('quantity'))->toBe('18');
});

it('deducts once after payment, packs every box and creates one shipment', function () {
    $c = unifiedContext();
    $order = app(UnifiedOrderService::class)->create($c['workspace'], $c['data']);
    $workflow = app(OrderWorkflowService::class);
    $workflow->quote($order, ['shipping_address' => $c['data']['shipping_address'], 'shipping_amount' => 5, 'payment_url' => 'https://payment.example.test']);
    $paid = $workflow->transition($order->fresh(), 'paid');
    $workflow->transition($paid, 'paid');
    expect($c['variants'][0]->fresh()->stock_quantity)->toBe(94);
    foreach ($order->boxes as $box) {
        app(OrderPackingService::class)->markPacked($order, $box->id);
    }
    expect($order->fresh()->status)->toBe('packed');
    $shipped = $workflow->transition($order->fresh(), 'shipped', ['tracking_number' => 'CARRIER-1', 'carrier' => 'Carrier']);
    $workflow->transition($shipped, 'shipped', ['tracking_number' => 'CARRIER-2']);
    $workflow->transition($shipped, 'completed');
    expect($order->shipment()->count())->toBe(1)->and($order->fresh()->delivered_at)->not->toBeNull();
});

it('releases cancellation and expiry reservations safely', function () {
    $c = unifiedContext();
    $order = app(UnifiedOrderService::class)->create($c['workspace'], $c['data']);
    $this->travel(25)->hours();
    app(OrderInventoryService::class)->expire();
    app(OrderInventoryService::class)->expire();
    expect($order->reservations()->where('state', 'expired')->count())->toBe(3);
    app(OrderWorkflowService::class)->transition($order->fresh(), 'cancelled');
    expect($c['variants'][0]->fresh()->stock_quantity)->toBe(100);
});

it('ignores customer-selected workspaces on the store API', function () {
    $c = unifiedContext();
    unset($c['data']['source']);
    $c['data']['workspace_id'] = 9999;
    $zone = ShippingZone::create(['workspace_id' => $c['workspace']->id, 'name' => 'US', 'code' => 'US', 'is_active' => true]);
    $zone->countries()->create(['workspace_id' => $c['workspace']->id, 'country_code' => 'US']);
    $method = ShippingMethod::create(['workspace_id' => $c['workspace']->id, 'name' => 'Air', 'code' => 'AIR', 'type' => 'air', 'is_active' => true]);
    $zone->rates()->create(['workspace_id' => $c['workspace']->id, 'shipping_method_id' => $method->id, 'min_weight_kg' => 0, 'price' => 5, 'price_per_kg' => 0, 'currency' => 'USD', 'is_active' => true]);
    $response = $this->postJson('/api/commerce/store/orders', $c['data'])->assertCreated();
    expect(Order::find($response->json('data.id'))->workspace_id)->toBe($c['workspace']->id);
    $this->getJson('/api/commerce/track/'.$response->json('data.tracking_code'))->assertOk()->assertDontSee('1 Main Street');
});

it('renders manual order management with appropriate permissions', function () {
    $c = unifiedContext();
    $outsider = User::factory()->create();
    $c['workspace']->members()->attach($outsider, ['status' => 'active']);
    $this->withSession(['active_workspace_id' => $c['workspace']->id]);
    $this->actingAs($outsider)->get(route('user.commerce.orders.create'))->assertForbidden();
    $role = Role::findOrCreate('Order manager', 'web');
    $role->givePermissionTo(Permission::findOrCreate('commerce.manage', 'web'));
    $c['user']->assignRole($role);
    $this->actingAs($c['user'])->get(route('user.commerce.orders.create'))->assertOk()->assertSee('Create Order');
});

it('aggregates wholesale tiers across colors without changing each box ratio', function () {
    $c = unifiedContext();
    $second = $c['product']->colors()->create(['workspace_id' => $c['workspace']->id, 'name' => 'Blue', 'hex_code' => '#0000ff']);
    $c['product']->update(['ws_size_ratios' => [$c['color']->id => ['S' => 2, 'M' => 3, 'L' => 1], $second->id => ['S' => 2, 'M' => 3, 'L' => 1]]]);
    foreach (['S', 'M', 'L'] as $size) {
        $c['product']->variants()->create(['workspace_id' => $c['workspace']->id, 'color_id' => $second->id, 'sku' => 'blue-'.$size, 'meta_retailer_id' => 'blue-'.$size, 'size' => $size, 'price' => 10, 'stock_quantity' => 100, 'status' => 'active']);
    }
    $c['product']->tierPrices()->create(['min_quantity' => 24, 'unit_price' => 4, 'workspace_id' => $c['workspace']->id]);
    $c['data']['groups'][] = ['product_id' => $c['product']->id, 'mode' => 'wholesale', 'color_id' => $second->id, 'box_count' => 3];
    $order = app(UnifiedOrderService::class)->create($c['workspace'], $c['data']);
    expect($order->subtotal)->toBe('144.0000')->and($order->boxes()->count())->toBe(6)->and($order->items->sum('quantity'))->toBe(36);
});

it('reacquires expired stock before payment and rejects stock held by another order', function () {
    $c = unifiedContext();
    $c['variants'][0]->update(['stock_quantity' => 6]);
    $service = app(UnifiedOrderService::class);
    $order = $service->create($c['workspace'], $c['data']);
    $this->travel(25)->hours();
    app(OrderInventoryService::class)->expire();
    $c['data']['submission_reference'] = (string) Str::uuid();
    $service->create($c['workspace'], $c['data']);
    $workflow = app(OrderWorkflowService::class);
    $workflow->quote($order, ['shipping_address' => $c['data']['shipping_address'], 'shipping_amount' => 5]);
    expect(fn () => $workflow->transition($order->fresh(), 'paid'))->toThrow(ValidationException::class);
    expect($c['variants'][0]->fresh()->stock_quantity)->toBe(6)->and($order->fresh()->payment_state)->toBe('unpaid');
});

it('blocks over-allocation and dispatch until every retail piece is packed', function () {
    $c = unifiedContext();
    $c['data']['groups'] = [['product_id' => $c['product']->id, 'mode' => 'retail', 'variant_id' => $c['variants'][0]->id, 'quantity' => 2]];
    $order = app(UnifiedOrderService::class)->create($c['workspace'], $c['data']);
    $workflow = app(OrderWorkflowService::class);
    $workflow->quote($order, ['shipping_address' => $c['data']['shipping_address'], 'shipping_amount' => 1]);
    $order = $workflow->transition($order->fresh(), 'paid');
    $packing = app(OrderPackingService::class);
    $item = $order->items->first();
    expect(fn () => $packing->addRetailBox($order, [$item->id => 3]))->toThrow(ValidationException::class);
    $box = $packing->addRetailBox($order, [$item->id => 1]);
    $packing->markPacked($order, $box->id);
    expect($packing->complete($order->fresh()))->toBeFalse();
    expect(fn () => $workflow->transition($order->fresh(), 'shipped', ['tracking_number' => 'RETAIL-1']))->toThrow(ValidationException::class);
    $box = $packing->addRetailBox($order, [$item->id => 1]);
    $packing->markPacked($order, $box->id);
    expect($packing->complete($order->fresh()))->toBeTrue()->and($order->fresh()->status)->toBe('packed');
});

it('keeps historical currency precision when store currency changes', function () {
    $c = unifiedContext();
    $c['settings']->update(['currency' => 'KWD']);
    $c['product']->update(['single_piece_price' => '1.234']);
    $c['data']['groups'] = [['product_id' => $c['product']->id, 'mode' => 'retail', 'variant_id' => $c['variants'][0]->id, 'quantity' => 2]];
    $order = app(UnifiedOrderService::class)->create($c['workspace'], $c['data']);
    $c['settings']->update(['currency' => 'JPY']);
    app(OrderWorkflowService::class)->quote($order, ['shipping_address' => $c['data']['shipping_address'], 'shipping_amount' => '0.111']);
    expect($order->fresh()->currency)->toBe('KWD')->and($order->fresh()->total)->toBe('2.5790');
});

it('rejects cross-store products and cross-workspace staff access', function () {
    $c = unifiedContext();
    $other = unifiedContext();
    $c['data']['groups'][0]['product_id'] = $other['product']->id;
    expect(fn () => app(UnifiedOrderService::class)->create($c['workspace'], $c['data']))->toThrow(ModelNotFoundException::class);
    $order = app(UnifiedOrderService::class)->create($other['workspace'], $other['data']);
    $this->actingAs($c['user'])->withSession(['active_workspace_id' => $c['workspace']->id])->get(route('user.commerce.orders.packing-slip', $order))->assertNotFound();
});

it('accepts payment evidence for review without deducting stock', function () {
    $c = unifiedContext();
    $order = app(UnifiedOrderService::class)->create($c['workspace'], $c['data']);
    app(OrderWorkflowService::class)->quote($order, ['shipping_address' => $c['data']['shipping_address'], 'shipping_amount' => 5]);
    Storage::fake('local');
    $c['workspace']->update(['settings' => ['commerce' => ['payment_methods' => [['id' => 'remitly', 'name' => 'Remitly', 'active' => true, 'recipient_details' => 'Store account', 'instructions' => 'Transfer', 'sort_order' => 0, 'fields' => []]]]]]);
    $proof = ['payment_method_id' => 'remitly', 'transaction_id' => 'ABC', 'receipts' => [UploadedFile::fake()->image('one.png'), UploadedFile::fake()->image('two.jpg')]];
    $this->postJson('/api/commerce/store/orders/'.$order->submission_reference.'/evidence', $proof)->assertOk();
    expect($order->fresh()->payment_evidence['receipts'])->toHaveCount(2);
    foreach ($order->fresh()->payment_evidence['receipts'] as $receipt) {
        Storage::disk('local')->assertExists($receipt['path']);
    }
    $this->postJson('/api/commerce/store/orders/'.$order->submission_reference.'/evidence', $proof)->assertUnprocessable();
    expect($order->fresh()->payment_state)->toBe('submitted')->and($c['variants'][0]->fresh()->stock_quantity)->toBe(100);
    app(OrderWorkflowService::class)->transition($order->fresh(), 'paid');
    $this->postJson('/api/commerce/store/orders/'.$order->submission_reference.'/evidence', ['note' => 'Late receipt'])->assertUnprocessable();
    expect($order->fresh()->payment_state)->toBe('paid');
});

it('renders summaries packing slips and currency settings', function () {
    $c = unifiedContext();
    $order = app(UnifiedOrderService::class)->create($c['workspace'], $c['data']);
    $this->actingAs($c['user'])->get(route('user.commerce.orders.show', $order))->assertOk()->assertSee('BOX-1')
        ->assertSee('Pack 1 of 3')->assertSee('Color: Red')->assertDontSee('Ordered items')->assertSeeInOrder(['>S<', '>M<', '>L<'], false);
    $this->get(route('user.commerce.orders.packing-slip', $order))->assertOk()->assertSee('Cotton shirt');
    $this->get(route('user.commerce.orders.settings'))->assertOk()->assertSee('Store order settings');
});

it('keeps the selected shipping method and calculates its price in store precision', function () {
    $c = unifiedContext();
    $c['settings']->update(['currency' => 'KWD']);
    $zone = ShippingZone::create(['workspace_id' => $c['workspace']->id, 'name' => 'US', 'code' => 'US', 'is_active' => true]);
    $zone->countries()->create(['workspace_id' => $c['workspace']->id, 'country_code' => 'US']);
    $method = ShippingMethod::create(['workspace_id' => $c['workspace']->id, 'name' => 'Air', 'code' => 'AIR', 'type' => 'air', 'is_active' => true]);
    $zone->rates()->create(['workspace_id' => $c['workspace']->id, 'shipping_method_id' => $method->id, 'min_weight_kg' => 0, 'price' => '1.234', 'price_per_kg' => 0, 'currency' => 'KWD', 'is_active' => true]);
    $c['data']['shipping_method_id'] = $method->id;
    $service = app(UnifiedOrderService::class);
    $order = $service->create($c['workspace'], $c['data']);
    expect($order->shipping_method_id)->toBe($method->id)->and($order->delivery_method)->toBe('Air')->and($order->shipping_amount)->toBe('1.2340')->and($order->total)->toBe('109.2340');
    $c['data']['shipping_method_id'] = $method->id + 100;
    expect(fn () => $service->preview($c['workspace'], $c['data']))->toThrow(ValidationException::class);
});

it('resolves an ambiguous native wholesale request without guessing its initial contents', function () {
    $c = unifiedContext();
    $order = Order::create(['workspace_id' => $c['workspace']->id, 'number' => 'ORD-NATIVE', 'source' => 'native_whatsapp', 'status' => 'needs_details', 'currency' => 'USD', 'subtotal' => 0, 'tracking_code' => 'TRK-NATIVE', 'issues' => ['Confirm catalog selections and delivery details.']]);
    $resolved = app(UnifiedOrderService::class)->completeRequest($order, $c['workspace'], $c['data']);
    expect($resolved->id)->toBe($order->id)->and($resolved->boxes()->count())->toBe(3)->and($resolved->reservations()->sum('quantity'))->toBe('18')->and($resolved->issues)->toBe([]);
    expect(fn () => app(UnifiedOrderService::class)->completeRequest($resolved, $c['workspace'], $c['data']))->toThrow(HttpException::class);
});

it('respects WhatsApp eligibility and sends each order event once', function () {
    $c = unifiedContext();
    $order = app(UnifiedOrderService::class)->create($c['workspace'], $c['data']);
    $event = $order->events()->first();
    $channel = ChannelAccount::create(['workspace_id' => $c['workspace']->id, 'provider' => 'whatsapp', 'name' => 'Sales', 'status' => 'connected', 'provider_account_id' => 'waba-test', 'provider_phone_id' => 'phone-test']);
    $conversation = Conversation::create(['workspace_id' => $c['workspace']->id, 'channel_account_id' => $channel->id, 'provider' => 'whatsapp', 'contact_id' => $order->contact_id, 'session_expires_at' => now()->subHour()]);
    $this->mock(ChannelManager::class)->shouldReceive('sendMessage')->once()->andReturn(['ok' => true, 'provider_message_id' => 'outbound-event-1']);
    $service = app(OrderWhatsAppNotificationService::class);
    $service->send($order, $event);
    $c['settings']->update(['whatsapp_notifications' => true, 'whatsapp_channel_id' => $channel->id]);
    $service->send($order, $event);
    expect(CommerceMessageAttempt::count())->toBe(0);
    $conversation->update(['session_expires_at' => now()->addDay()]);
    $service->send($order, $event);
    $service->send($order, $event);
    expect(CommerceMessageAttempt::count())->toBe(1)->and(CommerceMessageAttempt::first()->status)->toBe('completed');
    $order->contact->update(['opt_out_at' => now()]);
    $next = $order->events()->create(['key' => 'test-opt-out', 'label' => 'Update', 'occurred_at' => now()]);
    $service->send($order->fresh('contact'), $next);
    expect(CommerceMessageAttempt::count())->toBe(1);
});

it('rejects browser-provided order prices through the integration API', function () {
    $c = unifiedContext();
    $c['data']['groups'][0]['price'] = '0.01';
    $this->postJson('/api/commerce/store/orders', $c['data'])->assertUnprocessable()->assertJsonValidationErrors('groups.0.price');
    expect(Order::count())->toBe(0);
});

it('retries durable order events without repeating completed notification channels', function () {
    $c = unifiedContext();
    $c['data']['customer']['email'] = null;
    $order = app(UnifiedOrderService::class)->create($c['workspace'], $c['data']);
    $event = $order->events()->first();
    $job = new NotifyOrderEvent($event->id);
    $notifications = app(SystemNotificationService::class);
    $job->handle($notifications);
    $job->handle($notifications);
    expect($event->fresh()->notification_attempts)->toBe(1)
        ->and($event->fresh()->customer_notified_at)->not->toBeNull()
        ->and(SystemNotification::where('type', 'commerce')->count())->toBe(1);
    $event->update(['notification_attempts' => 10, 'whatsapp_notified_at' => null]);
    $job->handle($notifications);
    expect($event->fresh()->notification_attempts)->toBe(10);
});

it('lists only enabled configured methods and saves methods in workspace settings', function () {
    $c = unifiedContext();
    expect(array_column(StoreOrderSetting::paymentMethods($c['workspace']->id), 'name'))->toBe(['Remitly', 'Taptap Send', 'MoneyGram']);
    $this->getJson('/api/commerce/store/settings')->assertOk()->assertJsonPath('payment_methods', []);
    $methods = [['id' => 'remitly', 'name' => 'Remitly', 'active' => '1', 'recipient_details' => 'Recipient details', 'instructions' => 'Transfer instructions', 'sort_order' => 0, 'fields' => [['name' => 'sender', 'label' => 'Sender name', 'required' => '1']]]];
    $this->actingAs($c['user'])->put(route('user.commerce.orders.settings.update'), ['currency' => 'USD', 'reservation_hours' => 24, 'payment_methods' => $methods])->assertSessionHasNoErrors();
    $this->getJson('/api/commerce/store/settings')->assertJsonPath('payment_methods.0.name', 'Remitly');
    $methods[0]['recipient_details'] = '';
    $this->put(route('user.commerce.orders.settings.update'), ['currency' => 'USD', 'reservation_hours' => 24, 'payment_methods' => $methods])->assertSessionHasErrors('payment_methods.0.recipient_details');
});

it('rejects invalid manual proof and protects private receipts', function () {
    $c = unifiedContext();
    $c['workspace']->update(['settings' => ['commerce' => ['payment_methods' => [['id' => 'remitly', 'name' => 'Remitly', 'active' => true, 'recipient_details' => 'Account', 'sort_order' => 0, 'fields' => [['name' => 'sender', 'label' => 'Sender', 'required' => true]]]]]]]);
    $order = app(UnifiedOrderService::class)->create($c['workspace'], $c['data']);
    app(OrderWorkflowService::class)->quote($order, ['shipping_address' => $c['data']['shipping_address'], 'shipping_amount' => 5]);
    $proof = ['payment_method_id' => 'remitly', 'transaction_id' => 'TX123', 'receipts' => [UploadedFile::fake()->image('proof.png')]];
    $url = '/api/commerce/store/orders/'.$order->submission_reference.'/evidence';
    $this->postJson($url, $proof)->assertJsonValidationErrors('fields.sender');
    $proof['fields'] = ['sender' => 'Buyer'];
    $proof['receipts'] = [UploadedFile::fake()->create('proof.pdf', 1, 'application/pdf')];
    $this->postJson($url, $proof)->assertJsonValidationErrors('receipts.0');
    $proof['receipts'] = array_fill(0, 6, UploadedFile::fake()->image('proof.png'));
    $this->postJson($url, $proof)->assertJsonValidationErrors('receipts');
    $proof['receipts'] = [UploadedFile::fake()->image('proof.png')->size(4097)];
    $this->postJson($url, $proof)->assertJsonValidationErrors('receipts.0');
    $proof['receipts'] = [UploadedFile::fake()->image('proof.png')];
    $proof['payment_method_id'] = 'disabled';
    $this->postJson($url, $proof)->assertJsonValidationErrors('payment_method_id');
    Storage::fake('local');
    Storage::disk('local')->put('commerce-receipts/legacy.png', 'receipt');
    $order->update(['payment_evidence' => ['receipt_path' => 'commerce-receipts/legacy.png']]);
    $this->actingAs($c['user'])->get(route('user.commerce.orders.receipt', $order))->assertOk();
    $this->get(route('user.commerce.orders.receipt', ['order' => $order, 'index' => -1]))->assertNotFound();
    $outsider = User::factory()->create();
    app(WorkspaceResolver::class)->current($outsider);
    $this->actingAs($outsider)->get(route('user.commerce.orders.receipt', $order))->assertNotFound();
});

it('blocks storefront creation without shipping while keeping staff quote workflow', function () {
    $c = unifiedContext();
    $this->postJson('/api/commerce/store/orders', $c['data'])->assertUnprocessable();
    expect(Order::count())->toBe(0);
    expect(app(UnifiedOrderService::class)->create($c['workspace'], $c['data'])->shipping_quote_required)->toBeTrue();
});

it('quotes variant physical weight and shipping cost in the store currency', function () {
    $c = unifiedContext();
    $c['variants'][0]->update(['weight_kg' => 0.5]);
    $c['data']['groups'] = [['product_id' => $c['product']->id, 'mode' => 'retail', 'variant_id' => $c['variants'][0]->id, 'quantity' => 3]];
    $zone = ShippingZone::create(['workspace_id' => $c['workspace']->id, 'name' => 'US', 'code' => 'US', 'is_active' => true]);
    $zone->countries()->create(['workspace_id' => $c['workspace']->id, 'country_code' => 'US']);
    $method = ShippingMethod::create(['workspace_id' => $c['workspace']->id, 'name' => 'Air', 'code' => 'AIR', 'type' => 'air', 'is_active' => true]);
    $zone->rates()->create(['workspace_id' => $c['workspace']->id, 'shipping_method_id' => $method->id, 'min_weight_kg' => 0, 'max_weight_kg' => 2, 'price' => 5, 'price_per_kg' => 2, 'currency' => 'USD', 'is_active' => true]);
    $quote = app(UnifiedOrderService::class)->preview($c['workspace'], $c['data']);
    expect($quote['chargeable_weight_kg'])->toBe(1.5)->and($quote['shipping_amount'])->toBe('8.00')->and($quote['total'])->toBe('38.00');
    $c['data']['shipping_address']['country'] = 'BD';
    expect(app(UnifiedOrderService::class)->preview($c['workspace'], $c['data'])['shipping_quote_required'])->toBeTrue();
});

it('allows destination-only storefront quotes while orders require full contact details', function () {
    $c = unifiedContext();
    $data = $c['data'];
    $data['source'] = 'storefront_checkout';
    $data['customer'] = [];
    $data['shipping_address'] = ['country' => 'US'];
    $this->postJson('/api/commerce/store/orders/preview', $data)->assertOk();
    $this->postJson('/api/commerce/store/orders', $data)->assertJsonValidationErrors(['customer.name', 'customer.phone', 'shipping_address.line1', 'shipping_address.city']);
});

it('creates edits and deletes payment services with workspace scoped icon uploads', function () {
    Storage::fake('public');
    $c = unifiedContext();
    $url = route('user.commerce.orders.settings.update');
    $method = ['id' => 'custom-transfer', 'name' => 'Custom transfer', 'active' => '1', 'recipient_details' => 'Store recipient', 'instructions' => 'Send then upload proof', 'sort_order' => 1, 'fields' => [['name' => 'sender_name', 'label' => 'Sender name', 'required' => '1']]];
    $data = ['currency' => 'USD', 'reservation_hours' => 24, 'payment_methods' => [$method], 'payment_icons' => ['custom-transfer' => UploadedFile::fake()->image('icon.png')]];
    $this->actingAs($c['user'])->put($url, $data)->assertSessionHasNoErrors();
    $saved = StoreOrderSetting::paymentMethods($c['workspace']->id)[0];
    Storage::disk('public')->assertExists($saved['icon_path']);
    expect($saved['icon_path'])->toStartWith('payment-icons/'.$c['workspace']->id.'/');
    $this->getJson('/api/commerce/store/settings')->assertJsonPath('payment_methods.0.icon_url', Storage::disk('public')->url($saved['icon_path']));
    unset($data['payment_icons']);
    $data['payment_methods'][0]['name'] = 'Updated service';
    $this->put($url, $data)->assertSessionHasNoErrors();
    expect(StoreOrderSetting::paymentMethods($c['workspace']->id)[0]['icon_path'])->toBe($saved['icon_path']);
    $data['payment_icons'] = ['custom-transfer' => UploadedFile::fake()->image('replacement.jpg')];
    $this->put($url, $data)->assertSessionHasNoErrors();
    Storage::disk('public')->assertMissing($saved['icon_path']);
    $replacement = StoreOrderSetting::paymentMethods($c['workspace']->id)[0]['icon_path'];
    unset($data['payment_icons']);
    $data['payment_methods'][0]['remove_icon'] = '1';
    $this->put($url, $data)->assertSessionHasNoErrors();
    Storage::disk('public')->assertMissing($replacement);
    expect(StoreOrderSetting::paymentMethods($c['workspace']->id)[0]['icon_url'])->toEndWith('manual.svg');
    $data['payment_methods'] = [];
    $this->put($url, $data)->assertSessionHasNoErrors();
    expect(StoreOrderSetting::paymentMethods($c['workspace']->id))->toBe([]);
});

it('rejects unsafe or oversized icons and protects payment configuration from other users', function () {
    Storage::fake('public');
    $c = unifiedContext();
    $data = ['currency' => 'USD', 'reservation_hours' => 24, 'payment_methods' => StoreOrderSetting::defaultPaymentMethods(), 'payment_icons' => ['remitly' => UploadedFile::fake()->create('icon.svg', 1, 'image/svg+xml')]];
    $url = route('user.commerce.orders.settings.update');
    $this->actingAs($c['user'])->put($url, $data)->assertSessionHasErrors('payment_icons.remitly');
    $data['payment_icons']['remitly'] = UploadedFile::fake()->image('icon.png')->size(2049);
    $this->put($url, $data)->assertSessionHasErrors('payment_icons.remitly');
    unset($data['payment_icons']);
    $data['payment_methods'][0]['icon_path'] = 'payment-icons/another-workspace/icon.png';
    $this->put($url, $data)->assertSessionHasErrors('payment_methods.0.icon_path');
    $outsider = User::factory()->create();
    $c['workspace']->members()->attach($outsider, ['status' => 'active']);
    $this->withSession(['active_workspace_id' => $c['workspace']->id])->actingAs($outsider)->put($url, $data)->assertForbidden();
});

it('manages payment services through dedicated payment services page', function () {
    $c = unifiedContext();
    $this->actingAs($c['user'])->get(route('user.commerce.payment-services.index'))
        ->assertOk()
        ->assertSee('Payment Services')
        ->assertSee('Quick Presets');

    $methods = [
        [
            'id' => 'taptap-send',
            'name' => 'Taptap Send',
            'active' => '1',
            'recipient_details' => 'bKash +8801819876543',
            'instructions' => 'Send exact amount',
            'sort_order' => 0,
            'fields' => [['name' => 'sender_name', 'label' => 'Sender Name', 'required' => '1']],
        ],
    ];

    $this->put(route('user.commerce.payment-services.update'), ['payment_methods' => $methods])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(StoreOrderSetting::paymentMethods($c['workspace']->id)[0]['name'])->toBe('Taptap Send');
});

it('serves the store api without credentials from the oldest active workspace', function () {
    $c = unifiedContext();

    $this->getJson('/api/commerce/store/settings')
        ->assertOk()->assertJsonPath('currency', $c['settings']->fresh()->currency);
});

it('keeps the customer shipping address locked when staff save a quote', function () {
    $c = unifiedContext();
    $order = app(UnifiedOrderService::class)->create($c['workspace'], $c['data']);

    $this->actingAs($c['user'])->get(route('user.commerce.orders.show', $order))
        ->assertOk()->assertDontSee('name="shipping_line1"', false)->assertSee('1 Main Street')->assertDontSee('Order review help');

    $this->put(route('user.commerce.orders.quote', $order), ['shipping_line1' => 'Changed Road', 'shipping_country' => 'BD', 'shipping_amount' => 5])
        ->assertSessionHasNoErrors();

    expect($order->fresh()->shipping_address['line1'])->toBe('1 Main Street')
        ->and($order->fresh()->shipping_address['country'])->toBe('US');
});

it('deducts storefront order stock only once payment is confirmed and restores it on cancellation', function () {
    $c = unifiedContext();
    $zone = ShippingZone::create(['workspace_id' => $c['workspace']->id, 'name' => 'US', 'code' => 'US', 'is_active' => true]);
    $zone->countries()->create(['workspace_id' => $c['workspace']->id, 'country_code' => 'US']);
    $method = ShippingMethod::create(['workspace_id' => $c['workspace']->id, 'name' => 'Air', 'code' => 'AIR', 'type' => 'air', 'is_active' => true]);
    $zone->rates()->create(['workspace_id' => $c['workspace']->id, 'shipping_method_id' => $method->id, 'min_weight_kg' => 0, 'price' => 5, 'price_per_kg' => 0, 'currency' => 'USD', 'is_active' => true]);
    unset($c['data']['source']);
    $retail = fn (): array => array_merge($c['data'], ['submission_reference' => (string) Str::uuid(), 'groups' => [['product_id' => $c['product']->id, 'mode' => 'retail', 'variant_id' => $c['variants'][0]->id, 'quantity' => 2]]]);

    $placed = Order::findOrFail($this->postJson('/api/commerce/store/orders', $retail())->assertCreated()->json('data.id'));
    expect($c['variants'][0]->fresh()->stock_quantity)->toBe(100)
        ->and((int) $placed->reservations()->where('state', 'reserved')->sum('quantity'))->toBe(2);

    $workflow = app(OrderWorkflowService::class);
    $workflow->transition($placed->fresh(), 'paid');
    $workflow->transition($placed->fresh(), 'paid');
    expect($c['variants'][0]->fresh()->stock_quantity)->toBe(98)
        ->and((int) InventoryMovement::query()->where('order_id', $placed->id)->where('reason', 'order_paid')->sum('quantity_delta'))->toBe(-2);

    $cancelled = Order::findOrFail($this->postJson('/api/commerce/store/orders', $retail())->assertCreated()->json('data.id'));
    $workflow->transition($cancelled->fresh(), 'paid');
    expect($c['variants'][0]->fresh()->stock_quantity)->toBe(96);
    $workflow->transition($cancelled->fresh(), 'cancelled');
    expect($c['variants'][0]->fresh()->stock_quantity)->toBe(98);
});
