<?php

use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Models\User;
use App\Modules\Commerce\Models\Product;
use App\Modules\Contacts\Models\Contact;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MetaSocial\Models\MessengerMarketingOptin;
use App\Modules\MetaSocial\Models\MetaBusinessConnection;
use App\Modules\MetaSocial\Models\MetaPagePost;
use App\Modules\MetaSocial\Services\MetaPagePostService;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: Workspace, 2: ChannelAccount}
 */
function postsContext(): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $page = ChannelAccount::query()->create([
        'workspace_id' => $workspace->id, 'provider' => 'messenger', 'name' => 'Acme Page', 'status' => 'connected',
        'credentials' => ['access_token' => 'page-token'], 'provider_account_id' => 'page-1', 'provider_display_id' => 'Acme Page',
        'settings' => [], 'connected_at' => now(),
    ]);
    MetaBusinessConnection::query()->create([
        'workspace_id' => $workspace->id, 'business_id' => 'biz-1', 'credentials' => ['access_token' => 'EAAbusiness'],
        'scopes' => ['pages_manage_posts', 'instagram_content_publish'],
        'assets' => ['messenger' => [['id' => 'page-1', 'name' => 'Acme Page']], 'instagram' => [['id' => 'ig-1', 'name' => 'Acme', 'username' => 'acme']]],
        'selected' => [], 'connected_at' => now(),
    ]);

    return [$user, $workspace, $page];
}

function fakeMetaPosting(bool $instagramFailsFirst = false): void
{
    Http::fake([
        'graph.facebook.com/*/page-1/feed' => Http::response(['id' => 'page-1_100']),
        'graph.facebook.com/*/page-1/photos' => Http::response(['id' => 'photo-1', 'post_id' => 'page-1_200']),
        'graph.facebook.com/*/ig-1/media_publish' => Http::response(['id' => 'ig-media-9']),
        'graph.facebook.com/*/ig-1/media' => $instagramFailsFirst
            ? Http::sequence()
                ->push(['error' => ['code' => 9004, 'message' => 'Only photo or video can be accepted as media type.']], 400)
                ->push(['id' => 'container-1'])
            : Http::response(['id' => 'container-1']),
        'graph.facebook.com/*' => Http::response(['success' => true]),
    ]);
}

function postedTo(string $suffix): ?Request
{
    return Http::recorded(fn (Request $request): bool => $request->method() === 'POST' && str_ends_with($request->url(), $suffix))->first()[0] ?? null;
}

it('shows every Meta feature in the Facebook & Instagram sidebar section', function (): void {
    [$user] = postsContext();

    $response = $this->withoutMiddleware()->actingAs($user)->get(route('user.meta-social.posts.index'))->assertOk();

    foreach (['Facebook &amp; Instagram', 'Ad results', 'My ads', 'Create ad', 'Page &amp; Instagram posts', 'Facebook Live', 'Offer subscribers', 'Meta Business connection', 'Product catalog'] as $label) {
        $response->assertSee($label, false);
    }
    foreach (['user.meta-ads.index', 'user.meta-ads.ads.index', 'user.meta-ads.ads.create', 'user.meta-social.offers.index', 'user.meta-social.business.show'] as $route) {
        $response->assertSee(route($route), false);
    }
});

it('publishes a text and link post to the Facebook Page feed', function (): void {
    [$user] = postsContext();
    fakeMetaPosting();

    $this->withoutMiddleware()->actingAs($user)->post(route('user.meta-social.posts.store'), [
        'page_id' => 'page-1', 'targets' => ['facebook'], 'message' => 'Eid sale starts today!', 'link' => 'https://shop.example.test/sale',
    ])->assertRedirect(route('user.meta-social.posts.index'));

    $post = MetaPagePost::query()->sole();
    expect($post->status)->toBe(MetaPagePost::STATUS_PUBLISHED)
        ->and($post->facebook_post_id)->toBe('page-1_100')
        ->and(postedTo('/page-1/feed')->data())->toBe(['message' => 'Eid sale starts today!', 'link' => 'https://shop.example.test/sale']);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/page-1/feed') && $request->hasHeader('Authorization', 'Bearer page-token'));
});

it('publishes a photo to Facebook and Instagram from a product', function (): void {
    [$user, $workspace] = postsContext();
    $product = Product::query()->create([
        'workspace_id' => $workspace->id, 'name' => 'Eid Panjabi', 'slug' => 'eid-panjabi', 'sku' => 'p-1',
        'status' => 'published', 'visibility' => 'published', 'selling_mode' => 'retail', 'single_piece_price' => 45,
    ]);
    fakeMetaPosting();

    $this->withoutMiddleware()->actingAs($user)->post(route('user.meta-social.posts.store'), [
        'page_id' => 'page-1', 'targets' => ['facebook', 'instagram'], 'instagram_account_id' => 'ig-1',
        'product_id' => $product->id, 'image_url' => 'https://cdn.example.test/panjabi.jpg',
    ])->assertRedirect();

    $post = MetaPagePost::query()->sole();
    expect($post->status)->toBe(MetaPagePost::STATUS_PUBLISHED)
        ->and($post->link)->toEndWith('/products/eid-panjabi')
        ->and($post->message)->toContain('Eid Panjabi')
        ->and($post->facebook_post_id)->toBe('page-1_200')
        ->and($post->instagram_media_id)->toBe('ig-media-9')
        ->and(postedTo('/page-1/photos')->data()['url'])->toBe('https://cdn.example.test/panjabi.jpg')
        ->and(postedTo('/ig-1/media')->data()['image_url'])->toBe('https://cdn.example.test/panjabi.jpg')
        ->and(postedTo('/ig-1/media_publish')->data())->toBe(['creation_id' => 'container-1']);
});

it('marks a post partly published when one network fails, and retries only that one', function (): void {
    [$user] = postsContext();
    fakeMetaPosting(instagramFailsFirst: true);

    $this->withoutMiddleware()->actingAs($user)->post(route('user.meta-social.posts.store'), [
        'page_id' => 'page-1', 'targets' => ['facebook', 'instagram'], 'instagram_account_id' => 'ig-1',
        'message' => 'New arrivals', 'image_url' => 'https://cdn.example.test/new.jpg',
    ])->assertRedirect();

    $post = MetaPagePost::query()->sole();
    expect($post->status)->toBe(MetaPagePost::STATUS_PARTIAL)
        ->and($post->facebook_post_id)->toBe('page-1_200')
        ->and($post->errors['instagram'])->toContain('Only photo or video');

    $this->withoutMiddleware()->actingAs($user)->post(route('user.meta-social.posts.retry', $post))->assertRedirect();

    expect($post->refresh()->status)->toBe(MetaPagePost::STATUS_PUBLISHED)
        ->and($post->instagram_media_id)->toBe('ig-media-9')
        ->and(Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/page-1/photos')))->toHaveCount(1);
});

it('needs a photo for Instagram posts', function (): void {
    [$user] = postsContext();
    Http::fake();

    $this->withoutMiddleware()->actingAs($user)->post(route('user.meta-social.posts.store'), [
        'page_id' => 'page-1', 'targets' => ['instagram'], 'instagram_account_id' => 'ig-1', 'message' => 'No photo here',
    ])->assertSessionHasErrors('image_url');

    expect(MetaPagePost::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('waits until the scheduled time, then the scheduler publishes the post', function (): void {
    [$user] = postsContext();
    fakeMetaPosting();

    $this->withoutMiddleware()->actingAs($user)->post(route('user.meta-social.posts.store'), [
        'page_id' => 'page-1', 'targets' => ['facebook'], 'message' => 'Friday deals', 'scheduled_at' => now()->addHour()->format('Y-m-d H:i'),
    ])->assertRedirect();

    $post = MetaPagePost::query()->sole();
    expect($post->status)->toBe(MetaPagePost::STATUS_SCHEDULED)
        ->and(app(MetaPagePostService::class)->publishDue())->toBe(0);
    Http::assertNothingSent();

    $this->travel(2)->hours();

    expect(app(MetaPagePostService::class)->publishDue())->toBe(1)
        ->and($post->refresh()->status)->toBe(MetaPagePost::STATUS_PUBLISHED);
});

it('rejects Pages that are not connected to the workspace', function (): void {
    [$user] = postsContext();
    Http::fake();

    $this->withoutMiddleware()->actingAs($user)->post(route('user.meta-social.posts.store'), [
        'page_id' => 'someone-elses-page', 'targets' => ['facebook'], 'message' => 'Hi',
    ])->assertSessionHasErrors('page_id');

    Http::assertNothingSent();
});

it('deletes the Facebook post and keeps other workspaces\' posts private', function (): void {
    [$user, $workspace] = postsContext();
    $post = MetaPagePost::query()->create([
        'workspace_id' => $workspace->id, 'page_id' => 'page-1', 'targets' => ['facebook'], 'message' => 'Old post',
        'status' => 'published', 'facebook_post_id' => 'page-1_300',
    ]);
    [$stranger] = postsContext();
    fakeMetaPosting();

    $this->withoutMiddleware()->actingAs($stranger)->delete(route('user.meta-social.posts.destroy', $post))->assertNotFound();
    $this->withoutMiddleware()->actingAs($user)->delete(route('user.meta-social.posts.destroy', $post))->assertRedirect();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_ends_with($request->url(), '/page-1_300'));
    expect(MetaPagePost::query()->count())->toBe(0);
});

it('shows what is live on the Page and Instagram', function (): void {
    [$user] = postsContext();
    Http::fake([
        'graph.facebook.com/*/page-1/published_posts*' => Http::response(['data' => [['id' => 'page-1_1', 'message' => 'Live on Facebook', 'permalink_url' => 'https://facebook.com/p/1']]]),
        'graph.facebook.com/*/ig-1/media*' => Http::response(['data' => [['id' => 'm1', 'caption' => 'Live on Instagram', 'permalink' => 'https://instagram.com/p/1', 'media_url' => 'https://cdn.example.test/i.jpg']]]),
    ]);

    $this->withoutMiddleware()->actingAs($user)
        ->get(route('user.meta-social.posts.index', ['recent' => 1, 'page_id' => 'page-1', 'instagram_account_id' => 'ig-1']))
        ->assertOk()
        ->assertSee('Live on Facebook')
        ->assertSee('Live on Instagram');
});

it('lists Messenger offer subscribers and saves the offer settings', function (): void {
    [$user, $workspace, $page] = postsContext();
    $contact = Contact::query()->create(['workspace_id' => $workspace->id, 'name' => 'Nadia Rahman']);
    MessengerMarketingOptin::query()->create([
        'workspace_id' => $workspace->id, 'channel_account_id' => $page->id, 'contact_id' => $contact->id, 'psid' => '555',
        'topic' => 'offers', 'token' => 'tok', 'status' => 'active', 'opted_in_at' => now(), 'frequency' => 'WEEKLY',
    ]);

    $web = $this->withoutMiddleware([EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class])->actingAs($user);

    $web->get(route('user.meta-social.offers.index'))
        ->assertOk()
        ->assertSee('Nadia Rahman')
        ->assertSee('1 subscribed customer')
        ->assertSee(route('user.campaigns.create', ['channel_account_id' => $page->id]), false);

    $web->from(route('user.meta-social.offers.index'))
        ->put(route('user.meta-social.setup.offers', $page), ['title' => 'Weekly Eid deals', 'auto_request' => '1'])
        ->assertRedirect(route('user.meta-social.offers.index'));

    expect(data_get($page->refresh()->settings, 'marketing_messages'))->toEqual(['auto_request' => true, 'title' => 'Weekly Eid deals']);
});
