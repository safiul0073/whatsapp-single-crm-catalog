<?php

use App\Models\User;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\SocialWidgets\Models\SocialWidget;
use App\Modules\SocialWidgets\Support\SocialWidgetSettings;
use App\Modules\Workspaces\Enums\WorkspaceMemberStatus;
use App\Modules\Workspaces\Models\WorkspaceRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Permission::findOrCreate('meta-social.manage', 'web');
    $this->merchant = User::factory()->create();
    $this->merchant->givePermissionTo('meta-social.manage');
    $this->workspace = app(WorkspaceResolver::class)->current($this->merchant);
    $this->actingAs($this->merchant);
});

function socialWidgetFor(int $workspaceId, array $attributes = []): SocialWidget
{
    return SocialWidget::create($attributes + [
        'workspace_id' => $workspaceId,
        'provider' => 'instagram',
        'name' => 'Store feed',
        'layout' => 'default',
        'settings' => SocialWidgetSettings::defaults(),
    ]);
}

function connectedInstagram(int $workspaceId): ChannelAccount
{
    return ChannelAccount::create([
        'workspace_id' => $workspaceId,
        'provider' => 'instagram',
        'name' => 'yourbrand',
        'status' => ChannelAccountStatus::Connected->value,
        'credentials' => ['access_token' => 'token-123'],
        'provider_account_id' => '17841400000000000',
        'settings' => ['instagram_account_id' => '17841400000000000'],
    ]);
}

it('shows the layout picker with the recommended layout featured', function (): void {
    $response = $this->get(route('user.social-widgets.layouts', 'instagram'))->assertOk();

    $response->assertSee('Choose a layout.')
        ->assertSee('data-layout-featured="default"', false)
        ->assertSee('TikTok');
    expect(substr_count($response->getContent(), 'data-layout-card'))->toBe(10);
});

it('forbids the widget pages for staff without the instagram permission', function (): void {
    $staff = User::factory()->create();
    $role = WorkspaceRole::create(['workspace_id' => $this->workspace->id, 'name' => 'Support', 'is_system' => false]);
    $this->workspace->members()->attach($staff->id, [
        'workspace_role_id' => $role->id,
        'status' => WorkspaceMemberStatus::Active->value,
    ]);

    $this->actingAs($staff)
        ->withSession(['active_workspace_id' => $this->workspace->id])
        ->get(route('user.social-widgets.layouts', 'instagram'))
        ->assertForbidden();
});

it('creates a workspace scoped draft from the chosen layout and opens the editor', function (): void {
    $response = $this->post(route('user.social-widgets.store', 'instagram'), ['layout' => 'masonry']);

    $widget = SocialWidget::sole();
    $response->assertRedirect(route('user.social-widgets.edit', $widget));
    expect($widget->workspace_id)->toBe($this->workspace->id)
        ->and($widget->layout->value)->toBe('masonry')
        ->and($widget->status)->toBe('draft')
        ->and($widget->public_token)->toHaveLength(40);

    $this->get(route('user.social-widgets.edit', $widget))->assertOk()->assertSee('data-config', false);
});

it('rejects unknown layouts', function (): void {
    $this->post(route('user.social-widgets.store', 'instagram'), ['layout' => 'carousel-3d'])->assertSessionHasErrors('layout');
});

it('saves editor settings and drops keys outside the settings schema', function (): void {
    $widget = socialWidgetFor($this->workspace->id);
    $settings = SocialWidgetSettings::defaults();
    $settings['columns']['gap'] = 12;
    $settings['style']['colors']['headerButton'] = '#ff0066';
    $settings['injected'] = ['script' => 'alert(1)'];

    $this->putJson(route('user.social-widgets.update', $widget), ['name' => 'Home feed', 'layout' => 'bento', 'settings' => $settings])
        ->assertOk()
        ->assertJsonPath('saved', true);

    $widget->refresh();
    expect($widget->name)->toBe('Home feed')
        ->and($widget->layout->value)->toBe('bento')
        ->and($widget->settings['columns']['gap'])->toBe(12)
        ->and($widget->settings['style']['colors']['headerButton'])->toBe('#ff0066')
        ->and($widget->settings)->not->toHaveKey('injected');
});

it('validates colours and theme presets', function (): void {
    $widget = socialWidgetFor($this->workspace->id);
    $settings = SocialWidgetSettings::defaults();
    $settings['style']['colors']['postText'] = 'red;background:url(x)';
    $settings['style']['theme'] = 'vaporwave';

    $this->putJson(route('user.social-widgets.update', $widget), ['name' => 'Feed', 'layout' => 'default', 'settings' => $settings])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['settings.style.colors.postText', 'settings.style.theme']);
});

it('hides widgets from other workspaces', function (): void {
    $otherWorkspace = app(WorkspaceResolver::class)->current(User::factory()->create());
    $foreign = socialWidgetFor($otherWorkspace->id);

    $this->get(route('user.social-widgets.edit', $foreign))->assertNotFound();
    $this->putJson(route('user.social-widgets.update', $foreign), ['name' => 'x', 'layout' => 'default', 'settings' => SocialWidgetSettings::defaults()])->assertNotFound();
    $this->delete(route('user.social-widgets.destroy', $foreign))->assertNotFound();
    expect(SocialWidget::count())->toBe(1);
});

it('serves the public config only after publishing', function (): void {
    $widget = socialWidgetFor($this->workspace->id);

    $this->getJson(route('widgets.feed.config', $widget->public_token))->assertNotFound();

    $this->postJson(route('user.social-widgets.publish', $widget))
        ->assertOk()
        ->assertJsonPath('published', true)
        ->assertJsonFragment(['embed_code' => '<div data-social-feed="'.$widget->public_token.'"></div>'."\n".'<script src="'.route('widgets.feed.loader', $widget->public_token).'" async></script>']);

    $this->getJson(route('widgets.feed.config', $widget->public_token))
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertJsonPath('data.layout', 'default')
        ->assertJsonPath('data.profile.username', 'yourbrand')
        ->assertJsonCount(10, 'data.posts');
});

it('uses the connected instagram account and falls back to the last good copy on graph errors', function (): void {
    $account = connectedInstagram($this->workspace->id);
    $widget = socialWidgetFor($this->workspace->id, ['status' => 'published']);
    Http::fakeSequence('graph.facebook.com/*')
        ->push(['username' => 'realbrand', 'name' => 'Real Brand', 'followers_count' => 900, 'follows_count' => 12, 'media_count' => 1])
        ->push(['data' => [['id' => '1', 'caption' => 'Fresh drop', 'media_type' => 'IMAGE', 'media_url' => 'https://cdn.example/1.jpg', 'permalink' => 'https://instagram.com/p/1', 'like_count' => 7, 'comments_count' => 2]]])
        ->push(['error' => ['message' => 'Rate limited']], 429)
        ->push(['error' => ['message' => 'Rate limited']], 429);

    $this->getJson(route('widgets.feed.config', $widget->public_token))
        ->assertOk()
        ->assertJsonPath('data.profile.username', 'realbrand')
        ->assertJsonPath('data.posts.0.caption', 'Fresh drop')
        ->assertJsonPath('data.posts.0.likes', 7);

    cache()->forget('social-widgets:instagram:'.$account->id.':self:36');

    $this->getJson(route('widgets.feed.config', $widget->public_token))
        ->assertOk()
        ->assertJsonPath('data.profile.username', 'realbrand');
});

it('searches another account through business discovery', function (): void {
    connectedInstagram($this->workspace->id);
    $widget = socialWidgetFor($this->workspace->id);
    Http::fake(['graph.facebook.com/*' => Http::response(['business_discovery' => [
        'username' => 'otherbrand',
        'followers_count' => 50,
        'media' => ['data' => [['id' => '9', 'caption' => 'Hello', 'media_type' => 'VIDEO', 'media_url' => 'https://cdn.example/9.mp4', 'thumbnail_url' => 'https://cdn.example/9.jpg']]],
    ]])]);

    $this->postJson(route('user.social-widgets.source', $widget), ['username' => '@otherbrand'])
        ->assertOk()
        ->assertJsonPath('feed.source', 'search')
        ->assertJsonPath('feed.profile.username', 'otherbrand')
        ->assertJsonPath('feed.posts.0.type', 'video')
        ->assertJsonPath('feed.posts.0.image', 'https://cdn.example/9.jpg');

    expect($widget->fresh()->settings['source']['username'])->toBe('otherbrand');
    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'business_discovery.username(otherbrand)'));
});

it('shows sample posts with a hint when searching without a connected account', function (): void {
    $widget = socialWidgetFor($this->workspace->id);

    $this->postJson(route('user.social-widgets.source', $widget), ['username' => 'otherbrand'])
        ->assertOk()
        ->assertJsonPath('feed.source', 'demo')
        ->assertJsonPath('message', 'Connect an Instagram business account to search usernames. Showing sample posts for now.');
});

it('adds a widgets link to the instagram channel card', function (): void {
    Permission::findOrCreate('channels.manage', 'web');

    $this->get(route('user.channels.index'))
        ->assertOk()
        ->assertSee('data-channel-widgets-link="instagram"', false);
});

it('lists working widgets in the library with a hover preview', function (): void {
    $this->get(route('user.social-widgets.library'))
        ->assertOk()
        ->assertSee('Widget library')
        ->assertSee('data-widget-card="instagram-feed"', false)
        ->assertSee('data-widget-item', false)
        ->assertSee('x-ref="preview"', false)
        ->assertSee(route('user.social-widgets.layouts', 'instagram'), false)
        ->assertDontSee('Coming soon');
});
