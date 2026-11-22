<?php

use App\Modules\Commerce\Jobs\NotifyOrderEvent;
use App\Modules\Commerce\Jobs\SendOrderReviewRequest;
use App\Modules\Commerce\Mail\OrderReviewRequestMail;
use App\Modules\Commerce\Models\MobileCustomerDevice;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderEvent;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\OrderReviewRequestService;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Models\ProductReview;
use App\Modules\Commerce\Storefront\Models\StoreDomain;
use App\Modules\Commerce\Storefront\Services\StorePushService;
use App\Modules\Contacts\Enums\ContactOptInStatus;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\NotificationTemplates\Notifications\MobileAlert;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Kreait\Firebase\Contract\Messaging;
use Tests\Support\StorefrontDatabase;

beforeEach(function (): void {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
    DB::purge('sqlite');
    StorefrontDatabase::migrate();
    Queue::fake();
    Mail::fake();
    Notification::fake();
    $push = Mockery::mock(StorePushService::class)->makePartial();
    $push->shouldReceive('messaging')->andReturn(Mockery::mock(Messaging::class));
    $this->app->instance(StorePushService::class, $push);

    $this->store = Workspace::query()->create(['name' => 'Fashion Fiest', 'slug' => 'one', 'status' => 'active']);
    StoreDomain::query()->create(['workspace_id' => $this->store->id, 'host' => 'one.localhost', 'is_primary' => true, 'status' => 'active', 'verification_token' => 'test', 'type' => 'system']);
    $this->contact = Contact::withoutEvents(fn () => Contact::query()->create(['workspace_id' => $this->store->id, 'name' => 'Customer', 'phone' => '+8801876150613', 'source' => 'website', 'opt_in_status' => 'unknown']));
    $this->product = Product::query()->create(['workspace_id' => $this->store->id, 'name' => 'Shirt', 'slug' => 'shirt', 'sku' => 'shirt', 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'retail', 'single_piece_price' => 20]);
    $this->variant = ProductVariant::query()->create(['workspace_id' => $this->store->id, 'product_id' => $this->product->id, 'sku' => 'shirt-m', 'meta_retailer_id' => 'shirt-m', 'size' => 'M', 'price' => 20, 'stock_quantity' => 5, 'status' => 'active']);

    $this->order = Order::query()->create([
        'workspace_id' => $this->store->id, 'contact_id' => $this->contact->id, 'source' => 'storefront',
        'submission_reference' => (string) Str::uuid(), 'payload_hash' => 'x', 'number' => 'ORD-00007', 'tracking_code' => 'TRK-7',
        'status' => 'completed', 'payment_state' => 'paid', 'currency' => 'USD', 'subtotal' => 20, 'shipping_amount' => 0, 'total' => 20,
        'delivered_at' => now(), 'customer_reference' => 'storefront:1',
        'customer_snapshot' => ['name' => 'Test Customer', 'phone' => '+8801876150613'],
    ]);
    $this->order->items()->create(['workspace_id' => $this->store->id, 'variant_id' => $this->variant->id, 'retailer_id' => 'shirt-m', 'sku' => 'shirt-m', 'product_name' => 'Shirt', 'quantity' => 1, 'unit_price' => 20, 'line_total' => 20]);
});
afterEach(function (): void {
    DB::disconnect('sqlite');
});

function reviewRequestWhatsApp(object $test, ?DateTimeInterface $sessionExpiresAt): void
{
    StoreOrderSetting::forWorkspace($test->store->id)->update(['whatsapp_notifications' => true]);
    $account = ChannelAccount::query()->create(['workspace_id' => $test->store->id, 'provider' => 'whatsapp', 'name' => 'WhatsApp', 'status' => ChannelAccountStatus::Connected, 'connected_at' => now()]);
    Conversation::query()->create(['workspace_id' => $test->store->id, 'channel_account_id' => $account->id, 'provider' => 'whatsapp', 'contact_id' => $test->contact->id, 'status' => 'open', 'session_expires_at' => $sessionExpiresAt]);
    $test->order->update(['channel_account_id' => $account->id]);
}

function reviewRequestEvent(object $test, string $key = 'completed'): OrderEvent
{
    return OrderEvent::query()->create(['order_id' => $test->order->id, 'key' => $key, 'label' => 'Delivered', 'occurred_at' => now()]);
}

it('queues a delayed review request when an order is completed', function (): void {
    StoreOrderSetting::forWorkspace($this->store->id)->update(['review_request_delay_hours' => 6]);
    $event = reviewRequestEvent($this);

    app()->call([new NotifyOrderEvent($event->id), 'handle']);

    Queue::assertPushed(SendOrderReviewRequest::class, fn (SendOrderReviewRequest $job): bool => $job->orderId === $this->order->id
        && $job->delay->between(now()->addHours(6)->subMinute(), now()->addHours(6)->addMinute()));
});

it('does not queue a review request for other events or when switched off', function (): void {
    app()->call([new NotifyOrderEvent(reviewRequestEvent($this, 'shipped')->id), 'handle']);
    Queue::assertNotPushed(SendOrderReviewRequest::class);

    StoreOrderSetting::forWorkspace($this->store->id)->update(['review_request_enabled' => false]);
    app()->call([new NotifyOrderEvent(reviewRequestEvent($this)->id), 'handle']);
    Queue::assertNotPushed(SendOrderReviewRequest::class);
});

it('sends the rating link on WhatsApp inside the 24 hour window and only once', function (): void {
    reviewRequestWhatsApp($this, now()->addHours(5));
    $this->mock(ChannelManager::class, function ($mock): void {
        $mock->shouldReceive('sendMessage')->once()->withArgs(function ($account, $to, $payload): bool {
            return $to === ['to' => '+8801876150613']
                && $payload['type'] === 'text'
                && str_contains($payload['body'], 'ORD-00007')
                && str_contains($payload['body'], 'Shirt')
                && str_contains($payload['body'], '/account/orders/'.$this->order->submission_reference);
        })->andReturn(['ok' => true, 'provider_message_id' => 'wamid.1']);
    });

    $service = app(OrderReviewRequestService::class);
    $first = $service->send($this->order->fresh());
    $second = $service->send($this->order->fresh());

    expect($first)->toBe(OrderReviewRequestService::SENT)
        ->and($second)->toBe(OrderReviewRequestService::SKIPPED_ALREADY_SENT)
        ->and($this->order->fresh()->review_request_sent_at)->not->toBeNull();
});

it('does not message customers who opted out on WhatsApp', function (): void {
    reviewRequestWhatsApp($this, now()->addHours(5));
    $this->contact->forceFill(['opt_in_status' => ContactOptInStatus::Unsubscribed])->save();
    $this->mock(ChannelManager::class, fn ($mock) => $mock->shouldNotReceive('sendMessage'));

    $result = app(OrderReviewRequestService::class)->send($this->order->fresh());

    expect($result)->toBe(OrderReviewRequestService::SKIPPED_NO_CHANNEL)
        ->and($this->order->fresh()->review_request_sent_at)->toBeNull();
});

it('sends the rating request by email and app notification', function (): void {
    $this->order->update(['customer_snapshot' => ['name' => 'Test Customer', 'phone' => '+8801876150613', 'email' => 'buyer@example.test']]);
    $device = MobileCustomerDevice::query()->create(['workspace_id' => $this->store->id, 'customer_reference' => 'storefront:1', 'token' => 'tok', 'platform' => 'android']);

    $result = app(OrderReviewRequestService::class)->send($this->order->fresh());

    expect($result)->toBe(OrderReviewRequestService::SENT);
    Mail::assertQueued(OrderReviewRequestMail::class, fn (OrderReviewRequestMail $mail): bool => $mail->hasTo('buyer@example.test') && str_contains($mail->reviewUrl, '/account/orders/'.$this->order->submission_reference));
    Notification::assertSentTo($device, MobileAlert::class);
});

it('skips orders that are not completed, switched off, or already fully rated', function (): void {
    $this->order->update(['customer_snapshot' => ['name' => 'Test Customer', 'email' => 'buyer@example.test']]);
    $service = app(OrderReviewRequestService::class);

    $this->order->update(['status' => 'shipped']);
    expect($service->send($this->order->fresh()))->toBe(OrderReviewRequestService::SKIPPED_NOT_COMPLETED);

    $this->order->update(['status' => 'completed']);
    StoreOrderSetting::forWorkspace($this->store->id)->update(['review_request_enabled' => false]);
    expect($service->send($this->order->fresh()))->toBe(OrderReviewRequestService::SKIPPED_DISABLED);

    StoreOrderSetting::forWorkspace($this->store->id)->update(['review_request_enabled' => true]);
    $customer = Customer::query()->firstOrCreate(['workspace_id' => $this->store->id, 'contact_id' => $this->contact->id]);
    ProductReview::query()->create(['workspace_id' => $this->store->id, 'customer_id' => $customer->id, 'product_id' => $this->product->id, 'rating' => 5, 'body' => 'Great', 'status' => 'approved']);
    expect($service->send($this->order->fresh()))->toBe(OrderReviewRequestService::SKIPPED_NOTHING_TO_RATE);

    Mail::assertNothingQueued();
});
