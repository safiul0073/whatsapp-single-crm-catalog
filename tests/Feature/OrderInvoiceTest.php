<?php

use App\Modules\Commerce\Jobs\NotifyOrderEvent;
use App\Modules\Commerce\Jobs\SendOrderInvoiceWhatsApp;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderEvent;
use App\Modules\Commerce\Models\OrderPayment;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\OrderInvoiceWhatsAppService;
use App\Modules\Commerce\Storefront\Models\StoreDomain;
use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use App\Modules\Commerce\Storefront\Services\InvoiceService;
use App\Modules\Contacts\Enums\ContactOptInStatus;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\StorefrontDatabase;

beforeEach(function (): void {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'app.url' => 'https://app.example.test']);
    DB::purge('sqlite');
    StorefrontDatabase::migrate();
    Queue::fake();

    $this->store = Workspace::query()->create(['name' => 'Fashion Fiest', 'slug' => 'one', 'status' => 'active']);
    $this->other = Workspace::query()->create(['name' => 'Other', 'slug' => 'two', 'status' => 'active']);
    foreach ([$this->store, $this->other] as $w) {
        StoreDomain::query()->create(['workspace_id' => $w->id, 'host' => $w->slug.'.localhost', 'is_primary' => true, 'status' => 'active', 'verification_token' => 'test', 'type' => 'system']);
    }
    StorefrontSetting::query()->updateOrCreate(['workspace_id' => $this->store->id], ['config' => ['name' => 'Fashion Fiest', 'address' => 'House 12, Road 5, Uttara, Dhaka', 'contact_phone' => '+8801700000000', 'contact_email' => 'sales@fashionfiest.test']]);
    $this->contact = Contact::withoutEvents(fn () => Contact::query()->create(['workspace_id' => $this->store->id, 'name' => 'Customer', 'phone' => '+8801876150613', 'source' => 'website', 'opt_in_status' => 'unknown']));

    $makeOrder = fn (Workspace $workspace, string $number): Order => Order::query()->create([
        'workspace_id' => $workspace->id, 'contact_id' => $workspace->is($this->store) ? $this->contact->id : null, 'source' => 'storefront',
        'submission_reference' => (string) Str::uuid(), 'payload_hash' => 'x', 'number' => $number, 'tracking_code' => 'TRK-'.$number,
        'status' => 'shipped', 'payment_state' => 'paid', 'currency' => 'USD', 'subtotal' => 40, 'shipping_amount' => 5, 'total' => 45,
        'customer_snapshot' => ['name' => 'Test Customer', 'phone' => '+8801876150613'],
        'shipping_address' => ['name' => 'Test Customer', 'line1' => 'Uttora', 'city' => 'Dhaka', 'postal_code' => '1212', 'country' => 'BD'],
    ]);
    $this->order = $makeOrder($this->store, 'ORD-00007');
    $this->foreignOrder = $makeOrder($this->other, 'ORD-00001');
    OrderPayment::query()->create(['workspace_id' => $this->store->id, 'order_id' => $this->order->id, 'amount' => 45, 'currency' => 'USD', 'method' => 'cash', 'recorded_at' => now(), 'submission_reference' => (string) Str::uuid(), 'payload_hash' => 'p']);
});
afterEach(function (): void {
    DB::disconnect('sqlite');
});

it('gives an order a stable invoice number and private link', function (): void {
    $this->order->ensureInvoice();
    $token = $this->order->invoice_token;

    expect($this->order->invoice_number)->toBe('INV-ORD-00007')
        ->and($token)->toHaveLength(40)
        ->and($this->order->invoiceUrl())->toBe('https://app.example.test/invoices/'.$token)
        ->and($this->order->invoiceUrl(download: true))->toEndWith('?download=1');

    expect($this->order->fresh()->ensureInvoice()->invoice_token)->toBe($token);
    expect($this->order->regenerateInvoiceToken()->invoice_token)->not->toBe($token);
});

it('serves the invoice pdf on the private link without login', function (): void {
    $this->order->ensureInvoice();

    $response = $this->get('/invoices/'.$this->order->invoice_token)->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('inline');

    $this->get('/invoices/'.$this->order->invoice_token.'?download=1')
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="INV-ORD-00007.pdf"');
});

it('does not reveal invoices for unknown or malformed tokens', function (): void {
    $this->get('/invoices/'.Str::random(40))->assertNotFound();
    $this->get('/invoices/short')->assertNotFound();
});

it('only returns the invoice that belongs to the token', function (): void {
    $this->order->ensureInvoice();
    $this->foreignOrder->ensureInvoice();

    $pdf = $this->get('/invoices/'.$this->foreignOrder->invoice_token)->assertOk()->getContent();

    expect($pdf)->toContain('INV-ORD-00001')->not->toContain('INV-ORD-00007')->not->toContain('Uttara, Dhaka');
});

it('stops an old link from working after it is regenerated', function (): void {
    $old = $this->order->ensureInvoice()->invoice_token;
    $this->order->regenerateInvoiceToken();

    $this->get('/invoices/'.$old)->assertNotFound();
    $this->get('/invoices/'.$this->order->invoice_token)->assertOk();
});

it('prints the owner details, invoice number, ship to and payment totals on the invoice', function (): void {
    $pdf = app(InvoiceService::class)->pdf($this->order->fresh());

    expect($pdf)->toStartWith('%PDF-1.4')
        ->and($pdf)->toContain('INVOICE')
        ->and($pdf)->toContain('INV-ORD-00007')
        ->and($pdf)->toContain('Fashion Fiest')
        ->and($pdf)->toContain('House 12, Road 5, Uttara, Dhaka')
        ->and($pdf)->toContain('sales@fashionfiest.test')
        ->and($pdf)->toContain('BILL TO')
        ->and($pdf)->toContain('SHIP TO')
        ->and($pdf)->toContain('Paid:')
        ->and($pdf)->toContain('Balance due:')
        ->and($pdf)->toContain('https://app.example.test/invoices/');
});

it('embeds the store logo when one is configured', function (): void {
    if (! extension_loaded('gd')) {
        $this->markTestSkipped('GD is required to embed the logo.');
    }
    $path = public_path('storage/test-logo.png');
    @mkdir(dirname($path), 0777, true);
    $image = imagecreatetruecolor(300, 90);
    imagefill($image, 0, 0, imagecolorallocate($image, 10, 90, 200));
    imagepng($image, $path);
    imagedestroy($image);

    try {
        StorefrontSetting::query()->updateOrCreate(['workspace_id' => $this->store->id], ['config' => ['name' => 'Fashion Fiest', 'logo' => 'test-logo.png']]);
        $withLogo = app(InvoiceService::class)->pdf($this->order->fresh());
        StorefrontSetting::query()->updateOrCreate(['workspace_id' => $this->store->id], ['config' => ['name' => 'Fashion Fiest']]);
        $withoutLogo = app(InvoiceService::class)->pdf($this->order->fresh());
    } finally {
        @unlink($path);
    }

    expect($withLogo)->toContain('/ImLogo')->and($withoutLogo)->not->toContain('/ImLogo');
});

it('queues the invoice when an order ships', function (): void {
    $event = OrderEvent::query()->create(['order_id' => $this->order->id, 'key' => 'shipped', 'label' => 'Shipped / in transit', 'occurred_at' => now()]);

    app()->call([new NotifyOrderEvent($event->id), 'handle']);

    Queue::assertPushed(SendOrderInvoiceWhatsApp::class, fn (SendOrderInvoiceWhatsApp $job): bool => $job->orderId === $this->order->id);
});

it('does not queue the invoice for other order events', function (): void {
    $event = OrderEvent::query()->create(['order_id' => $this->order->id, 'key' => 'paid', 'label' => 'Payment confirmed', 'occurred_at' => now()]);

    app()->call([new NotifyOrderEvent($event->id), 'handle']);

    Queue::assertNotPushed(SendOrderInvoiceWhatsApp::class);
});

function invoiceWhatsAppFixture(object $test, ?DateTimeInterface $sessionExpiresAt): ChannelAccount
{
    StoreOrderSetting::forWorkspace($test->store->id)->update(['whatsapp_notifications' => true]);
    $account = ChannelAccount::query()->create(['workspace_id' => $test->store->id, 'provider' => 'whatsapp', 'name' => 'WhatsApp', 'status' => ChannelAccountStatus::Connected, 'connected_at' => now()]);
    Conversation::query()->create(['workspace_id' => $test->store->id, 'channel_account_id' => $account->id, 'provider' => 'whatsapp', 'contact_id' => $test->contact->id, 'status' => 'open', 'session_expires_at' => $sessionExpiresAt]);
    $test->order->update(['channel_account_id' => $account->id]);

    return $account;
}

it('sends the invoice document on WhatsApp inside the 24 hour window and only once', function (): void {
    invoiceWhatsAppFixture($this, now()->addHours(5));
    $this->mock(ChannelManager::class, function ($mock): void {
        $mock->shouldReceive('sendMessage')->once()->withArgs(function ($account, $to, $payload): bool {
            return $to === ['to' => '+8801876150613']
                && $payload['type'] === 'document'
                && $payload['filename'] === 'INV-ORD-00007.pdf'
                && str_contains($payload['url'], '/invoices/')
                && str_ends_with($payload['url'], '?download=1');
        })->andReturn(['ok' => true, 'provider_message_id' => 'wamid.1']);
    });

    $service = app(OrderInvoiceWhatsAppService::class);
    $first = $service->send($this->order->fresh()->load('contact'));
    $second = $service->send($this->order->fresh()->load('contact'));

    expect($first['status'])->toBe(OrderInvoiceWhatsAppService::SENT)
        ->and($second['status'])->toBe(OrderInvoiceWhatsAppService::SKIPPED_ALREADY_SENT)
        ->and($this->order->fresh()->invoice_sent_at)->not->toBeNull();
});

it('lets the owner resend an invoice that was already sent', function (): void {
    invoiceWhatsAppFixture($this, now()->addHours(5));
    $this->order->forceFill(['invoice_sent_at' => now()->subDay()])->save();
    $this->mock(ChannelManager::class, function ($mock): void {
        $mock->shouldReceive('sendMessage')->once()->andReturn(['ok' => true, 'provider_message_id' => 'wamid.2']);
    });

    $result = app(OrderInvoiceWhatsAppService::class)->send($this->order->fresh()->load('contact'), manual: true);

    expect($result['status'])->toBe(OrderInvoiceWhatsAppService::SENT);
});

it('skips the invoice outside the 24 hour window when no template is set up', function (): void {
    invoiceWhatsAppFixture($this, now()->subHour());
    $this->mock(ChannelManager::class, fn ($mock) => $mock->shouldNotReceive('sendMessage'));

    $result = app(OrderInvoiceWhatsAppService::class)->send($this->order->fresh()->load('contact'));

    expect($result['status'])->toBe(OrderInvoiceWhatsAppService::SKIPPED_WINDOW)
        ->and($this->order->fresh()->invoice_sent_at)->toBeNull();
});

it('does not message customers who opted out or are blocked', function (): void {
    invoiceWhatsAppFixture($this, now()->addHours(5));
    $this->contact->forceFill(['opt_in_status' => ContactOptInStatus::Unsubscribed])->save();
    $this->mock(ChannelManager::class, fn ($mock) => $mock->shouldNotReceive('sendMessage'));

    $result = app(OrderInvoiceWhatsAppService::class)->send($this->order->fresh()->load('contact'), manual: true);

    expect($result['status'])->toBe(OrderInvoiceWhatsAppService::SKIPPED_CONTACT);
});

it('keeps automatic sending off when WhatsApp order notifications are disabled', function (): void {
    invoiceWhatsAppFixture($this, now()->addHours(5));
    StoreOrderSetting::forWorkspace($this->store->id)->update(['whatsapp_notifications' => false]);
    $this->mock(ChannelManager::class, fn ($mock) => $mock->shouldNotReceive('sendMessage'));

    $result = app(OrderInvoiceWhatsAppService::class)->send($this->order->fresh()->load('contact'));

    expect($result['status'])->toBe(OrderInvoiceWhatsAppService::SKIPPED_DISABLED);
});
