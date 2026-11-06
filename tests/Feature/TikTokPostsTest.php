<?php

use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Models\User;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\TikTok\Models\TikTokPost;
use App\Modules\TikTok\Services\TikTokPostService;
use App\Modules\TikTok\Services\TikTokSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: ChannelAccount}
 */
function tiktokPostingContext(string $scope = 'user.info.basic,video.upload,video.publish'): array
{
    app(TikTokSettingsService::class)->update(['tiktok_client_key' => 'ck-123', 'tiktok_client_secret' => 'cs-secret']);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $account = ChannelAccount::query()->create([
        'workspace_id' => $workspace->id, 'provider' => 'tiktok', 'name' => 'Acme TikTok', 'status' => 'connected',
        'credentials' => ['access_token' => 'tt-access', 'refresh_token' => 'tt-refresh', 'open_id' => 'open-1', 'expires_at' => now()->addDay()->toIso8601String(), 'refresh_expires_at' => now()->addYear()->toIso8601String()],
        'webhook_verify_token' => 'x', 'provider_account_id' => 'open-1', 'settings' => ['scope' => $scope], 'connected_at' => now(),
    ]);

    return [$user, $account];
}

/**
 * @param  array<string, mixed>  $statusData
 */
function fakeTikTok(array $statusData = ['status' => 'PUBLISH_COMPLETE', 'publicaly_available_post_id' => [7300000000000000001]]): void
{
    Http::fake([
        'open.tiktokapis.com/v2/post/publish/creator_info/query/' => Http::response(['data' => [
            'creator_nickname' => 'Acme Fashion', 'creator_username' => 'acme', 'creator_avatar_url' => 'https://p16.tiktokcdn.test/a.jpg',
            'privacy_level_options' => ['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'SELF_ONLY'],
            'comment_disabled' => false, 'duet_disabled' => true, 'stitch_disabled' => false, 'max_video_post_duration_sec' => 600,
        ], 'error' => ['code' => 'ok']]),
        'open.tiktokapis.com/v2/post/publish/video/init/' => Http::response(['data' => ['publish_id' => 'v_pub_1', 'upload_url' => 'https://open-upload.tiktokapis.test/upload?id=1'], 'error' => ['code' => 'ok']]),
        'open.tiktokapis.com/v2/post/publish/inbox/video/init/' => Http::response(['data' => ['publish_id' => 'v_inbox_1', 'upload_url' => 'https://open-upload.tiktokapis.test/upload?id=2'], 'error' => ['code' => 'ok']]),
        'open-upload.tiktokapis.test/*' => Http::response('', 201),
        'open.tiktokapis.com/v2/post/publish/status/fetch/' => Http::response(['data' => $statusData, 'error' => ['code' => 'ok']]),
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function tiktokDirectPayload(ChannelAccount $account, array $overrides = []): array
{
    return array_merge([
        'channel_account_id' => $account->id,
        'mode' => 'direct',
        'video' => UploadedFile::fake()->createWithContent('eid-look.mp4', str_repeat('v', 4096)),
        'title' => 'Eid look #panjabi',
        'privacy_level' => 'PUBLIC_TO_EVERYONE',
        'allow_comment' => '1',
        'allow_duet' => '1',
        'allow_stitch' => '0',
        'disclose_commercial' => '1',
        'brand_organic' => '1',
        'brand_content' => '0',
        'consent' => '1',
    ], $overrides);
}

beforeEach(function (): void {
    Storage::fake('local');
});

it('shows the TikTok creator, privacy choices and interaction settings before posting', function (): void {
    [$user] = tiktokPostingContext();
    fakeTikTok();

    $this->withoutMiddleware([EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class])->actingAs($user)
        ->get(route('user.tiktok.posts.create'))
        ->assertOk()
        ->assertSee('Acme Fashion')
        ->assertSee('Everyone')
        ->assertSee('Only me')
        ->assertSee('Music Usage Confirmation')
        ->assertSee('TikTok posts');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'creator_info/query') && $request->hasHeader('Authorization', 'Bearer tt-access'));
});

it('posts a video directly to TikTok and records when it is published', function (): void {
    [$user, $account] = tiktokPostingContext();
    fakeTikTok();

    $this->withoutMiddleware()->actingAs($user)->post(route('user.tiktok.posts.store'), tiktokDirectPayload($account))
        ->assertRedirect(route('user.tiktok.posts.index'));

    $post = TikTokPost::query()->sole();
    expect($post->status)->toBe(TikTokPost::STATUS_PROCESSING)
        ->and($post->publish_id)->toBe('v_pub_1');

    $init = Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'post/publish/video/init'))->first()[0]->data();
    expect($init['post_info'])->toMatchArray([
        'title' => 'Eid look #panjabi',
        'privacy_level' => 'PUBLIC_TO_EVERYONE',
        'disable_comment' => false,
        'disable_duet' => true,
        'disable_stitch' => true,
        'brand_organic_toggle' => true,
        'brand_content_toggle' => false,
    ])->and($init['source_info'])->toBe(['source' => 'FILE_UPLOAD', 'video_size' => 4096, 'chunk_size' => 4096, 'total_chunk_count' => 1]);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && str_contains($request->url(), 'open-upload.tiktokapis.test/upload?id=1')
        && $request->header('Content-Range')[0] === 'bytes 0-4095/4096'
        && strlen($request->body()) === 4096);

    expect(app(TikTokPostService::class)->refreshPending())->toBe(1);
    $post->refresh();
    expect($post->status)->toBe(TikTokPost::STATUS_PUBLISHED)
        ->and($post->tiktok_post_id)->toBe('7300000000000000001')
        ->and($post->video_path)->toBeNull();
});

it('sends a video to the TikTok inbox as a draft', function (): void {
    [$user, $account] = tiktokPostingContext('user.info.basic,video.upload');
    fakeTikTok(['status' => 'SEND_TO_USER_INBOX']);

    $this->withoutMiddleware()->actingAs($user)->post(route('user.tiktok.posts.store'), [
        'channel_account_id' => $account->id, 'mode' => 'draft', 'title' => 'Draft',
        'video' => UploadedFile::fake()->createWithContent('draft.mov', str_repeat('v', 2048)),
    ])->assertRedirect();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'inbox/video/init'));
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'creator_info'));

    app(TikTokPostService::class)->refreshPending();
    expect(TikTokPost::query()->sole()->status)->toBe(TikTokPost::STATUS_IN_INBOX);
});

it('enforces TikTok posting rules', function (array $overrides, string $errorField): void {
    [$user, $account] = tiktokPostingContext();
    fakeTikTok();

    $this->withoutMiddleware()->actingAs($user)->post(route('user.tiktok.posts.store'), tiktokDirectPayload($account, $overrides))
        ->assertSessionHasErrors($errorField);

    expect(TikTokPost::query()->count())->toBe(0);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'video/init'));
})->with([
    'no privacy chosen' => [['privacy_level' => null], 'privacy_level'],
    'privacy the creator cannot use' => [['privacy_level' => 'FOLLOWER_OF_CREATOR'], 'privacy_level'],
    'branded content set to private' => [['privacy_level' => 'SELF_ONLY', 'brand_content' => '1'], 'privacy_level'],
    'disclosure on with nothing chosen' => [['brand_organic' => '0', 'brand_content' => '0'], 'disclose_commercial'],
    'music consent missing' => [['consent' => null], 'consent'],
    'not a video' => [['video' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')], 'video'],
]);

it('refuses direct posts when the connection lacks video.publish', function (): void {
    [$user, $account] = tiktokPostingContext('user.info.basic,video.upload');
    fakeTikTok();

    $this->withoutMiddleware()->actingAs($user)->post(route('user.tiktok.posts.store'), tiktokDirectPayload($account))
        ->assertSessionHasErrors('mode');

    Http::assertNothingSent();
});

it('explains why TikTok rejected a video', function (): void {
    [$user, $account] = tiktokPostingContext();
    fakeTikTok(['status' => 'FAILED', 'fail_reason' => 'duration_check_failed']);

    $this->withoutMiddleware()->actingAs($user)->post(route('user.tiktok.posts.store'), tiktokDirectPayload($account))->assertRedirect();
    app(TikTokPostService::class)->refreshPending();

    $post = TikTokPost::query()->sole();
    expect($post->status)->toBe(TikTokPost::STATUS_FAILED)
        ->and($post->fail_reason)->toBe('The video is longer than this account is allowed to post.');
});

it('splits large videos into TikTok upload chunks', function (): void {
    $service = app(TikTokPostService::class);
    $mb = 1024 * 1024;

    expect($service->chunkPlan(30 * $mb))->toBe(['video_size' => 30 * $mb, 'chunk_size' => 30 * $mb, 'total_chunk_count' => 1])
        ->and($service->chunkPlan(105 * $mb))->toBe(['video_size' => 105 * $mb, 'chunk_size' => 10 * $mb, 'total_chunk_count' => 10]);
});

it('keeps each workspace\'s TikTok posts private', function (): void {
    [, $account] = tiktokPostingContext();
    $post = TikTokPost::query()->create([
        'workspace_id' => $account->workspace_id, 'channel_account_id' => $account->id, 'mode' => 'draft', 'status' => 'failed',
    ]);
    [$stranger] = tiktokPostingContext();

    $this->withoutMiddleware()->actingAs($stranger)->delete(route('user.tiktok.posts.destroy', $post))->assertNotFound();
    expect(TikTokPost::query()->count())->toBe(1);
});
