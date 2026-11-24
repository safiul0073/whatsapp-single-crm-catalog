<?php

use App\Models\User;
use App\Modules\Commerce\Jobs\NotifyOrderEvent;
use App\Modules\Commerce\Jobs\SendOrderTemplateFallback;
use App\Modules\Commerce\Jobs\SendPackingSlipWhatsApp;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\UnifiedOrderService;
use App\Modules\Commerce\Storefront\Jobs\RevalidateStorefront;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use App\Modules\MessageTemplates\Models\MessageTemplateSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * @return array{user: User, workspace: mixed, order: Order, account: ChannelAccount}
 */
function packingSlipContext(): array
{
    $user = User::factory()->create();
    $workspace = app(WorkspaceResolver::class)->current($user);
    StoreOrderSetting::forWorkspace($workspace->id);
    $product = Product::query()->create(['workspace_id' => $workspace->id, 'name' => 'Cotton shirt', 'slug' => 'shirt-'.$workspace->id, 'sku' => 'shirt-'.$workspace->id, 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'both', 'single_piece_price' => '10.00', 'wholesale_price' => '6.00', 'ws_enabled' => true, 'ws_main_moq' => 12, 'ws_color_moq' => 6, 'ws_min_sizes' => 3, 'ws_ratio_multiplier' => 1]);
    $color = $product->colors()->create(['workspace_id' => $workspace->id, 'name' => 'Red', 'hex_code' => '#ff0000']);
    $product->update(['ws_size_ratios' => [$color->id => ['S' => 2, 'M' => 3, 'L' => 1]]]);
    foreach (['S', 'M', 'L'] as $size) {
        $product->variants()->create(['workspace_id' => $workspace->id, 'color_id' => $color->id, 'sku' => $workspace->id.'-'.$size, 'meta_retailer_id' => $workspace->id.'-'.$size, 'size' => $size, 'price' => 10, 'stock_quantity' => 100, 'status' => 'active']);
    }
    $order = app(UnifiedOrderService::class)->create($workspace, ['submission_reference' => (string) Str::uuid(), 'source' => 'manual', 'customer' => ['name' => 'Buyer', 'phone' => '+15555550100'], 'shipping_address' => ['name' => 'Buyer', 'line1' => '1 Main Street', 'city' => 'Boston', 'country' => 'US'], 'groups' => [['product_id' => $product->id, 'mode' => 'wholesale', 'color_id' => $color->id, 'box_count' => 3]]]);
    $account = ChannelAccount::query()->create(['workspace_id' => $workspace->id, 'provider' => 'whatsapp', 'name' => 'WhatsApp', 'status' => ChannelAccountStatus::Connected, 'connected_at' => now(), 'provider_account_id' => 'waba-store']);
    StoreOrderSetting::forWorkspace($workspace->id)->update(['whatsapp_channel_id' => $account->id, 'packing_slip_recipients' => [['name' => 'Packer Rahim', 'phone' => '+8801711223344'], ['name' => 'Courier', 'phone' => '+8801811223344']]]);

    return compact('user', 'workspace', 'order', 'account');
}

beforeEach(function (): void {
    Queue::fake([NotifyOrderEvent::class, RevalidateStorefront::class, SendOrderTemplateFallback::class, SendPackingSlipWhatsApp::class]);
});

it('sends the packing slip pdf free-form to every picked recipient and the extra number', function (): void {
    $c = packingSlipContext();
    $sent = [];
    $this->mock(ChannelManager::class, function ($mock) use (&$sent): void {
        $mock->shouldReceive('sendMessage')->times(3)->andReturnUsing(function ($account, $to, $payload) use (&$sent): array {
            $sent[] = [$to['to'], $payload];

            return ['ok' => true, 'provider_message_id' => 'wamid.'.count($sent)];
        });
    });

    $this->actingAs($c['user'])
        ->post(route('user.commerce.orders.packing-slip.share', $c['order']), ['recipients' => ['+8801711223344', '+8801811223344'], 'extra_phone' => '+8801911223344'])
        ->assertRedirect()
        ->assertSessionHas('success', 'Packing slip sent to 3 people on WhatsApp.');

    expect(array_column($sent, 0))->toBe(['+8801711223344', '+8801811223344', '+8801911223344'])
        ->and($sent[0][1]['type'])->toBe('document')
        ->and($sent[0][1]['filename'])->toBe($c['order']->number.'-packing-slip.pdf')
        ->and($sent[0][1]['url'])->toContain('/packing-slips/'.$c['order']->id)
        ->and(Contact::query()->where('workspace_id', $c['workspace']->id)->where('phone', '+8801711223344')->value('name'))->toBe('Packer Rahim')
        ->and(Message::query()->where('payload->packing_slip_order_id', $c['order']->id)->count())->toBe(3);
});

it('warns when the recipient has not messaged in 24 hours and no template is selected', function (): void {
    $c = packingSlipContext();
    $this->mock(ChannelManager::class, fn ($mock) => $mock->shouldReceive('sendMessage')->once()
        ->andReturn(['ok' => false, 'error_code' => '131047', 'error' => 'The 24-hour reply window has closed']));

    $this->actingAs($c['user'])
        ->post(route('user.commerce.orders.packing-slip.share', $c['order']), ['recipients' => ['+8801711223344']])
        ->assertSessionHas('warning');

    Queue::assertNotPushed(SendOrderTemplateFallback::class);
});

it('queues the template fallback for a blocked free-form packing slip when a template is approved', function (): void {
    $c = packingSlipContext();
    $template = MessageTemplate::query()->create(['workspace_id' => $c['workspace']->id, 'provider' => 'whatsapp', 'name' => 'order_update', 'language' => 'en_US', 'category' => 'utility', 'status' => 'approved', 'components' => [['type' => 'BODY', 'text' => 'Here is an update on your order: {{1}}.']]]);
    MessageTemplateSubmission::query()->create(['workspace_id' => $c['workspace']->id, 'message_template_id' => $template->id, 'channel_account_id' => $c['account']->id, 'provider' => 'whatsapp', 'provider_account_id' => 'waba-store', 'whatsapp_template_id' => 'meta-1', 'status' => 'approved', 'submission_payload' => [], 'meta_response' => [], 'synced_at' => now()]);
    StoreOrderSetting::forWorkspace($c['workspace']->id)->update(['whatsapp_template_id' => $template->id]);
    $this->mock(ChannelManager::class, fn ($mock) => $mock->shouldReceive('sendMessage')->once()
        ->andReturn(['ok' => false, 'error_code' => '131047', 'error' => 'The 24-hour reply window has closed']));

    $this->actingAs($c['user'])
        ->post(route('user.commerce.orders.packing-slip.share', $c['order']), ['recipients' => ['+8801711223344']])
        ->assertSessionHas('success');

    Queue::assertPushed(SendOrderTemplateFallback::class, 1);
});

it('skips recipients who opted out or are blocked', function (): void {
    $c = packingSlipContext();
    Contact::withoutEvents(fn () => Contact::query()->create(['workspace_id' => $c['workspace']->id, 'name' => 'Blocked', 'phone' => '+8801711223344', 'source' => 'import', 'blocked_at' => now()]));
    $this->mock(ChannelManager::class, fn ($mock) => $mock->shouldNotReceive('sendMessage'));

    $this->actingAs($c['user'])
        ->post(route('user.commerce.orders.packing-slip.share', $c['order']), ['recipients' => ['+8801711223344']])
        ->assertSessionHas('warning');
});

it('requires a recipient and rejects invalid numbers', function (): void {
    $c = packingSlipContext();

    $this->actingAs($c['user'])->post(route('user.commerce.orders.packing-slip.share', $c['order']), [])->assertSessionHasErrors('recipients');
    $this->actingAs($c['user'])->post(route('user.commerce.orders.packing-slip.share', $c['order']), ['extra_phone' => '01711'])->assertSessionHasErrors('extra_phone');
});

it('does not share another store\'s order', function (): void {
    $c = packingSlipContext();
    $other = packingSlipContext();

    $this->actingAs($c['user'])
        ->post(route('user.commerce.orders.packing-slip.share', $other['order']), ['extra_phone' => '+8801911223344'])
        ->assertNotFound();
});

it('queues one job per selected order and recipient, ignoring other stores\' orders', function (): void {
    $c = packingSlipContext();
    $other = packingSlipContext();

    $this->actingAs($c['user'])
        ->post(route('user.commerce.orders.packing-slips.bulk-share'), ['order_ids' => [$c['order']->id, $other['order']->id], 'recipients' => ['+8801711223344', '+8801811223344']])
        ->assertSessionHas('success');

    Queue::assertPushed(SendPackingSlipWhatsApp::class, 2);
    Queue::assertPushed(SendPackingSlipWhatsApp::class, fn (SendPackingSlipWhatsApp $job): bool => $job->orderId === $c['order']->id && $job->recipient === ['name' => 'Packer Rahim', 'phone' => '+8801711223344']);
    Queue::assertNotPushed(SendPackingSlipWhatsApp::class, fn (SendPackingSlipWhatsApp $job): bool => $job->orderId === $other['order']->id);
});

it('serves the packing slip pdf only on a valid signed link', function (): void {
    $c = packingSlipContext();

    $response = $this->get($c['order']->packingSlipUrl())->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF-1.4')
        ->toContain('PACKING SLIP')
        ->toContain($c['order']->number)
        ->toContain('Cotton shirt')
        ->toContain('1 Main Street');
    $this->get($c['order']->packingSlipUrl(download: true))->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="'.$c['order']->number.'-packing-slip.pdf"');

    $this->get('/packing-slips/'.$c['order']->id)->assertForbidden();
    $this->get(URL::temporarySignedRoute('commerce.packing-slips.show', now()->subMinute(), ['order' => $c['order']->id]))->assertForbidden();
});

it('saves normalized packing slip recipients in order settings and rejects invalid numbers', function (): void {
    $c = packingSlipContext();
    $settings = StoreOrderSetting::forWorkspace($c['workspace']->id);
    $base = ['currency' => $settings->currency, 'reservation_hours' => $settings->reservation_hours ?: 24];

    $this->actingAs($c['user'])
        ->put(route('user.commerce.orders.settings.update'), $base + ['packing_slip_recipients' => [['name' => ' Packer ', 'phone' => '+880 1711-223344'], ['name' => 'Dup', 'phone' => '+8801711223344']]])
        ->assertSessionHasNoErrors();
    expect($settings->fresh()->packing_slip_recipients)->toBe([['name' => 'Dup', 'phone' => '+8801711223344']]);

    $this->actingAs($c['user'])
        ->put(route('user.commerce.orders.settings.update'), $base + ['packing_slip_recipients' => [['name' => 'Bad', 'phone' => 'abc']]])
        ->assertSessionHasErrors('packing_slip_recipients.0.phone');

    $this->actingAs($c['user'])->put(route('user.commerce.orders.settings.update'), $base + ['packing_slip_recipients' => ''])->assertSessionHasNoErrors();
    expect($settings->fresh()->packing_slip_recipients)->toBe([]);
});
