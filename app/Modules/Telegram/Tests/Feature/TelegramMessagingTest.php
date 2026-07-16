<?php

use App\Http\Middleware\EnsureOnboardingComplete;
use App\Models\User;
use App\Modules\Automations\Services\AutomationDispatcher;
use App\Modules\Campaigns\Enums\CampaignRecipientStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Services\CampaignRecipientService;
use App\Modules\Contacts\Enums\ContactOptInStatus;
use App\Modules\Contacts\Models\ContactProviderIdentity;
use App\Modules\Contacts\Services\ContactService;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Enums\ChannelWebhookEventStatus;
use App\Modules\MarketingChannels\Jobs\ProcessChannelWebhookJob;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Models\ChannelWebhookEvent;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Telegram\Services\TelegramBotProvider;
use App\Modules\Telegram\Services\TelegramOptInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function telegramWorkspaceFor(User $user)
{
    return app(WorkspaceResolver::class)->current($user);
}

function connectedTelegramChannel(int $workspaceId): ChannelAccount
{
    return ChannelAccount::query()->create([
        'workspace_id' => $workspaceId,
        'provider' => 'telegram',
        'name' => 'Support Bot',
        'status' => ChannelAccountStatus::Connected->value,
        'provider_account_id' => 'support_bot',
        'provider_display_id' => 'Support Bot',
        'credentials' => ['access_token' => '123456:test-token'],
        'settings' => ['telegram_bot_username' => 'support_bot'],
        'webhook_verify_token' => Str::random(32),
        'connected_at' => now(),
    ]);
}

function connectedInviteDeliveryChannel(int $workspaceId, string $provider): ChannelAccount
{
    return ChannelAccount::query()->create([
        'workspace_id' => $workspaceId,
        'provider' => $provider,
        'name' => ucfirst($provider).' Channel',
        'status' => ChannelAccountStatus::Connected->value,
        'provider_account_id' => $provider.'-account',
        'provider_phone_id' => $provider === 'whatsapp' ? 'phone-123' : null,
        'provider_display_id' => $provider === 'email' ? 'noreply@example.com' : '+14155550000',
        'credentials' => match ($provider) {
            'whatsapp' => ['access_token' => 'whatsapp-token'],
            'sms' => ['sms_provider' => 'log'],
            'email' => ['mail_mailer' => 'log', 'mail_from_name' => 'WaPro'],
            default => [],
        },
        'settings' => [],
        'webhook_verify_token' => Str::random(32),
        'connected_at' => now(),
    ]);
}

it('shows public telegram links on the connected setup page', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = telegramWorkspaceFor($user);
    connectedTelegramChannel($workspace->id);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.telegram.index'))
        ->assertOk()
        ->assertSee('https://t.me/support_bot', false)
        ->assertSee('https://t.me/support_bot?start=subscribe', false);
});

it('creates a contact specific telegram invite link for copy mode', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = telegramWorkspaceFor($user);
    connectedTelegramChannel($workspace->id);
    $contact = app(ContactService::class)->upsert($workspace->id, [
        'phone' => '+14155552671',
        'name' => 'Ada Lovelace',
    ]);

    $this->withoutMiddleware(EnsureOnboardingComplete::class)
        ->actingAs($user)
        ->postJson(route('user.telegram.contacts.invite', $contact), [
            'channel' => 'copy',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('channel', 'copy')
        ->assertJson(fn ($json) => $json
            ->where('invite_url', fn (string $url): bool => str_starts_with($url, 'https://t.me/support_bot?start='))
            ->where('message', fn (string $message): bool => str_contains($message, 'Ada Lovelace') && str_contains($message, 'https://t.me/support_bot?start='))
            ->etc()
        );
});

it('blocks telegram invite access for contacts in another workspace', function (): void {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create(['email_verified_at' => now()]);
    $workspace = telegramWorkspaceFor($owner);
    telegramWorkspaceFor($other);
    connectedTelegramChannel($workspace->id);
    $contact = app(ContactService::class)->upsert($workspace->id, [
        'phone' => '+14155552671',
        'name' => 'Ada Lovelace',
    ]);

    $this->withoutMiddleware(EnsureOnboardingComplete::class)
        ->actingAs($other)
        ->postJson(route('user.telegram.contacts.invite', $contact), [
            'channel' => 'copy',
        ])
        ->assertForbidden();
});

it('sends contact specific telegram invites through whatsapp sms and email', function (string $provider): void {
    if ($provider === 'whatsapp') {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.invite']],
            ], 200),
        ]);
    }

    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = telegramWorkspaceFor($user);
    connectedTelegramChannel($workspace->id);
    connectedInviteDeliveryChannel($workspace->id, $provider);
    $contact = app(ContactService::class)->upsert($workspace->id, [
        'phone' => '+14155552671',
        'email' => 'ada@example.com',
        'name' => 'Ada Lovelace',
    ]);

    $response = $this->withoutMiddleware(EnsureOnboardingComplete::class)
        ->actingAs($user)
        ->postJson(route('user.telegram.contacts.invite', $contact), [
            'channel' => $provider,
            'message' => 'Join Telegram here: {{ telegram_link }}',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('channel', $provider)
        ->assertJson(fn ($json) => $json
            ->where('message', fn (string $message): bool => str_contains($message, 'https://t.me/support_bot?start='))
            ->etc()
        );

    if ($provider === 'whatsapp') {
        expect(Message::query()
            ->where('provider', 'whatsapp')
            ->where('contact_id', $contact->id)
            ->where('body', $response->json('message'))
            ->exists())->toBeTrue();
    }
})->with(['whatsapp', 'sms', 'email']);

it('receives telegram webhooks without csrf and routes them by secret token', function (): void {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $firstWorkspace = telegramWorkspaceFor($first);
    $secondWorkspace = telegramWorkspaceFor($second);
    connectedTelegramChannel($firstWorkspace->id)->update(['webhook_verify_token' => 'first-secret']);
    $secondChannel = connectedTelegramChannel($secondWorkspace->id);
    $secondChannel->update(['webhook_verify_token' => 'second-secret']);

    $this->postJson(route('webhooks.channels.receive', ['provider' => 'telegram']), [
        'update_id' => 9001,
        'message' => [
            'message_id' => 501,
            'text' => 'hello from telegram',
            'from' => ['id' => 222, 'username' => 'telegram_user'],
            'chat' => ['id' => 222, 'type' => 'private'],
        ],
    ], [
        'X-Telegram-Bot-Api-Secret-Token' => 'second-secret',
    ])->assertOk();

    expect(Message::query()
        ->where('workspace_id', $secondWorkspace->id)
        ->where('channel_account_id', $secondChannel->id)
        ->where('provider', 'telegram')
        ->where('provider_message_id', '501')
        ->where('body', 'hello from telegram')
        ->exists())->toBeTrue()
        ->and(Message::query()
            ->where('workspace_id', $firstWorkspace->id)
            ->where('provider', 'telegram')
            ->exists())->toBeFalse();
});

it('receives telegram webhooks through the channel webhook code', function (): void {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $firstWorkspace = telegramWorkspaceFor($first);
    $secondWorkspace = telegramWorkspaceFor($second);
    connectedTelegramChannel($firstWorkspace->id);
    $secondChannel = connectedTelegramChannel($secondWorkspace->id);

    $this->postJson(route('webhooks.channels.account.receive', [
        'provider' => 'telegram',
        'webhookCode' => $secondChannel->webhook_code,
    ]), [
        'update_id' => 9101,
        'message' => [
            'message_id' => 601,
            'text' => 'hello through dynamic code',
            'from' => ['id' => 444, 'username' => 'dynamic_user'],
            'chat' => ['id' => 444, 'type' => 'private'],
        ],
    ])->assertOk();

    expect(Message::query()
        ->where('workspace_id', $secondWorkspace->id)
        ->where('channel_account_id', $secondChannel->id)
        ->where('provider', 'telegram')
        ->where('provider_message_id', '601')
        ->where('body', 'hello through dynamic code')
        ->exists())->toBeTrue()
        ->and(Message::query()
            ->where('workspace_id', $firstWorkspace->id)
            ->where('provider', 'telegram')
            ->exists())->toBeFalse();
});

it('rejects channel webhook codes that do not match the provider', function (): void {
    $user = User::factory()->create();
    $workspace = telegramWorkspaceFor($user);
    $channel = connectedTelegramChannel($workspace->id);

    $this->postJson(route('webhooks.channels.account.receive', [
        'provider' => 'whatsapp',
        'webhookCode' => $channel->webhook_code,
    ]), [
        'update_id' => 9102,
        'message' => [
            'message_id' => 602,
            'text' => 'wrong provider',
            'from' => ['id' => 555],
            'chat' => ['id' => 555, 'type' => 'private'],
        ],
    ])->assertNotFound();
});

it('rejects telegram webhooks without a secret when multiple telegram channels exist', function (): void {
    $first = User::factory()->create();
    $second = User::factory()->create();
    connectedTelegramChannel(telegramWorkspaceFor($first)->id);
    connectedTelegramChannel(telegramWorkspaceFor($second)->id);

    $this->postJson(route('webhooks.channels.receive', ['provider' => 'telegram']), [
        'update_id' => 9002,
        'message' => [
            'message_id' => 502,
            'text' => 'ambiguous',
            'from' => ['id' => 333],
            'chat' => ['id' => 333, 'type' => 'private'],
        ],
    ])->assertNotFound();
});

it('registers telegram webhooks with the channel secret token', function (): void {
    Http::fake([
        'api.telegram.org/*/setWebhook' => Http::response([
            'ok' => true,
            'description' => 'Webhook was set',
        ], 200),
    ]);

    $user = User::factory()->create();
    $workspace = telegramWorkspaceFor($user);
    $channel = connectedTelegramChannel($workspace->id);
    $channel->update(['webhook_verify_token' => 'telegram-secret-token']);

    $result = app(TelegramBotProvider::class)->setWebhook($channel->fresh());

    expect($result['ok'])->toBeTrue();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.telegram.org/bot123456:test-token/setWebhook'
        && $request['secret_token'] === 'telegram-secret-token'
        && str_ends_with((string) $request['url'], '/webhooks/channels/telegram/'.$channel->webhook_code));
});

it('updates telegram webhook automatically when a bot is connected', function (): void {
    Http::fake([
        'https://api.telegram.org/bot123456:test-token/getMe' => Http::response([
            'ok' => true,
            'result' => ['username' => 'support_bot'],
        ], 200),
        'https://api.telegram.org/bot123456:test-token/setWebhook' => Http::response([
            'ok' => true,
            'description' => 'Webhook was set',
        ], 200),
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->post(route('user.telegram.store'), [
            'name' => 'Support Bot',
            'provider_account_id' => 'support_bot',
            'provider_display_id' => 'Support Bot',
            'access_token' => '123456:test-token',
        ])
        ->assertRedirect(route('user.telegram.index'))
        ->assertSessionHas('status', 'Telegram bot connected and webhook updated.');

    $channel = ChannelAccount::query()->where('provider', 'telegram')->firstOrFail();

    expect($channel->settings)
        ->toHaveKey('last_webhook_set_at')
        ->and($channel->settings['telegram_bot_username'])->toBe('support_bot');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.telegram.org/bot123456:test-token/setWebhook'
        && $request['secret_token'] === $channel->webhook_verify_token
        && str_ends_with((string) $request['url'], '/webhooks/channels/telegram/'.$channel->webhook_code));
});

it('links an existing contact when telegram receives a start token', function (): void {
    $user = User::factory()->create();
    $workspace = telegramWorkspaceFor($user);
    $contact = app(ContactService::class)->upsert($workspace->id, [
        'phone' => '+14155552671',
        'name' => 'Ada Lovelace',
    ]);
    $channel = connectedTelegramChannel($workspace->id);
    $link = app(TelegramOptInService::class)->linkFor($contact, $channel);

    $event = ChannelWebhookEvent::query()->create([
        'channel_account_id' => $channel->id,
        'workspace_id' => $workspace->id,
        'provider' => 'telegram',
        'event_type' => 'message.received',
        'payload_hash' => sha1('telegram-start-token'),
        'payload' => [
            'message' => [
                'message_id' => 101,
                'text' => '/start '.$link['token'],
                'from' => ['id' => 777, 'username' => 'ada'],
                'chat' => ['id' => 777, 'type' => 'private'],
            ],
        ],
        'status' => ChannelWebhookEventStatus::Pending->value,
    ]);

    (new ProcessChannelWebhookJob($event->id))->handle(app(ChannelManager::class), app(AutomationDispatcher::class));

    $identity = ContactProviderIdentity::query()
        ->where('workspace_id', $workspace->id)
        ->where('provider', 'telegram')
        ->where('provider_contact_id', '777')
        ->first();

    expect($identity)->not->toBeNull()
        ->and($identity->contact_id)->toBe($contact->id)
        ->and($identity->identity_type)->toBe('telegram_chat_id')
        ->and($contact->fresh()->opt_in_status)->toBe(ContactOptInStatus::Subscribed);
});

it('links a shared telegram contact phone to the matching contact', function (): void {
    $user = User::factory()->create();
    $workspace = telegramWorkspaceFor($user);
    $contact = app(ContactService::class)->upsert($workspace->id, [
        'phone' => '+14155552671',
        'name' => 'Ada Lovelace',
    ]);
    $channel = connectedTelegramChannel($workspace->id);

    $event = ChannelWebhookEvent::query()->create([
        'channel_account_id' => $channel->id,
        'workspace_id' => $workspace->id,
        'provider' => 'telegram',
        'event_type' => 'message.received',
        'payload_hash' => sha1('telegram-shared-contact'),
        'payload' => [
            'message' => [
                'message_id' => 102,
                'from' => ['id' => 888, 'first_name' => 'Ada'],
                'chat' => ['id' => 888, 'type' => 'private'],
                'contact' => [
                    'phone_number' => '14155552671',
                    'first_name' => 'Ada',
                    'user_id' => 888,
                ],
            ],
        ],
        'status' => ChannelWebhookEventStatus::Pending->value,
    ]);

    (new ProcessChannelWebhookJob($event->id))->handle(app(ChannelManager::class), app(AutomationDispatcher::class));

    expect(ContactProviderIdentity::query()
        ->where('workspace_id', $workspace->id)
        ->where('contact_id', $contact->id)
        ->where('provider', 'telegram')
        ->where('provider_contact_id', '888')
        ->exists())->toBeTrue();
});

it('skips telegram campaign recipients that have not opted in', function (): void {
    $user = User::factory()->create();
    $workspace = telegramWorkspaceFor($user);
    $contact = app(ContactService::class)->upsert($workspace->id, [
        'phone' => '+14155552671',
        'name' => 'Ada Lovelace',
    ]);
    $channel = connectedTelegramChannel($workspace->id);
    $campaign = Campaign::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'telegram',
        'uuid' => (string) Str::uuid(),
        'name' => 'Telegram test',
        'type' => 'broadcast',
        'status' => 'sending',
        'audience_type' => 'contacts',
        'audience_ids' => [$contact->id],
        'message_type' => 'custom',
        'message_body' => 'Hello {{ name }}',
        'variables' => [],
        'settings' => [],
    ]);

    $recipient = app(CampaignRecipientService::class)->createForContact($campaign, $contact);

    expect($recipient->status)->toBe(CampaignRecipientStatus::SkippedPolicy)
        ->and($recipient->error_code)->toBe('telegram_opt_in_missing')
        ->and($recipient->recipient_address)->toBeNull();
});

it('sends attachments via multipart upload to telegram', function (): void {
    Http::fake([
        'https://api.telegram.org/*/sendPhoto' => Http::response([
            'ok' => true,
            'result' => ['message_id' => 12345],
        ], 200),
        'https://example.com/image.png' => Http::response('fake-image-bytes', 200),
    ]);

    $account = new ChannelAccount([
        'credentials' => ['access_token' => '123:token'],
    ]);

    $recipient = ['chat_id' => '98765'];
    $payload = [
        'type' => 'image',
        'url' => 'https://example.com/image.png',
        'caption' => 'Check this out!',
    ];

    $result = app(TelegramBotProvider::class)->sendMessage($account, $recipient, $payload);

    expect($result['ok'])->toBeTrue()
        ->and($result['provider_message_id'])->toBe(12345);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.telegram.org/bot123:token/sendPhoto' &&
        $request->isMultipart() &&
        collect($request->data())->firstWhere('name', 'chat_id')['contents'] === '98765' &&
        collect($request->data())->firstWhere('name', 'caption')['contents'] === 'Check this out!'
    );
});
