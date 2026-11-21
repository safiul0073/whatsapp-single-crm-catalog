<?php

use App\Models\User;
use App\Modules\Commerce\Jobs\NotifyOrderEvent;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\OrderMessageService;
use App\Modules\Commerce\Services\UnifiedOrderService;
use App\Modules\Contacts\Models\ContactProviderIdentity;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake([NotifyOrderEvent::class]);

    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceResolver::class)->current($this->owner);
    StoreOrderSetting::forWorkspace($this->workspace->id);

    $this->product = Product::query()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Classic Tee',
        'slug' => 'classic-tee',
        'sku' => 'TEE-001',
        'status' => 'active',
        'visibility' => 'published',
        'selling_mode' => 'both',
        'single_piece_price' => 20,
        'wholesale_price' => 12,
        'ws_enabled' => true,
    ]);

    $this->color = $this->product->colors()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Black',
    ]);

    $this->variant = $this->product->variants()->create([
        'workspace_id' => $this->workspace->id,
        'color_id' => $this->color->id,
        'sku' => 'TEE-001-L',
        'meta_retailer_id' => 'TEE-001-L',
        'size' => 'L',
        'price' => 20,
        'stock_quantity' => 100,
        'status' => 'active',
    ]);

    $this->actingAs($this->owner);
});

function createTestOrder(mixed $test, array $customer, ?string $email = null): Order
{
    $order = app(UnifiedOrderService::class)->create($test->workspace, [
        'submission_reference' => (string) Str::uuid(),
        'source' => 'storefront',
        'customer' => $customer,
        'shipping_address' => array_filter([
            'name' => $customer['name'] ?? 'Customer',
            'phone' => $customer['phone'] ?? null,
            'line1' => '123 Main St',
            'city' => 'Austin',
            'country' => 'US',
        ]),
        'groups' => [
            ['product_id' => $test->product->id, 'mode' => 'retail', 'variant_id' => $test->variant->id, 'quantity' => 1],
        ],
    ]);

    if ($email !== null && $order->contact) {
        $order->contact->update(['email' => $email]);
        $snapshot = $order->customer_snapshot ?? [];
        $snapshot['email'] = $email;
        $order->update(['customer_snapshot' => $snapshot]);
    }

    return $order->fresh(['contact.identities']);
}

it('shows only active connected channels that can reach each order customer', function (): void {
    $whatsapp = ChannelAccount::query()->create([
        'workspace_id' => $this->workspace->id,
        'provider' => 'whatsapp',
        'name' => 'Main WhatsApp',
        'status' => ChannelAccountStatus::Connected,
        'provider_account_id' => 'waba-1',
        'provider_phone_id' => 'phone-1',
        'provider_display_id' => '+15550001111',
        'connected_at' => now(),
    ]);

    $email = ChannelAccount::query()->create([
        'workspace_id' => $this->workspace->id,
        'provider' => 'email',
        'name' => 'Support Email',
        'status' => ChannelAccountStatus::Connected,
        'provider_account_id' => 'email-1',
        'provider_display_id' => 'support@example.test',
        'connected_at' => now(),
    ]);

    $messenger = ChannelAccount::query()->create([
        'workspace_id' => $this->workspace->id,
        'provider' => 'messenger',
        'name' => 'Store Page',
        'status' => ChannelAccountStatus::Connected,
        'provider_account_id' => 'page-1',
        'provider_display_id' => 'Store Page',
        'connected_at' => now(),
    ]);

    // Inactive / unsupported channels that must NEVER appear
    ChannelAccount::query()->create([
        'workspace_id' => $this->workspace->id,
        'provider' => 'sms',
        'name' => 'Disconnected SMS',
        'status' => ChannelAccountStatus::Disconnected,
        'provider_account_id' => 'sms-1',
    ]);

    ChannelAccount::query()->create([
        'workspace_id' => $this->workspace->id,
        'provider' => 'email',
        'name' => 'Draft Email',
        'status' => ChannelAccountStatus::Draft,
        'provider_account_id' => 'email-2',
    ]);

    ChannelAccount::query()->create([
        'workspace_id' => $this->workspace->id,
        'provider' => 'tiktok',
        'name' => 'Brand TikTok',
        'status' => ChannelAccountStatus::Connected,
        'provider_account_id' => 'tt-1',
        'connected_at' => now(),
    ]);

    // Order 1: has phone + email, no Messenger identity
    $orderWithPhoneAndEmail = createTestOrder($this, ['name' => 'Alice', 'phone' => '+15551234567'], 'alice@example.test');

    // Order 2: has phone + Messenger identity, no email
    $orderWithMessenger = createTestOrder($this, ['name' => 'Bob', 'phone' => '+15557654321']);
    ContactProviderIdentity::query()->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $orderWithMessenger->contact_id,
        'channel_account_id' => $messenger->id,
        'provider' => 'messenger',
        'provider_contact_id' => 'psid-999',
    ]);

    // Order 3: guest order with no reachable phone/email/identity
    $unreachableOrder = Order::query()->create([
        'workspace_id' => $this->workspace->id,
        'source' => 'pos',
        'submission_reference' => (string) Str::uuid(),
        'payload_hash' => 'guest-hash',
        'number' => 'ORD-GUEST-01',
        'tracking_code' => 'TRK-GUEST-01',
        'status' => 'completed',
        'payment_state' => 'paid',
        'currency' => 'USD',
        'subtotal' => 20,
        'total' => 20,
        'customer_snapshot' => ['name' => 'Walk-in Guest'],
    ]);

    $service = app(OrderMessageService::class);
    $order1Channels = collect($service->availableChannelsForOrder($orderWithPhoneAndEmail->fresh(['contact.identities'])));
    $order2Channels = collect($service->availableChannelsForOrder($orderWithMessenger->fresh(['contact.identities'])));
    $order3Channels = collect($service->availableChannelsForOrder($unreachableOrder));

    expect($order1Channels->pluck('channel_account_id')->all())->toBe([$whatsapp->id, $email->id])
        ->and($order2Channels->pluck('channel_account_id')->all())->toBe([$whatsapp->id, $messenger->id])
        ->and($order3Channels->all())->toBe([]);

    // Verify orders index page renders trigger for all orders, marks channel availability accurately, and omits inactive channels
    $indexResponse = $this->get(route('user.commerce.orders.index'))->assertOk();
    $indexResponse->assertSee('data-order-message-trigger="'.$orderWithPhoneAndEmail->id.'"', false);
    $indexResponse->assertSee('data-order-message-trigger="'.$orderWithMessenger->id.'"', false);
    $indexResponse->assertSee('data-order-message-trigger="'.$unreachableOrder->id.'"', false);
    $indexResponse->assertSee('data-has-channels="1"', false);
    $indexResponse->assertSee('data-has-channels="0"', false);
    $indexResponse->assertSee('Main WhatsApp');
    $indexResponse->assertSee('Support Email');
    $indexResponse->assertDontSee('Disconnected SMS');
    $indexResponse->assertDontSee('Draft Email');
    $indexResponse->assertDontSee('Brand TikTok');

    // Verify order detail page also exposes direct messaging with only active channels
    $detailResponse = $this->get(route('user.commerce.orders.show', $orderWithPhoneAndEmail))->assertOk();
    $detailResponse->assertSee('Message customer');
    $detailResponse->assertSee('Main WhatsApp');
    $detailResponse->assertSee('Support Email');
    $detailResponse->assertDontSee('Disconnected SMS');
    $detailResponse->assertDontSee('Store Page');
});

it('sends a direct message on the owner-chosen WhatsApp channel from the orders page', function (): void {
    $whatsapp = ChannelAccount::query()->create([
        'workspace_id' => $this->workspace->id,
        'provider' => 'whatsapp',
        'name' => 'Main WhatsApp',
        'status' => ChannelAccountStatus::Connected,
        'provider_account_id' => 'waba-1',
        'provider_phone_id' => 'phone-1',
        'connected_at' => now(),
    ]);

    $order = createTestOrder($this, ['name' => 'Alice', 'phone' => '+15551234567']);

    $mockManager = Mockery::mock(ChannelManager::class);
    $mockManager->shouldReceive('sendMessage')
        ->once()
        ->withArgs(function (ChannelAccount $account, array $recipient, array $payload) use ($whatsapp): bool {
            return $account->id === $whatsapp->id
                && $recipient['to'] === '+15551234567'
                && $payload['type'] === 'text'
                && $payload['body'] === 'Your parcel is packed and ready.';
        })
        ->andReturn([
            'ok' => true,
            'provider' => 'whatsapp',
            'provider_message_id' => 'wamid.direct.1',
            'status' => 'sent',
            'response' => ['messages' => [['id' => 'wamid.direct.1']]],
        ]);

    $this->app->instance(ChannelManager::class, $mockManager);

    $response = $this->postJson(route('user.commerce.orders.message.send', $order), [
        'channel_account_id' => $whatsapp->id,
        'body' => 'Your parcel is packed and ready.',
    ]);

    $response->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('channel', 'whatsapp')
        ->assertJsonPath('recipient', '+15551234567');

    expect($response->json('conversation_url'))->not->toBeNull();

    $message = Message::query()->where('provider_message_id', 'wamid.direct.1')->first();
    expect($message)->not->toBeNull()
        ->and($message->direction)->toBe('outbound')
        ->and($message->body)->toBe('Your parcel is packed and ready.')
        ->and(data_get($message->payload, 'order_direct_message'))->toBeTrue()
        ->and(data_get($message->payload, 'order_id'))->toBe($order->id);

    expect($order->fresh()->conversation_id)->toBe($message->conversation_id);
});

it('sends a direct email when owner chooses an active email channel and validates subject', function (): void {
    $email = ChannelAccount::query()->create([
        'workspace_id' => $this->workspace->id,
        'provider' => 'email',
        'name' => 'Orders Mail',
        'status' => ChannelAccountStatus::Connected,
        'provider_account_id' => 'email-1',
        'provider_display_id' => 'orders@example.test',
        'connected_at' => now(),
    ]);

    $order = createTestOrder($this, ['name' => 'Alice', 'phone' => '+15551234567'], 'alice@example.test');

    // Missing subject must fail validation for email
    $this->postJson(route('user.commerce.orders.message.send', $order), [
        'channel_account_id' => $email->id,
        'body' => 'Please confirm your delivery window.',
    ])->assertUnprocessable()->assertJsonValidationErrors(['subject']);

    $mockManager = Mockery::mock(ChannelManager::class);
    $mockManager->shouldReceive('sendMessage')
        ->once()
        ->withArgs(function (ChannelAccount $account, array $recipient, array $payload) use ($email): bool {
            return $account->id === $email->id
                && $recipient['to'] === 'alice@example.test'
                && $payload['type'] === 'email'
                && $payload['subject'] === 'Delivery confirmation'
                && $payload['text_body'] === 'Please confirm your delivery window.';
        })
        ->andReturn([
            'ok' => true,
            'provider' => 'email',
            'provider_message_id' => 'mail-123',
            'status' => 'sent',
        ]);

    $this->app->instance(ChannelManager::class, $mockManager);

    $this->postJson(route('user.commerce.orders.message.send', $order), [
        'channel_account_id' => $email->id,
        'subject' => 'Delivery confirmation',
        'body' => 'Please confirm your delivery window.',
    ])->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('channel', 'email')
        ->assertJsonPath('recipient', 'alice@example.test')
        ->assertJsonPath('conversation_url', null);

    expect(Message::query()->where('provider', 'email')->where('contact_id', $order->contact_id)->count())->toBe(1);
});

it('sends via SMS or Telegram when active and reachable', function (): void {
    $sms = ChannelAccount::query()->create([
        'workspace_id' => $this->workspace->id,
        'provider' => 'sms',
        'name' => 'Twilio SMS',
        'status' => ChannelAccountStatus::Connected,
        'provider_account_id' => 'sms-1',
        'connected_at' => now(),
    ]);

    $telegram = ChannelAccount::query()->create([
        'workspace_id' => $this->workspace->id,
        'provider' => 'telegram',
        'name' => 'Store Bot',
        'status' => ChannelAccountStatus::Connected,
        'provider_account_id' => 'store_bot',
        'connected_at' => now(),
    ]);

    $order = createTestOrder($this, ['name' => 'Dana', 'phone' => '+15552223333']);
    ContactProviderIdentity::query()->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $order->contact_id,
        'channel_account_id' => $telegram->id,
        'provider' => 'telegram',
        'provider_contact_id' => 'tg-chat-77',
        'address' => 'tg-chat-77',
    ]);

    $mockManager = Mockery::mock(ChannelManager::class);
    $mockManager->shouldReceive('sendMessage')
        ->once()
        ->withArgs(fn (ChannelAccount $a, array $r, array $p): bool => $a->id === $sms->id && $r['to'] === '+15552223333' && $p['body'] === 'SMS update')
        ->andReturn(['ok' => true, 'provider' => 'sms', 'status' => 'sent']);
    $mockManager->shouldReceive('sendMessage')
        ->once()
        ->withArgs(fn (ChannelAccount $a, array $r, array $p): bool => $a->id === $telegram->id && $r['to'] === 'tg-chat-77' && $p['body'] === 'Telegram update')
        ->andReturn(['ok' => true, 'provider' => 'telegram', 'provider_message_id' => 'tg-msg-1', 'status' => 'sent']);

    $this->app->instance(ChannelManager::class, $mockManager);

    $this->postJson(route('user.commerce.orders.message.send', $order), [
        'channel_account_id' => $sms->id,
        'body' => 'SMS update',
    ])->assertOk()->assertJsonPath('channel', 'sms');

    $this->postJson(route('user.commerce.orders.message.send', $order), [
        'channel_account_id' => $telegram->id,
        'body' => 'Telegram update',
    ])->assertOk()->assertJsonPath('channel', 'telegram');
});

it('rejects inactive channels, foreign channels, and unreachable channels', function (): void {
    $disconnectedWhatsapp = ChannelAccount::query()->create([
        'workspace_id' => $this->workspace->id,
        'provider' => 'whatsapp',
        'name' => 'Offline WhatsApp',
        'status' => ChannelAccountStatus::Disconnected,
        'provider_account_id' => 'waba-off',
    ]);

    $connectedEmail = ChannelAccount::query()->create([
        'workspace_id' => $this->workspace->id,
        'provider' => 'email',
        'name' => 'Active Email',
        'status' => ChannelAccountStatus::Connected,
        'provider_account_id' => 'email-on',
        'connected_at' => now(),
    ]);

    $otherOwner = User::factory()->create();
    $otherWorkspace = app(WorkspaceResolver::class)->current($otherOwner);
    $foreignChannel = ChannelAccount::query()->create([
        'workspace_id' => $otherWorkspace->id,
        'provider' => 'whatsapp',
        'name' => 'Other Store WA',
        'status' => ChannelAccountStatus::Connected,
        'provider_account_id' => 'waba-other',
        'connected_at' => now(),
    ]);

    // Order has phone ONLY (no email)
    $order = createTestOrder($this, ['name' => 'Evan', 'phone' => '+15554445555']);

    $this->postJson(route('user.commerce.orders.message.send', $order), [
        'channel_account_id' => $disconnectedWhatsapp->id,
        'body' => 'Hello',
    ])->assertUnprocessable()->assertJsonValidationErrors(['channel_account_id']);

    $this->postJson(route('user.commerce.orders.message.send', $order), [
        'channel_account_id' => $foreignChannel->id,
        'body' => 'Hello',
    ])->assertUnprocessable()->assertJsonValidationErrors(['channel_account_id']);

    $this->postJson(route('user.commerce.orders.message.send', $order), [
        'channel_account_id' => $connectedEmail->id,
        'subject' => 'Order update',
        'body' => 'Hello',
    ])->assertUnprocessable()->assertJsonValidationErrors(['channel_account_id']);
});

it('returns clean error feedback when provider delivery fails and supports standard form redirect', function (): void {
    $whatsapp = ChannelAccount::query()->create([
        'workspace_id' => $this->workspace->id,
        'provider' => 'whatsapp',
        'name' => 'Main WhatsApp',
        'status' => ChannelAccountStatus::Connected,
        'provider_account_id' => 'waba-1',
        'provider_phone_id' => 'phone-1',
        'connected_at' => now(),
    ]);

    $order = createTestOrder($this, ['name' => 'Frank', 'phone' => '+15556667777']);

    $mockManager = Mockery::mock(ChannelManager::class);
    $mockManager->shouldReceive('sendMessage')
        ->once()
        ->andReturn([
            'ok' => false,
            'provider' => 'whatsapp',
            'status' => 'failed',
            'error' => 'The 24-hour reply window has closed.',
            'error_detail' => 'Send an approved template message to reopen the chat.',
        ]);

    $this->app->instance(ChannelManager::class, $mockManager);

    $this->postJson(route('user.commerce.orders.message.send', $order), [
        'channel_account_id' => $whatsapp->id,
        'body' => 'Checking on your delivery.',
    ])->assertUnprocessable()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error', 'The 24-hour reply window has closed.')
        ->assertJsonPath('error_detail', 'Send an approved template message to reopen the chat.');

    expect(Message::query()->where('contact_id', $order->contact_id)->where('status', 'failed')->count())->toBe(1);

    // Non-JSON form submit redirects back with flash success
    $mockManagerSuccess = Mockery::mock(ChannelManager::class);
    $mockManagerSuccess->shouldReceive('sendMessage')
        ->once()
        ->andReturn(['ok' => true, 'provider' => 'whatsapp', 'provider_message_id' => 'wamid.ok.2', 'status' => 'sent']);

    $this->app->instance(ChannelManager::class, $mockManagerSuccess);

    $this->from(route('user.commerce.orders.index'))
        ->post(route('user.commerce.orders.message.send', $order), [
            'channel_account_id' => $whatsapp->id,
            'body' => 'Delivered!',
        ])
        ->assertRedirect(route('user.commerce.orders.index'))
        ->assertSessionHas('success');
});

it('prevents users from other workspaces from messaging an order', function (): void {
    $whatsapp = ChannelAccount::query()->create([
        'workspace_id' => $this->workspace->id,
        'provider' => 'whatsapp',
        'name' => 'Main WhatsApp',
        'status' => ChannelAccountStatus::Connected,
        'provider_account_id' => 'waba-1',
        'connected_at' => now(),
    ]);

    $order = createTestOrder($this, ['name' => 'Grace', 'phone' => '+15558889999']);

    $otherUser = User::factory()->create();
    app(WorkspaceResolver::class)->current($otherUser);

    $this->actingAs($otherUser)
        ->postJson(route('user.commerce.orders.message.send', $order), [
            'channel_account_id' => $whatsapp->id,
            'body' => 'Unauthorized',
        ])
        ->assertNotFound();
});
