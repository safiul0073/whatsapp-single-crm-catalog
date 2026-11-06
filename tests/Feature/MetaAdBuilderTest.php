<?php

use App\Models\User;
use App\Modules\Commerce\Models\Product;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MetaAds\Models\MetaAd;
use App\Modules\MetaSocial\Models\MetaBusinessConnection;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $scopes
 * @return array{0: User, 1: Workspace}
 */
function adBuilderContext(array $scopes = ['ads_read', 'ads_management', 'business_management', 'instagram_branded_content_ads_brand']): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);

    MetaBusinessConnection::query()->create([
        'workspace_id' => $workspace->id,
        'business_id' => 'biz-1',
        'business_name' => 'Acme Fashion',
        'credentials' => ['access_token' => 'EAAbusiness'],
        'scopes' => $scopes,
        'assets' => [
            'ad_accounts' => [['id' => 'act_1', 'name' => 'Acme Ads', 'currency' => 'BDT']],
            'messenger' => [['id' => 'page-1', 'name' => 'Acme Page']],
            'instagram' => [['id' => 'ig-1', 'name' => 'Acme', 'username' => 'acme']],
        ],
        'selected' => ['ad_accounts' => ['act_1']],
        'connected_at' => now(),
    ]);

    return [$user, $workspace];
}

function adProduct(Workspace $workspace): Product
{
    return Product::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Eid Panjabi',
        'slug' => 'eid-panjabi',
        'sku' => 'panjabi-01',
        'status' => 'published',
        'visibility' => 'published',
        'selling_mode' => 'retail',
        'single_piece_price' => 45,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function adPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Eid sale',
        'ad_account_id' => 'act_1',
        'page_id' => 'page-1',
        'kind' => 'product',
        'destination' => 'website',
        'message' => 'New Eid collection!',
        'daily_budget' => '500',
        'countries' => 'BD',
        'age_min' => 20,
        'age_max' => 45,
        'gender' => 'all',
    ], $overrides);
}

function fakeMetaAdsApi(bool $failCreative = false): void
{
    Http::fake([
        'graph.facebook.com/*/act_1/campaigns' => Http::response(['id' => 'camp-1']),
        'graph.facebook.com/*/act_1/adsets' => Http::response(['id' => 'set-1']),
        'graph.facebook.com/*/act_1/adcreatives' => $failCreative
            ? Http::response(['error' => ['code' => 100, 'message' => 'Invalid parameter', 'error_user_msg' => 'The image could not be downloaded.']], 400)
            : Http::response(['id' => 'cre-1']),
        'graph.facebook.com/*/act_1/ads' => Http::response(['id' => 'ad-1']),
        'graph.facebook.com/*' => Http::response(['success' => true]),
    ]);
}

function sentTo(string $suffix): ?Request
{
    return Http::recorded(fn (Request $request): bool => $request->method() === 'POST' && str_ends_with($request->url(), $suffix))->first()[0] ?? null;
}

it('creates a paused product ad in Meta: campaign, ad set, creative and ad', function (): void {
    [$user, $workspace] = adBuilderContext();
    $product = adProduct($workspace);
    fakeMetaAdsApi();

    $this->withoutMiddleware()->actingAs($user)
        ->post(route('user.meta-ads.ads.store'), adPayload(['product_id' => $product->id]))
        ->assertRedirect(route('user.meta-ads.ads.index'));

    $ad = MetaAd::query()->sole();
    expect($ad->status)->toBe(MetaAd::STATUS_PAUSED)
        ->and([$ad->meta_campaign_id, $ad->meta_adset_id, $ad->meta_creative_id, $ad->meta_ad_id])->toBe(['camp-1', 'set-1', 'cre-1', 'ad-1'])
        ->and($ad->daily_budget_minor)->toBe(50000)
        ->and($ad->currency)->toBe('BDT');

    $campaign = sentTo('/act_1/campaigns')->data();
    $adSet = sentTo('/act_1/adsets')->data();
    $creative = json_decode(sentTo('/act_1/adcreatives')->data()['object_story_spec'], true);

    expect($campaign['objective'])->toBe('OUTCOME_TRAFFIC')
        ->and($campaign['status'])->toBe('PAUSED')
        ->and($adSet['status'])->toBe('PAUSED')
        ->and($adSet['daily_budget'])->toBe(50000)
        ->and(json_decode($adSet['targeting'], true)['geo_locations']['countries'])->toBe(['BD'])
        ->and($creative['page_id'])->toBe('page-1')
        ->and($creative['link_data']['link'])->toEndWith('/products/eid-panjabi')
        ->and($creative['link_data']['call_to_action']['type'])->toBe('SHOP_NOW')
        ->and(sentTo('/act_1/ads')->data()['status'])->toBe('PAUSED');
});

it('deletes the half-built campaign and shows Meta\'s reason when a step fails', function (): void {
    [$user, $workspace] = adBuilderContext();
    $product = adProduct($workspace);
    fakeMetaAdsApi(failCreative: true);

    $this->withoutMiddleware()->actingAs($user)
        ->post(route('user.meta-ads.ads.store'), adPayload(['product_id' => $product->id]))
        ->assertRedirect();

    $ad = MetaAd::query()->sole();
    expect($ad->status)->toBe(MetaAd::STATUS_FAILED)
        ->and($ad->meta_campaign_id)->toBeNull()
        ->and($ad->last_error)->toContain('The image could not be downloaded.');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_ends_with($request->url(), '/camp-1'));
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/act_1/ads'));
});

it('starts and pauses an ad on the campaign, ad set and ad', function (): void {
    [$user, $workspace] = adBuilderContext();
    $ad = MetaAd::query()->create([
        'workspace_id' => $workspace->id, 'ad_account_id' => 'act_1', 'page_id' => 'page-1', 'kind' => 'product', 'name' => 'Eid sale',
        'objective' => 'OUTCOME_TRAFFIC', 'daily_budget_minor' => 50000, 'currency' => 'BDT', 'status' => 'paused',
        'meta_campaign_id' => 'camp-1', 'meta_adset_id' => 'set-1', 'meta_creative_id' => 'cre-1', 'meta_ad_id' => 'ad-1',
    ]);
    fakeMetaAdsApi();

    $this->withoutMiddleware()->actingAs($user)->post(route('user.meta-ads.ads.start', $ad))->assertSessionHas('status');

    expect($ad->refresh()->status)->toBe(MetaAd::STATUS_ACTIVE);
    foreach (['camp-1', 'set-1', 'ad-1'] as $objectId) {
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/'.$objectId) && $request->data() === ['status' => 'ACTIVE']);
    }

    $this->withoutMiddleware()->actingAs($user)->post(route('user.meta-ads.ads.pause', $ad))->assertSessionHas('status');
    expect($ad->refresh()->status)->toBe(MetaAd::STATUS_PAUSED);
});

it('asks owners to reconnect Meta when ad management was not granted', function (): void {
    [$user, $workspace] = adBuilderContext(['ads_read']);
    $product = adProduct($workspace);
    Http::fake();

    $this->withoutMiddleware()->actingAs($user)->get(route('user.meta-ads.ads.create'))
        ->assertOk()
        ->assertSee('data-meta-ads-access="reconnect"', false)
        ->assertDontSee('data-meta-ad-form', false);

    $this->withoutMiddleware()->actingAs($user)
        ->post(route('user.meta-ads.ads.store'), adPayload(['product_id' => $product->id]))
        ->assertSessionHasErrors('ad_account_id');

    expect(MetaAd::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('boosts a Page post using the post as the creative', function (): void {
    [$user, $workspace] = adBuilderContext();
    ChannelAccount::query()->create([
        'workspace_id' => $workspace->id, 'provider' => 'messenger', 'name' => 'Acme Page', 'status' => 'connected',
        'credentials' => ['access_token' => 'page-token'], 'provider_account_id' => 'page-1', 'connected_at' => now(),
    ]);
    Http::fake([
        'graph.facebook.com/*/page-1/published_posts*' => Http::response(['data' => [['id' => 'page-1_777', 'message' => 'New arrivals are in!', 'full_picture' => 'https://cdn.example.test/p.jpg']]]),
        'graph.facebook.com/*/act_1/campaigns' => Http::response(['id' => 'camp-2']),
        'graph.facebook.com/*/act_1/adsets' => Http::response(['id' => 'set-2']),
        'graph.facebook.com/*/act_1/adcreatives' => Http::response(['id' => 'cre-2']),
        'graph.facebook.com/*/act_1/ads' => Http::response(['id' => 'ad-2']),
    ]);

    $this->withoutMiddleware()->actingAs($user)
        ->getJson(route('user.meta-ads.ads.page-posts', ['page_id' => 'page-1']))
        ->assertOk()
        ->assertJsonPath('posts.0.id', 'page-1_777')
        ->assertJsonPath('posts.0.message', 'New arrivals are in!');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/page-1/published_posts') && $request->hasHeader('Authorization', 'Bearer page-token'));

    $this->withoutMiddleware()->actingAs($user)
        ->post(route('user.meta-ads.ads.store'), adPayload(['kind' => 'boost_post', 'post_id' => 'page-1_777', 'destination' => null]))
        ->assertRedirect();

    expect(sentTo('/act_1/adcreatives')->data()['object_story_id'])->toBe('page-1_777')
        ->and(sentTo('/act_1/campaigns')->data()['objective'])->toBe('OUTCOME_ENGAGEMENT')
        ->and(MetaAd::query()->sole()->status)->toBe(MetaAd::STATUS_PAUSED);
});

it('lists creator posts and runs one as a partnership ad with the sponsor fields', function (): void {
    [$user] = adBuilderContext();
    Http::fake([
        'graph.facebook.com/*/biz-1/partnership-ads-advertisable-content*' => Http::response(['data' => [[
            'content_id' => 'ig-media-55',
            'platform' => 'INSTAGRAM',
            'caption' => 'Loving my new panjabi from @acme',
            'author' => ['display_name' => 'Nadia Styles'],
            'partnership_info' => ['ad_code' => 'AD-CODE-55', 'ad_eligibility' => 'ELIGIBLE', 'permission_status' => 'APPROVED', 'eligibility_errors' => []],
        ]]]),
        'graph.facebook.com/*/act_1/campaigns' => Http::response(['id' => 'camp-3']),
        'graph.facebook.com/*/act_1/adsets' => Http::response(['id' => 'set-3']),
        'graph.facebook.com/*/act_1/adcreatives' => Http::response(['id' => 'cre-3']),
        'graph.facebook.com/*/act_1/ads' => Http::response(['id' => 'ad-3']),
    ]);

    $this->withoutMiddleware()->actingAs($user)
        ->getJson(route('user.meta-ads.ads.partnership-content', ['page_id' => 'page-1', 'instagram_account_id' => 'ig-1']))
        ->assertOk()
        ->assertJsonPath('content.0.content_id', 'ig-media-55')
        ->assertJsonPath('content.0.creator', 'Nadia Styles')
        ->assertJsonPath('content.0.ad_code', 'AD-CODE-55');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/biz-1/partnership-ads-advertisable-content')
        && $request['fb_page_id'] === 'page-1' && $request['ig_user_id'] === 'ig-1');

    $this->withoutMiddleware()->actingAs($user)
        ->post(route('user.meta-ads.ads.store'), adPayload([
            'kind' => 'partnership', 'destination' => null, 'instagram_account_id' => 'ig-1',
            'content_id' => 'ig-media-55', 'ad_code' => 'AD-CODE-55', 'platform' => 'INSTAGRAM',
        ]))
        ->assertRedirect();

    $creative = sentTo('/act_1/adcreatives')->data();
    expect($creative['object_id'])->toBe('page-1')
        ->and(json_decode($creative['branded_content'], true))->toBe(['ad_format' => 1, 'instagram_boost_post_access_token' => 'AD-CODE-55'])
        ->and(json_decode($creative['facebook_branded_content'], true))->toBe(['sponsor_page_id' => 'page-1'])
        ->and(json_decode($creative['instagram_branded_content'], true))->toBe(['sponsor_id' => 'ig-1'])
        ->and(sentTo('/act_1/campaigns')->data()['objective'])->toBe('OUTCOME_AWARENESS');
});

it('uses the Instagram media id when a partnership post has no ad code', function (): void {
    [$user] = adBuilderContext();
    fakeMetaAdsApi();

    $this->withoutMiddleware()->actingAs($user)
        ->post(route('user.meta-ads.ads.store'), adPayload(['kind' => 'partnership', 'destination' => null, 'content_id' => 'ig-media-56', 'platform' => 'INSTAGRAM']))
        ->assertRedirect();

    expect(sentTo('/act_1/adcreatives')->data()['source_instagram_media_id'])->toBe('ig-media-56');
});

it('needs the creator ad code for Facebook creator posts', function (): void {
    [$user] = adBuilderContext();
    Http::fake();

    $this->withoutMiddleware()->actingAs($user)
        ->post(route('user.meta-ads.ads.store'), adPayload(['kind' => 'partnership', 'destination' => null, 'content_id' => 'fb-post-1', 'platform' => 'FACEBOOK']))
        ->assertSessionHasErrors('ad_code');

    Http::assertNothingSent();
});

it('sends a partnership permission request to a creator', function (): void {
    [$user] = adBuilderContext();
    Http::fake(['graph.facebook.com/*/ig-1/branded_content_ad_permissions' => Http::response(['success' => true])]);

    $this->withoutMiddleware()->actingAs($user)
        ->postJson(route('user.meta-ads.ads.creator-permission'), ['instagram_account_id' => 'ig-1', 'creator_username' => '@nadia.styles'])
        ->assertOk();

    expect(sentTo('/ig-1/branded_content_ad_permissions')->data()['creator_instagram_username'])->toBe('nadia.styles');
});

it('keeps each workspace\'s ads private', function (): void {
    [, $otherWorkspace] = adBuilderContext();
    $ad = MetaAd::query()->create([
        'workspace_id' => $otherWorkspace->id, 'ad_account_id' => 'act_1', 'page_id' => 'page-1', 'kind' => 'product', 'name' => 'Theirs',
        'objective' => 'OUTCOME_TRAFFIC', 'daily_budget_minor' => 100, 'currency' => 'BDT', 'status' => 'paused', 'meta_campaign_id' => 'camp-9',
    ]);
    [$user] = adBuilderContext();
    Http::fake();

    $this->withoutMiddleware()->actingAs($user)->post(route('user.meta-ads.ads.start', $ad))->assertNotFound();
    Http::assertNothingSent();
});
