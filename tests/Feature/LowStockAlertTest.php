<?php

use App\Models\User;
use App\Modules\Commerce\Jobs\NotifyLowStock;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\NotificationTemplates\Notifications\MobileAlert;
use App\Modules\SystemNotifications\Models\SystemNotification;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\Support\StorefrontDatabase;

beforeEach(function (): void {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
    DB::purge('sqlite');
    StorefrontDatabase::migrate();
    (require app_path('Modules/SystemNotifications/Database/Migrations/2026_03_29_000001_create_system_notifications_table.php'))->up();
    Queue::fake();
    Notification::fake();

    Schema::table('users', fn (Blueprint $table) => $table->softDeletes());
    $this->owner = User::query()->findOrFail(DB::table('users')->insertGetId(['name' => 'Owner']));
    $this->store = Workspace::query()->create(['name' => 'Fashion Fiest', 'slug' => 'one', 'status' => 'active', 'owner_id' => $this->owner->id]);
    StoreOrderSetting::forWorkspace($this->store->id)->update(['low_stock_threshold' => 3]);
    $this->product = Product::query()->create(['workspace_id' => $this->store->id, 'name' => 'Tracksuit', 'slug' => 'tracksuit', 'sku' => 'tracksuit', 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'retail', 'single_piece_price' => 20]);
    $this->variant = ProductVariant::query()->create(['workspace_id' => $this->store->id, 'product_id' => $this->product->id, 'sku' => 'ts-s', 'meta_retailer_id' => 'ts-s', 'size' => 'S', 'attributes' => ['size' => 'S', 'color' => 'Red'], 'price' => 20, 'stock_quantity' => 5, 'status' => 'active']);
});
afterEach(function (): void {
    DB::disconnect('sqlite');
});

it('alerts once when a variant drops to the threshold', function (): void {
    $this->variant->decrement('stock_quantity', 2);
    $this->variant->decrement('stock_quantity', 1);

    Queue::assertPushed(NotifyLowStock::class, 1);
    expect($this->variant->fresh()->low_stock_notified_at)->not->toBeNull();
});

it('does not alert while stock stays above the threshold, on creation or when disabled', function (): void {
    $this->variant->decrement('stock_quantity', 1);
    ProductVariant::query()->create(['workspace_id' => $this->store->id, 'product_id' => $this->product->id, 'sku' => 'ts-m', 'meta_retailer_id' => 'ts-m', 'size' => 'M', 'price' => 20, 'stock_quantity' => 0, 'status' => 'active']);
    StoreOrderSetting::forWorkspace($this->store->id)->update(['low_stock_alerts_enabled' => false]);
    $this->variant->decrement('stock_quantity', 3);

    Queue::assertNotPushed(NotifyLowStock::class);
});

it('alerts again after the variant is restocked above the threshold', function (): void {
    $this->variant->decrement('stock_quantity', 3);
    $this->variant->increment('stock_quantity', 10);
    expect($this->variant->fresh()->low_stock_notified_at)->toBeNull();

    $this->variant->fresh()->decrement('stock_quantity', 11);

    expect($this->variant->fresh()->low_stock_notified_at)->not->toBeNull();
    Queue::assertPushed(NotifyLowStock::class, 1);
});

it('notifies the owner in the dashboard, by push and on WhatsApp', function (): void {
    $channel = ChannelAccount::query()->create(['workspace_id' => $this->store->id, 'provider' => 'whatsapp', 'name' => 'Sales', 'status' => 'connected', 'provider_account_id' => 'waba', 'provider_phone_id' => 'phone']);
    StoreOrderSetting::forWorkspace($this->store->id)->update(['owner_whatsapp_number' => '+8801711223344', 'whatsapp_channel_id' => $channel->id]);
    $ownerContact = Contact::withoutEvents(fn () => Contact::query()->create(['workspace_id' => $this->store->id, 'phone' => '+8801711223344', 'name' => 'Owner']));
    Conversation::query()->create(['workspace_id' => $this->store->id, 'channel_account_id' => $channel->id, 'provider' => 'whatsapp', 'contact_id' => $ownerContact->id, 'session_expires_at' => now()->addHours(5)]);
    $this->variant->decrement('stock_quantity', 4);
    $this->mock(ChannelManager::class)->shouldReceive('sendMessage')->once()
        ->withArgs(fn ($account, $recipient, $payload) => $recipient['to'] === '+8801711223344' && $payload['type'] === 'text' && str_contains($payload['body'], 'Red / S: 1'))
        ->andReturn(['ok' => true]);

    app()->call([new NotifyLowStock($this->product->id), 'handle']);

    expect(SystemNotification::query()->where('notifiable_id', $this->owner->id)->count())->toBe(1);
    Notification::assertSentTo($this->owner, MobileAlert::class);
});

it('skips WhatsApp when no owner number is set', function (): void {
    $this->variant->decrement('stock_quantity', 4);
    $this->mock(ChannelManager::class)->shouldNotReceive('sendMessage');

    app()->call([new NotifyLowStock($this->product->id), 'handle']);

    Notification::assertSentTo($this->owner, MobileAlert::class);
});
