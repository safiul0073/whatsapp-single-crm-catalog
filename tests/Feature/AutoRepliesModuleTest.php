<?php

use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Models\User;
use App\Modules\Automations\Services\AutomationDispatcher;
use App\Modules\AutoReplies\Models\AutoReplyRule;
use App\Modules\AutoReplies\Services\AutoReplyService;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Jobs\ProcessChannelWebhookJob;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Models\ChannelWebhookEvent;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Media\Models\Media;
use App\Modules\MessageTemplates\Enums\MessageTemplateStatus;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('lets a user create and manage auto reply rules', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->withoutMiddleware([Authorize::class, EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class])
        ->actingAs($user)
        ->post(route('user.auto-replies.store'), [
            'name' => 'Menu request',
            'trigger_type' => 'keyword',
            'trigger_value' => 'menu, catalog',
            'match_type' => 'contains',
            'reply_type' => 'text',
            'reply_text' => 'Here is our latest menu.',
            'priority' => 2,
            'is_active' => '1',
        ])
        ->assertRedirect(route('user.auto-replies.index'))
        ->assertSessionHas('status', 'Auto-reply rule saved.');

    $rule = AutoReplyRule::query()->firstOrFail();

    expect($rule)
        ->workspace_id->toBe(app(WorkspaceResolver::class)->current($user)->id)
        ->name->toBe('Menu request')
        ->trigger_type->toBe('keyword')
        ->trigger_value->toBe('menu, catalog')
        ->match_type->toBe('contains')
        ->reply_type->toBe('text')
        ->reply_text->toBe('Here is our latest menu.')
        ->priority->toBe(2)
        ->is_active->toBeTrue();

    $this->withoutMiddleware([Authorize::class, EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class])
        ->actingAs($user)
        ->get(route('user.auto-replies.index'))
        ->assertOk()
        ->assertSee('Menu request')
        ->assertSee('Here is our latest menu.')
        ->assertSee('priority 2');

    $this->withoutMiddleware([Authorize::class, EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class])
        ->actingAs($user)
        ->put(route('user.auto-replies.update', $rule), [
            'name' => 'Opening hours',
            'trigger_type' => 'out_of_hours',
            'trigger_value' => null,
            'match_type' => null,
            'reply_type' => 'text',
            'reply_text' => 'We are closed right now and will reply tomorrow.',
            'priority' => 9,
            'is_active' => '1',
        ])
        ->assertRedirect(route('user.auto-replies.index'))
        ->assertSessionHas('status', 'Auto-reply rule updated.');

    $rule->refresh();

    expect($rule)
        ->name->toBe('Opening hours')
        ->trigger_type->toBe('out_of_hours')
        ->trigger_value->toBeNull()
        ->priority->toBe(9);

    $this->withoutMiddleware([Authorize::class, EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class])
        ->actingAs($user)
        ->patch(route('user.auto-replies.toggle', $rule))
        ->assertRedirect()
        ->assertSessionHas('status', 'Auto-reply rule disabled.');

    expect($rule->refresh()->is_active)->toBeFalse();

    $this->withoutMiddleware([Authorize::class, EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class])
        ->actingAs($user)
        ->delete(route('user.auto-replies.destroy', $rule))
        ->assertRedirect()
        ->assertSessionHas('status', 'Auto-reply rule deleted.');

    expect(AutoReplyRule::query()->exists())->toBeFalse();
});

it('requires keywords for keyword triggered auto replies', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->withoutMiddleware([Authorize::class, EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class])
        ->actingAs($user)
        ->from(route('user.auto-replies.create'))
        ->post(route('user.auto-replies.store'), [
            'name' => 'Missing keyword',
            'trigger_type' => 'keyword',
            'reply_type' => 'text',
            'reply_text' => 'Thanks for messaging.',
            'priority' => 10,
            'is_active' => '1',
        ])
        ->assertRedirect(route('user.auto-replies.create'))
        ->assertSessionHasErrors('trigger_value');
});

it('stores approved template response payloads', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $template = MessageTemplate::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'whatsapp',
        'name' => 'welcome_offer',
        'language' => 'en_US',
        'category' => 'utility',
        'status' => MessageTemplateStatus::Approved,
        'components' => [],
    ]);

    $this->withoutMiddleware([Authorize::class, EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class])
        ->actingAs($user)
        ->post(route('user.auto-replies.store'), [
            'name' => 'Welcome template',
            'trigger_type' => 'welcome',
            'reply_type' => 'template',
            'message_template_id' => $template->id,
            'priority' => 1,
            'is_active' => '1',
        ])
        ->assertRedirect(route('user.auto-replies.index'));

    $rule = AutoReplyRule::query()->firstOrFail();

    expect($rule->reply_type)->toBe('template')
        ->and($rule->reply_payload['message_template_id'])->toBe($template->id)
        ->and($rule->reply_payload['template_name'])->toBe('welcome_offer')
        ->and($rule->reply_payload['language'])->toBe('en_US');
});

it('stores media response payloads from library or url', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $media = Media::query()->create([
        'name' => 'catalog',
        'file_name' => 'catalog.pdf',
        'original_name' => 'catalog.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'type' => 'document',
        'size' => 1234,
        'disk' => 'public',
        'path' => 'media/catalog.pdf',
        'uploaded_by' => $user->id,
    ]);

    $this->withoutMiddleware([Authorize::class, EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class])
        ->actingAs($user)
        ->post(route('user.auto-replies.store'), [
            'name' => 'Catalog media',
            'trigger_type' => 'keyword',
            'trigger_value' => 'catalog',
            'match_type' => 'exact',
            'reply_type' => 'media',
            'media_id' => $media->id,
            'media_caption' => 'Here is the catalog.',
            'priority' => 3,
            'is_active' => '1',
        ])
        ->assertRedirect(route('user.auto-replies.index'));

    $libraryRule = AutoReplyRule::query()->firstOrFail();

    expect($libraryRule->reply_payload['media_id'])->toBe($media->id)
        ->and($libraryRule->reply_payload['type'])->toBe('document')
        ->and($libraryRule->reply_payload['caption'])->toBe('Here is the catalog.');

    $this->withoutMiddleware([Authorize::class, EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class])
        ->actingAs($user)
        ->post(route('user.auto-replies.store'), [
            'name' => 'Image media',
            'trigger_type' => 'keyword',
            'trigger_value' => 'photo',
            'match_type' => 'contains',
            'reply_type' => 'media',
            'media_url' => 'https://example.com/photo.jpg',
            'media_type' => 'image',
            'priority' => 4,
            'is_active' => '1',
        ])
        ->assertRedirect(route('user.auto-replies.index'));

    $urlRule = AutoReplyRule::query()->where('name', 'Image media')->firstOrFail();

    expect($urlRule->reply_payload['media_source'])->toBe('url')
        ->and($urlRule->reply_payload['url'])->toBe('https://example.com/photo.jpg')
        ->and($urlRule->reply_payload['type'])->toBe('image');
});

it('validates template and media response requirements', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->withoutMiddleware([Authorize::class, EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class])
        ->actingAs($user)
        ->from(route('user.auto-replies.create'))
        ->post(route('user.auto-replies.store'), [
            'name' => 'Missing template',
            'trigger_type' => 'welcome',
            'reply_type' => 'template',
            'priority' => 10,
            'is_active' => '1',
        ])
        ->assertRedirect(route('user.auto-replies.create'))
        ->assertSessionHasErrors('message_template_id');

    $this->withoutMiddleware([Authorize::class, EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class])
        ->actingAs($user)
        ->from(route('user.auto-replies.create'))
        ->post(route('user.auto-replies.store'), [
            'name' => 'Missing media',
            'trigger_type' => 'keyword',
            'trigger_value' => 'file',
            'match_type' => 'contains',
            'reply_type' => 'media',
            'priority' => 10,
            'is_active' => '1',
        ])
        ->assertRedirect(route('user.auto-replies.create'))
        ->assertSessionHasErrors(['media_id', 'media_url']);
});

it('matches contains exact regex priority and fallback rules when webhooks are processed', function (): void {
    Http::fake(['https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.auto-reply']]], 200)]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $account = autoReplyWhatsappAccount($workspace->id);

    AutoReplyRule::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Contains menu',
        'trigger_type' => 'keyword',
        'trigger_value' => 'menu',
        'match_type' => 'contains',
        'reply_type' => 'text',
        'reply_text' => 'Contains reply',
        'reply_payload' => ['text' => 'Contains reply'],
        'priority' => 5,
        'is_active' => true,
    ]);
    AutoReplyRule::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Exact menu',
        'trigger_type' => 'keyword',
        'trigger_value' => 'menu',
        'match_type' => 'exact',
        'reply_type' => 'text',
        'reply_text' => 'Exact reply',
        'reply_payload' => ['text' => 'Exact reply'],
        'priority' => 1,
        'is_active' => true,
    ]);
    AutoReplyRule::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Regex order',
        'trigger_type' => 'keyword',
        'trigger_value' => 'ord(er)?\\s+\\d+',
        'match_type' => 'regex',
        'reply_type' => 'text',
        'reply_text' => 'Regex reply',
        'reply_payload' => ['text' => 'Regex reply'],
        'priority' => 2,
        'is_active' => true,
    ]);
    AutoReplyRule::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Fallback',
        'trigger_type' => 'fallback',
        'match_type' => 'contains',
        'reply_type' => 'text',
        'reply_text' => 'Fallback reply',
        'reply_payload' => ['text' => 'Fallback reply'],
        'priority' => 999,
        'is_active' => true,
    ]);

    processAutoReplyPayload($account, 'msg-1', 'menu');
    processAutoReplyPayload($account, 'msg-2', 'please send menu');
    processAutoReplyPayload($account, 'msg-3', 'order 123');
    processAutoReplyPayload($account, 'msg-4', 'unknown');

    $outboundBodies = Message::query()
        ->where('workspace_id', $workspace->id)
        ->where('direction', 'outbound')
        ->orderBy('id')
        ->pluck('body')
        ->all();

    expect($outboundBodies)->toBe([
        'Exact reply',
        'Contains reply',
        'Regex reply',
        'Fallback reply',
    ]);
});

it('sends one auto reply for a duplicated webhook event', function (): void {
    Http::fake(['https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.duplicate-reply']]], 200)]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $account = autoReplyWhatsappAccount($workspace->id);

    AutoReplyRule::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Menu',
        'trigger_type' => 'keyword',
        'trigger_value' => 'menu',
        'match_type' => 'contains',
        'reply_type' => 'text',
        'reply_text' => 'Here is the menu.',
        'reply_payload' => ['text' => 'Here is the menu.'],
        'priority' => 1,
        'is_active' => true,
    ]);

    $event = ChannelWebhookEvent::query()->create([
        'channel_account_id' => $account->id,
        'workspace_id' => $workspace->id,
        'provider' => 'whatsapp',
        'event_type' => 'message.received',
        'provider_event_id' => 'duplicate-event',
        'payload' => autoReplyWebhookPayload($account, 'duplicate-message', 'menu'),
        'status' => 'pending',
    ]);

    $job = new ProcessChannelWebhookJob($event->id);
    $job->handle(app(ChannelManager::class), app(AutomationDispatcher::class), app(AutoReplyService::class));
    $job->handle(app(ChannelManager::class), app(AutomationDispatcher::class), app(AutoReplyService::class));

    expect(Message::query()->where('direction', 'outbound')->count())->toBe(1);
});

function autoReplyWhatsappAccount(int $workspaceId): ChannelAccount
{
    return ChannelAccount::query()->create([
        'workspace_id' => $workspaceId,
        'provider' => 'whatsapp',
        'name' => 'WhatsApp',
        'status' => ChannelAccountStatus::Connected,
        'credentials' => ['access_token' => 'test-token'],
        'webhook_verify_token' => 'verify-token',
        'provider_account_id' => 'waba-123',
        'provider_phone_id' => 'phone-123',
        'provider_display_id' => '+15551234567',
        'connected_at' => now(),
    ]);
}

function processAutoReplyPayload(ChannelAccount $account, string $messageId, string $body): void
{
    $event = ChannelWebhookEvent::query()->create([
        'channel_account_id' => $account->id,
        'workspace_id' => $account->workspace_id,
        'provider' => 'whatsapp',
        'event_type' => 'message.received',
        'provider_event_id' => $messageId,
        'payload' => autoReplyWebhookPayload($account, $messageId, $body),
        'status' => 'pending',
    ]);

    (new ProcessChannelWebhookJob($event->id))->handle(
        app(ChannelManager::class),
        app(AutomationDispatcher::class),
        app(AutoReplyService::class),
    );
}

function autoReplyWebhookPayload(ChannelAccount $account, string $messageId, string $body): array
{
    return [
        'entry' => [[
            'id' => $account->provider_account_id,
            'changes' => [[
                'value' => [
                    'metadata' => ['phone_number_id' => $account->provider_phone_id],
                    'messages' => [[
                        'id' => $messageId,
                        'from' => '15558675309',
                        'type' => 'text',
                        'text' => ['body' => $body],
                        'profile' => ['name' => 'Customer'],
                    ]],
                ],
            ]],
        ]],
    ];
}

function autoReplyMenuRule(int $workspaceId): AutoReplyRule
{
    return AutoReplyRule::query()->create([
        'workspace_id' => $workspaceId,
        'name' => 'Menu',
        'trigger_type' => 'keyword',
        'trigger_value' => 'menu',
        'match_type' => 'contains',
        'reply_type' => 'text',
        'reply_text' => 'Here is the menu.',
        'reply_payload' => ['text' => 'Here is the menu.'],
        'priority' => 1,
        'is_active' => true,
    ]);
}

function autoReplyChannelAccount(int $workspaceId, string $provider, string $accountId): ChannelAccount
{
    return ChannelAccount::query()->create([
        'workspace_id' => $workspaceId,
        'provider' => $provider,
        'name' => ucfirst($provider),
        'status' => ChannelAccountStatus::Connected,
        'credentials' => ['access_token' => 'test-token'],
        'webhook_verify_token' => 'verify-token',
        'provider_account_id' => $accountId,
        'connected_at' => now(),
    ]);
}

function processAutoReplyWebhook(ChannelAccount $account, string $eventId, array $payload): ChannelWebhookEvent
{
    $event = ChannelWebhookEvent::query()->create([
        'channel_account_id' => $account->id,
        'workspace_id' => $account->workspace_id,
        'provider' => $account->provider,
        'event_type' => 'message.received',
        'provider_event_id' => $eventId,
        'payload' => $payload,
        'status' => 'pending',
    ]);

    (new ProcessChannelWebhookJob($event->id))->handle(
        app(ChannelManager::class),
        app(AutomationDispatcher::class),
        app(AutoReplyService::class),
    );

    return $event->fresh();
}

it('auto replies on messenger and instagram to the sender id', function (string $provider, string $sendPath): void {
    Http::fake(['https://graph.facebook.com/*' => Http::response(['recipient_id' => 'psid-42', 'message_id' => 'mid.reply'], 200)]);
    $workspace = app(WorkspaceResolver::class)->current(User::factory()->create(['email_verified_at' => now()]));
    $account = autoReplyChannelAccount($workspace->id, $provider, 'page-123');
    autoReplyMenuRule($workspace->id);

    $event = processAutoReplyWebhook($account, $provider.'-event', ['entry' => [[
        'id' => 'page-123',
        'messaging' => [[
            'sender' => ['id' => 'psid-42'],
            'recipient' => ['id' => 'page-123'],
            'message' => ['mid' => 'mid.inbound-1', 'text' => 'show me the menu'],
        ]],
    ]]]);

    expect($event->status->value ?? $event->status)->toBe('processed')
        ->and(Message::query()->where('direction', 'outbound')->where('provider', $provider)->sole()->body)->toBe('Here is the menu.');
    Http::assertSent(fn ($request): bool => str_contains($request->url(), $sendPath) && data_get($request->data(), 'recipient.id') === 'psid-42');
})->with([
    ['messenger', 'page-123/messages'],
    ['instagram', 'page-123/messages'],
]);

it('auto replies on telegram to the chat id', function (): void {
    Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 99]], 200)]);
    $workspace = app(WorkspaceResolver::class)->current(User::factory()->create(['email_verified_at' => now()]));
    $account = autoReplyChannelAccount($workspace->id, 'telegram', 'bot-1');
    autoReplyMenuRule($workspace->id);

    $event = processAutoReplyWebhook($account, 'telegram-event', [
        'update_id' => 1001,
        'message' => [
            'message_id' => 55,
            'from' => ['id' => 777001, 'is_bot' => false, 'first_name' => 'Rafi', 'username' => 'rafi'],
            'chat' => ['id' => 777001, 'type' => 'private', 'first_name' => 'Rafi'],
            'date' => 1760000000,
            'text' => 'menu please',
        ],
    ]);

    expect($event->status->value ?? $event->status)->toBe('processed')
        ->and(Message::query()->where('direction', 'outbound')->where('provider', 'telegram')->sole()->status->value ?? null)->not->toBe('failed');
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/sendMessage') && (string) data_get($request->data(), 'chat_id') === '777001');
});

it('auto replies on threads as a reply to the comment instead of a public post', function (): void {
    Http::fake([
        'https://graph.threads.net/*/threads_publish' => Http::response(['id' => 'published-1'], 200),
        'https://graph.threads.net/*/threads' => Http::response(['id' => 'creation-1'], 200),
    ]);
    $workspace = app(WorkspaceResolver::class)->current(User::factory()->create(['email_verified_at' => now()]));
    $account = autoReplyChannelAccount($workspace->id, 'threads', 'threads-user-1');
    autoReplyMenuRule($workspace->id);

    processAutoReplyWebhook($account, 'threads-event', ['entry' => [[
        'changes' => [[
            'field' => 'replies',
            'value' => [
                'id' => 'reply-555',
                'text' => 'menu?',
                'from' => ['id' => 'fan-1', 'username' => 'fan'],
            ],
        ]],
    ]]]);

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/threads') && data_get($request->data(), 'reply_to_id') === 'reply-555');
    expect(Message::query()->where('direction', 'outbound')->where('provider', 'threads')->count())->toBe(1);
});

it('does not trigger auto replies on sms or email because they have no inbound messages', function (string $provider): void {
    Http::fake();
    $workspace = app(WorkspaceResolver::class)->current(User::factory()->create(['email_verified_at' => now()]));
    $account = autoReplyChannelAccount($workspace->id, $provider, $provider.'-1');
    autoReplyMenuRule($workspace->id);

    processAutoReplyWebhook($account, $provider.'-event', ['from' => '+8801711223344', 'text' => 'menu']);

    expect(Message::query()->count())->toBe(0);
    Http::assertNothingSent();
})->with(['sms', 'email']);

it('skips media auto replies on threads so nothing is posted publicly', function (): void {
    Http::fake();
    $workspace = app(WorkspaceResolver::class)->current(User::factory()->create(['email_verified_at' => now()]));
    $account = autoReplyChannelAccount($workspace->id, 'threads', 'threads-user-1');
    AutoReplyRule::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Menu image',
        'trigger_type' => 'keyword',
        'trigger_value' => 'menu',
        'match_type' => 'contains',
        'reply_type' => 'media',
        'reply_text' => 'Menu',
        'reply_payload' => ['type' => 'image', 'url' => 'https://cdn.example/menu.jpg'],
        'priority' => 1,
        'is_active' => true,
    ]);

    processAutoReplyWebhook($account, 'threads-media-event', ['entry' => [[
        'changes' => [['field' => 'replies', 'value' => ['id' => 'reply-9', 'text' => 'menu', 'from' => ['id' => 'fan-1']]]],
    ]]]);

    Http::assertNothingSent();
    expect(Message::query()->where('direction', 'outbound')->count())->toBe(0);
});
