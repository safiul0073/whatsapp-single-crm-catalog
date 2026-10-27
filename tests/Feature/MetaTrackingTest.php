<?php

use App\Models\User;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Services\MetaConversionsService;
use App\Modules\Commerce\Services\TikTokEventsService;
use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use App\Modules\Commerce\Storefront\Services\CmsService;
use App\Modules\Commerce\Storefront\Services\TrackingSettingsService;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function trackingWorkspace(): Workspace
{
    $user = User::factory()->create(['email_verified_at' => now()]);

    return Workspace::query()->create([
        'owner_id' => $user->id,
        'name' => 'Tracking Store',
        'slug' => 'tracking-store-'.$user->id,
        'status' => 'active',
        'timezone' => 'UTC',
        'settings' => [],
    ]);
}

function trackingOrder(Workspace $workspace): Order
{
    $order = new Order([
        'workspace_id' => $workspace->id,
        'number' => 'FF-1001',
        'currency' => 'bdt',
        'total' => '2500.0000',
        'customer_snapshot' => ['name' => 'Ayesha Rahman', 'phone' => '+880 1711-223344', 'email' => ' Ayesha@Example.com '],
        'shipping_address' => ['city' => 'Dhaka', 'country' => 'BD'],
    ]);
    $order->setRelation('items', collect([
        new OrderItem(['retailer_id' => 'sku-1-m', 'sku' => 'SKU-1', 'quantity' => 2, 'unit_price' => '1250.0000']),
    ]));

    return $order;
}

it('saves tracking settings with an encrypted token that never reaches the public settings', function (): void {
    $workspace = trackingWorkspace();
    $tracking = app(TrackingSettingsService::class);

    $tracking->save($workspace->id, ['meta_pixel_id' => '123456789012', 'tiktok_pixel_id' => 'CABC123DEF456', 'meta_capi_enabled' => true, 'meta_capi_token' => 'EAAB-secret', 'meta_test_event_code' => 'TEST123']);

    $raw = json_encode(StorefrontSetting::query()->where('workspace_id', $workspace->id)->first()->config);
    $public = app(CmsService::class)->settings($workspace->id)['tracking'];

    expect($raw)->not->toContain('EAAB-secret')
        ->and(json_encode(app(CmsService::class)->settings($workspace->id)))->not->toContain('EAAB-secret')
        ->and($public)->toBe(['meta_pixel_id' => '123456789012', 'tiktok_pixel_id' => 'CABC123DEF456'])
        ->and($tracking->admin($workspace->id)['meta_capi_token_set'])->toBeTrue()
        ->and($tracking->metaConversions($workspace->id))->toBe(['pixel_id' => '123456789012', 'token' => 'EAAB-secret', 'test_event_code' => 'TEST123']);

    $tracking->save($workspace->id, ['meta_pixel_id' => '123456789012', 'meta_capi_enabled' => true, 'meta_capi_token' => '']);
    expect($tracking->metaConversions($workspace->id)['token'])->toBe('EAAB-secret');

    $tracking->save($workspace->id, ['meta_pixel_id' => '123456789012', 'meta_capi_enabled' => true, 'clear_token' => true]);
    expect($tracking->metaConversions($workspace->id))->toBeNull();
});

it('builds a hashed, deduplicated Purchase event', function (): void {
    $workspace = trackingWorkspace();
    $event = app(MetaConversionsService::class)->purchaseEvent(trackingOrder($workspace), ['ip' => '203.0.113.9', 'user_agent' => 'Mozilla/5.0', 'host' => 'shop.example.com']);

    expect($event['event_name'])->toBe('Purchase')
        ->and($event['event_id'])->toBe('purchase-FF-1001')
        ->and($event['event_source_url'])->toBe('https://shop.example.com/checkout')
        ->and($event['user_data']['em'])->toBe([hash('sha256', 'ayesha@example.com')])
        ->and($event['user_data']['ph'])->toBe([hash('sha256', '8801711223344')])
        ->and($event['user_data']['fn'])->toBe([hash('sha256', 'ayesha')])
        ->and($event['user_data']['ln'])->toBe([hash('sha256', 'rahman')])
        ->and($event['user_data']['ct'])->toBe([hash('sha256', 'dhaka')])
        ->and($event['user_data']['country'])->toBe([hash('sha256', 'bd')])
        ->and($event['user_data']['client_ip_address'])->toBe('203.0.113.9')
        ->and($event['custom_data'])->toMatchArray(['currency' => 'BDT', 'value' => 2500.0, 'order_id' => 'FF-1001', 'num_items' => 2])
        ->and($event['custom_data']['contents'])->toBe([['id' => 'sku-1-m', 'quantity' => 2, 'item_price' => 1250.0]]);
});

it('posts the Purchase event to the pixel only when Conversions API is enabled', function (): void {
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);
    $workspace = trackingWorkspace();
    $conversions = app(MetaConversionsService::class);

    expect($conversions->sendPurchase(trackingOrder($workspace)))->toBeNull();
    Http::assertNothingSent();

    app(TrackingSettingsService::class)->save($workspace->id, ['meta_pixel_id' => '123456789012', 'meta_capi_enabled' => true, 'meta_capi_token' => 'EAAB-secret', 'meta_test_event_code' => 'TEST123']);
    $conversions->sendPurchase(trackingOrder($workspace));

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/123456789012/events')
        && $request->hasHeader('Authorization', 'Bearer EAAB-secret')
        && $request['test_event_code'] === 'TEST123'
        && $request['data'][0]['event_id'] === 'purchase-FF-1001');
});

it('builds a hashed TikTok CompletePayment event that shares the pixel event id', function (): void {
    $workspace = trackingWorkspace();
    $event = app(TikTokEventsService::class)->purchaseEvent(trackingOrder($workspace), ['ip' => '203.0.113.9', 'user_agent' => 'Mozilla/5.0', 'ttp' => 'ttp-cookie-value-1', 'host' => 'shop.example.com']);

    expect($event['event'])->toBe('CompletePayment')
        ->and($event['event_id'])->toBe('purchase-FF-1001')
        ->and($event['user']['email'])->toBe(hash('sha256', 'ayesha@example.com'))
        ->and($event['user']['phone'])->toBe(hash('sha256', '+8801711223344'))
        ->and($event['user'])->toMatchArray(['ip' => '203.0.113.9', 'user_agent' => 'Mozilla/5.0', 'ttp' => 'ttp-cookie-value-1'])
        ->and($event['page'])->toBe(['url' => 'https://shop.example.com/checkout'])
        ->and($event['properties'])->toMatchArray(['currency' => 'BDT', 'value' => 2500.0, 'order_id' => 'FF-1001'])
        ->and($event['properties']['contents'])->toBe([['content_id' => 'sku-1-m', 'quantity' => 2, 'price' => 1250.0]]);
});

it('posts to the TikTok Events API only when it is enabled, keeping the token encrypted', function (): void {
    Http::fake(['business-api.tiktok.com/*' => Http::response(['code' => 0, 'message' => 'OK'])]);
    $workspace = trackingWorkspace();
    $tracking = app(TrackingSettingsService::class);
    $events = app(TikTokEventsService::class);

    expect($events->sendPurchase(trackingOrder($workspace)))->toBeNull();
    Http::assertNothingSent();

    $tracking->save($workspace->id, ['tiktok_pixel_id' => 'CABC123DEF456', 'tiktok_events_enabled' => true, 'tiktok_events_token' => 'tt-secret', 'tiktok_test_event_code' => 'TEST1234']);
    $events->sendPurchase(trackingOrder($workspace));

    expect(json_encode(StorefrontSetting::query()->where('workspace_id', $workspace->id)->first()->config))->not->toContain('tt-secret')
        ->and(json_encode(app(CmsService::class)->settings($workspace->id)))->not->toContain('tt-secret')
        ->and($tracking->admin($workspace->id)['tiktok_events_token_set'])->toBeTrue();

    Http::assertSent(fn ($request): bool => $request->url() === TikTokEventsService::ENDPOINT
        && $request->hasHeader('Access-Token', 'tt-secret')
        && $request['event_source'] === 'web'
        && $request['event_source_id'] === 'CABC123DEF456'
        && $request['test_event_code'] === 'TEST1234'
        && $request['data'][0]['event_id'] === 'purchase-FF-1001');

    $tracking->save($workspace->id, ['tiktok_pixel_id' => 'CABC123DEF456', 'tiktok_events_enabled' => true, 'clear_tiktok_token' => true]);
    expect($tracking->tiktokEvents($workspace->id))->toBeNull();
});
