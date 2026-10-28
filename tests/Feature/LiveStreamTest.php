<?php

use App\Models\User;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Storefront\Events\LiveCommentCreated;
use App\Modules\Commerce\Storefront\Events\LiveProductPinned;
use App\Modules\Commerce\Storefront\Events\LiveStreamStatusChanged;
use App\Modules\Commerce\Storefront\Events\LiveWebRtcSignal;
use App\Modules\Commerce\Storefront\Models\LiveStream;
use App\Modules\Commerce\Storefront\Models\StoreDomain;
use App\Modules\Commerce\Storefront\Models\StorefrontSlider;
use App\Modules\Commerce\Storefront\Services\CustomerService;
use App\Modules\Commerce\Storefront\Services\LiveStreamService;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\StorefrontDatabase;

beforeEach(function (): void {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'storefront.platform_secret' => str_repeat('p', 40),
        'storefront.preview_secret' => str_repeat('v', 40),
        'cache.default' => 'array',
    ]);
    DB::purge('sqlite');
    StorefrontDatabase::migrate();
    Schema::table('users', fn ($t) => $t->softDeletes());
    Queue::fake();
    Event::fake([
        LiveStreamStatusChanged::class,
        LiveWebRtcSignal::class,
        LiveCommentCreated::class,
        LiveProductPinned::class,
    ]);
    Storage::fake('public');

    $ownerId = DB::table('users')->insertGetId(['name' => 'Store Owner']);
    $this->owner = new User;
    $this->owner->forceFill(['id' => $ownerId, 'first_name' => 'Store', 'last_name' => 'Owner']);
    $this->owner->exists = true;

    $this->store = Workspace::query()->create([
        'name' => 'One',
        'slug' => 'one',
        'status' => 'active',
        'owner_id' => $ownerId,
    ]);
    StoreDomain::query()->create([
        'workspace_id' => $this->store->id,
        'host' => 'one.localhost',
        'is_primary' => true,
        'status' => 'active',
        'verification_token' => 'test',
        'type' => 'system',
    ]);

    $this->headers = [
        'X-Storefront-Secret' => str_repeat('p', 40),
        'X-Store-Host' => 'one.localhost',
    ];

    $this->contact = Contact::withoutEvents(fn () => Contact::query()->create([
        'workspace_id' => $this->store->id,
        'name' => 'Alice Customer',
        'phone' => '+8801700000000',
        'source' => 'website',
        'opt_in_status' => 'unknown',
    ]));
    $this->customer = app(CustomerService::class)->enroll($this->contact, true);
    $this->customerHeaders = [
        ...$this->headers,
        'Authorization' => 'Bearer '.$this->customer->createToken('test', ['storefront'], now()->addDay())->plainTextToken,
    ];

    $this->product = Product::query()->create([
        'workspace_id' => $this->store->id,
        'name' => 'Heavyweight Hoodie',
        'slug' => 'heavyweight-hoodie',
        'sku' => 'hoodie-01',
        'status' => 'published',
        'visibility' => 'published',
        'selling_mode' => 'retail',
        'single_piece_price' => 45,
    ]);
    ProductVariant::query()->create([
        'workspace_id' => $this->store->id,
        'product_id' => $this->product->id,
        'sku' => 'hoodie-01-l',
        'meta_retailer_id' => 'hoodie-01-l',
        'size' => 'L',
        'price' => 45,
        'stock_quantity' => 10,
        'status' => 'active',
    ]);
});

afterEach(function (): void {
    DB::disconnect('sqlite');
});

it('allows owner mobile app to go live on storefront, relay webrtc signals, pin products, save mp4 to public storage, and promote to hero slider', function (): void {
    Sanctum::actingAs($this->owner);

    // 1. Create live stream from mobile app
    $createRes = $this->postJson("/api/v1/mobile/workspaces/{$this->store->id}/lives", [
        'title' => 'Winter Drop Live Showcase',
        'description' => 'Live fitting and exclusive discounts',
        'broadcast_platforms' => ['storefront'],
        'product_ids' => [$this->product->id],
        'add_to_slider' => true,
    ])->assertCreated();

    $liveId = $createRes->json('data.id');
    $slug = $createRes->json('data.slug');
    expect($slug)->toBe('winter-drop-live-showcase');

    // 2. Start live stream
    $this->postJson("/api/v1/mobile/workspaces/{$this->store->id}/lives/{$liveId}/start", [
        'broadcast_platforms' => ['storefront'],
    ])->assertOk()->assertJsonPath('data.status', 'live');

    Event::assertDispatched(LiveStreamStatusChanged::class, fn ($e) => $e->status === 'live' && $e->streamId === $liveId);

    // 3. Storefront sees active live stream & mobile storefront endpoint also sees it
    $this->getJson('/api/storefront/v1/lives/active', $this->headers)
        ->assertOk()
        ->assertJsonPath('data.active_live.slug', $slug);

    $this->getJson('/api/v1/mobile/storefront/lives', ['X-Store-Host' => 'one.localhost'])
        ->assertOk()
        ->assertJsonCount(1, 'data.live_streams');

    // 4. WebRTC Signaling (Viewer -> Owner and Owner -> Viewer via Reverb)
    $this->postJson("/api/storefront/v1/lives/{$slug}/signal", [
        'type' => 'viewer-join',
        'sender_peer_id' => 'viewer_123',
        'payload' => ['sdp' => 'mock-offer'],
    ], $this->headers)->assertOk();

    Event::assertDispatched(LiveWebRtcSignal::class, fn ($e) => $e->type === 'viewer-join' && $e->senderPeerId === 'viewer_123');

    $this->postJson("/api/v1/mobile/workspaces/{$this->store->id}/lives/{$liveId}/signal", [
        'type' => 'offer',
        'sender_peer_id' => 'owner_broadcaster',
        'target_peer_id' => 'viewer_123',
        'payload' => ['sdp' => 'broadcaster-offer'],
    ])->assertOk();

    // 5. Pin product in real time
    $this->postJson("/api/v1/mobile/workspaces/{$this->store->id}/lives/{$liveId}/pin-product", [
        'product_id' => $this->product->id,
    ])->assertOk()->assertJsonPath('data.pinned_product_id', $this->product->id);

    Event::assertDispatched(LiveProductPinned::class, fn ($e) => $e->pinnedProductId === $this->product->id);

    // 6. Customer posts live comment & likes stream
    $this->postJson("/api/storefront/v1/lives/{$slug}/comments", [
        'body' => 'Does this hoodie come in XL?',
    ], $this->customerHeaders)->assertCreated()->assertJsonPath('data.author_name', 'Alice Customer');

    Event::assertDispatched(LiveCommentCreated::class);

    $this->postJson("/api/storefront/v1/lives/{$slug}/react", [
        'action' => 'like',
    ], $this->headers)->assertOk()->assertJsonPath('data.likes_count', 1);

    // 7. Stop stream and upload recorded MP4 video to public storage + promote to Hero Slider
    $videoFile = UploadedFile::fake()->create('live-recording.mp4', 2048, 'video/mp4');
    $thumbFile = UploadedFile::fake()->image('cover.jpg');

    $stopRes = $this->postJson("/api/v1/mobile/workspaces/{$this->store->id}/lives/{$liveId}/stop", [
        'video' => $videoFile,
        'thumbnail' => $thumbFile,
        'add_to_slider' => true,
        'duration_seconds' => 185,
    ])->assertOk();

    expect($stopRes->json('data.status'))->toBe('ended')
        ->and($stopRes->json('data.is_in_slider'))->toBeTrue()
        ->and($stopRes->json('data.duration_seconds'))->toBe(185)
        ->and($stopRes->json('data.video_url'))->toContain('/storage/commerce/lives/');

    $streamModel = LiveStream::query()->findOrFail($liveId);
    Storage::disk('public')->assertExists($streamModel->video_path);
    Storage::disk('public')->assertExists($streamModel->thumbnail_path);

    // 8. Verify Hero Slider now contains the saved video slide
    $slider = StorefrontSlider::query()->where('workspace_id', $this->store->id)->first();
    expect($slider)->not->toBeNull()
        ->and($slider->slides[0]['video'])->toBe($streamModel->video_path)
        ->and($slider->slides[0]['button_link'])->toBe('/live/'.$slug);

    // 9. Storefront /lives archive now lists the recorded stream
    $this->getJson('/api/storefront/v1/lives', $this->headers)
        ->assertOk()
        ->assertJsonCount(0, 'data.live_streams')
        ->assertJsonCount(1, 'data.recorded_streams')
        ->assertJsonPath('data.recorded_streams.0.slug', $slug);
});

it('simultaneously streams to facebook live and storefront when facebook settings are up to date, and syncs facebook comments', function (): void {
    Sanctum::actingAs($this->owner);

    Http::fake([
        'https://graph.facebook.com/*/123456789/live_videos' => Http::response([
            'id' => 'fb_live_999',
            'stream_url' => 'rtmps://live-api-s.facebook.com:443/rtmp/FB-KEY-999',
            'secure_stream_url' => 'rtmps://live-api-s.facebook.com:443/rtmp/FB-KEY-999',
            'permalink_url' => '/FashionFiest/videos/fb_live_999',
        ], 200),
        'https://graph.facebook.com/*/123456789*' => Http::response([
            'id' => '123456789',
            'name' => 'FashionFiest Official Page',
        ], 200),
        'https://graph.facebook.com/*/fb_live_999*' => function ($request) {
            if ($request->method() === 'POST') {
                return Http::response(['id' => 'fb_live_999'], 200);
            }

            return Http::response([
                'live_views' => 42,
                'comments' => [
                    'data' => [
                        [
                            'id' => 'fb_comment_1',
                            'from' => ['name' => 'Facebook Shopper'],
                            'message' => 'Watching live from Facebook!',
                            'created_time' => now()->toIso8601String(),
                        ],
                    ],
                ],
            ], 200);
        },
    ]);

    // 1. Save & verify Facebook settings
    $this->putJson("/api/v1/mobile/workspaces/{$this->store->id}/lives/settings", [
        'enabled' => true,
        'default_platforms' => ['storefront', 'facebook'],
        'facebook' => [
            'enabled' => true,
            'use_connected_messenger_page' => false,
            'page_id' => '123456789',
            'page_name' => 'FashionFiest Official Page',
            'page_access_token' => 'EAAB_VALID_PAGE_TOKEN',
            'sync_facebook_comments' => true,
        ],
    ])->assertOk();

    $verifyRes = $this->postJson("/api/v1/mobile/workspaces/{$this->store->id}/lives/facebook/verify")
        ->assertOk();
    expect($verifyRes->json('data.ready'))->toBeTrue()
        ->and($verifyRes->json('data.page_name'))->toBe('FashionFiest Official Page');

    // 2. Create & Start simultaneous broadcast (Storefront + Facebook Live)
    $createRes = $this->postJson("/api/v1/mobile/workspaces/{$this->store->id}/lives", [
        'title' => 'Dual Platform Simulcast Live',
        'broadcast_platforms' => ['storefront', 'facebook'],
    ])->assertCreated();

    $liveId = $createRes->json('data.id');

    $startRes = $this->postJson("/api/v1/mobile/workspaces/{$this->store->id}/lives/{$liveId}/start", [
        'broadcast_platforms' => ['storefront', 'facebook'],
    ])->assertOk();

    expect($startRes->json('data.status'))->toBe('live')
        ->and($startRes->json('data.facebook_status'))->toBe('live')
        ->and($startRes->json('data.facebook_live_id'))->toBe('fb_live_999')
        ->and($startRes->json('data.facebook_permalink_url'))->toBe('https://www.facebook.com/FashionFiest/videos/fb_live_999');

    // 3. Sync Facebook Live comments and viewer count into unified DB & Reverb
    $syncRes = $this->postJson("/api/v1/mobile/workspaces/{$this->store->id}/lives/{$liveId}/facebook/sync")
        ->assertOk();
    expect($syncRes->json('data.imported_comments'))->toBe(1)
        ->and($syncRes->json('data.facebook_viewer_count'))->toBe(42);

    // 4. Stop simultaneous stream
    $stopRes = $this->postJson("/api/v1/mobile/workspaces/{$this->store->id}/lives/{$liveId}/stop")
        ->assertOk();
    expect($stopRes->json('data.status'))->toBe('ended')
        ->and($stopRes->json('data.facebook_status'))->toBe('ended');
});

it('blocks facebook-only broadcast when facebook settings are not configured', function (): void {
    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/mobile/workspaces/{$this->store->id}/lives", [
        'title' => 'FB Only Stream Without Config',
        'broadcast_platforms' => ['facebook'],
    ])->assertStatus(422);
});

it('exposes active live status in cms settings, renders live_videos cms sections, and cleans up public storage on delete', function (): void {
    Sanctum::actingAs($this->owner);

    $videoFile = UploadedFile::fake()->create('studio-drop.mp4', 1024, 'video/mp4');
    $createRes = $this->postJson("/api/v1/mobile/workspaces/{$this->store->id}/lives", [
        'title' => 'CMS Header & Section Test Live',
        'broadcast_platforms' => ['storefront'],
        'video' => $videoFile,
        'add_to_slider' => true,
    ])->assertCreated();

    $liveId = $createRes->json('data.id');

    $this->postJson("/api/v1/mobile/workspaces/{$this->store->id}/lives/{$liveId}/start", [
        'broadcast_platforms' => ['storefront'],
    ])->assertOk();

    // CMS settings endpoint reflects active live status for header indicator
    $this->getJson('/api/storefront/v1/cms/settings', $this->headers)
        ->assertOk()
        ->assertJsonPath('data.live_shopping.enabled', true)
        ->assertJsonPath('data.live_shopping.is_live_now', true)
        ->assertJsonPath('data.live_shopping.active_live.id', $liveId);

    // Verify the LiveStreamService settings & formatted stream structure used by the Owner Panel
    $liveService = app(LiveStreamService::class);
    $ownerLiveSettings = $liveService->settings($this->store->id);
    expect($ownerLiveSettings['enabled'])->toBeTrue()
        ->and($ownerLiveSettings['reverb']['enabled'])->toBeTrue();

    // Toggle slider off and back on via mobile owner endpoint
    $this->postJson("/api/v1/mobile/workspaces/{$this->store->id}/lives/{$liveId}/toggle-slider", [
        'enable' => false,
    ])->assertOk()->assertJsonPath('data.is_in_slider', false);

    $this->postJson("/api/v1/mobile/workspaces/{$this->store->id}/lives/{$liveId}/toggle-slider", [
        'enable' => true,
    ])->assertOk()->assertJsonPath('data.is_in_slider', true);

    $streamModel = LiveStream::query()->findOrFail($liveId);
    $videoPath = $streamModel->video_path;
    Storage::disk('public')->assertExists($videoPath);

    // Delete stream and verify public storage file & slider slide are cleaned up
    $this->deleteJson("/api/v1/mobile/workspaces/{$this->store->id}/lives/{$liveId}")
        ->assertOk()
        ->assertJsonPath('data.deleted', true);

    Storage::disk('public')->assertMissing($videoPath);
    expect(LiveStream::query()->find($liveId))->toBeNull();
});

it('returns pusher websocket connection details when broadcasting through pusher', function (): void {
    config([
        'broadcasting.default' => 'pusher',
        'broadcasting.connections.pusher.key' => 'pusher-test-key',
        'broadcasting.connections.pusher.options.cluster' => 'ap2',
    ]);

    expect(app(LiveStreamService::class)->reverbConfig())->toBe([
        'app_key' => 'pusher-test-key',
        'host' => 'ws-ap2.pusher.com',
        'port' => 443,
        'scheme' => 'https',
        'enabled' => true,
    ]);
});
