<?php

use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Models\User;
use App\Modules\Campaigns\Enums\CampaignRecipientStatus;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\CampaignRecipient;
use App\Modules\Campaigns\Services\CampaignRecipientService;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactProviderIdentity;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Enums\ChannelWebhookEventStatus;
use App\Modules\MarketingChannels\Jobs\ProcessChannelWebhookJob;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Models\ChannelWebhookEvent;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\PlansSubscriptions\Models\Subscription;
use App\Modules\Workspaces\Enums\WorkspaceMemberRole;
use App\Modules\Workspaces\Enums\WorkspaceMemberStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function inboxChannelContext(): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);

    return [$user, $workspace];
}

function inboxChannel(int $workspaceId, string $provider, string $status = 'connected'): ChannelAccount
{
    return ChannelAccount::query()->create([
        'workspace_id' => $workspaceId,
        'provider' => $provider,
        'name' => ucfirst($provider).' Inbox',
        'status' => $status,
        'credentials' => ['access_token' => $provider.'-token'],
        'webhook_verify_token' => $provider.'-verify',
        'provider_account_id' => $provider.'-account',
        'provider_phone_id' => $provider === 'whatsapp' ? 'phone-123' : null,
        'provider_display_id' => $provider.' display',
        'settings' => [],
        'connected_at' => now(),
    ]);
}

function configureInboxPlatformAi(): void
{
    config([
        'ai.default' => 'openai',
        'ai.providers.openai' => [
            'driver' => 'openai',
            'key' => 'sk-platform-test',
        ],
    ]);
}

function inboxAiConversation(User $user, string $latestBody = 'Do you have pricing details?'): Conversation
{
    $workspace = app(WorkspaceResolver::class)->current($user);
    $channel = inboxChannel($workspace->id, 'whatsapp');
    $contact = Contact::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'AI Customer',
        'phone' => '+15555550123',
    ]);
    $conversation = Conversation::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'whatsapp',
        'provider_conversation_id' => $contact->phone,
        'contact_id' => $contact->id,
        'status' => 'open',
        'labels' => [],
    ]);

    Message::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'whatsapp',
        'conversation_id' => $conversation->id,
        'contact_id' => $contact->id,
        'direction' => 'inbound',
        'type' => 'text',
        'body' => $latestBody,
        'status' => 'received',
    ]);

    return $conversation;
}

function inboxAiCreditPlan(int $workspaceId, int $credits, int $used = 0): Subscription
{
    $plan = Plan::query()->create([
        'name' => 'Inbox AI Credits',
        'slug' => 'inbox-ai-credits-'.$workspaceId.'-'.$credits.'-'.$used,
        'price' => 0,
        'interval' => 'month',
        'limits' => ['max_ai_credits' => $credits],
        'features' => [],
        'is_active' => true,
    ]);

    return Subscription::query()->updateOrCreate(
        ['workspace_id' => $workspaceId],
        ['plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now(), 'renews_at' => now()->addMonth(), 'usage' => ['max_ai_credits' => $used]]
    );
}

it('lists inbox conversations from config enabled inbox providers only', function (): void {
    [$user, $workspace] = inboxChannelContext();

    $whatsapp = inboxChannel($workspace->id, 'whatsapp');
    $messenger = inboxChannel($workspace->id, 'messenger');
    $telegram = inboxChannel($workspace->id, 'telegram');
    $instagram = inboxChannel($workspace->id, 'instagram');
    $threads = inboxChannel($workspace->id, 'threads');
    $email = inboxChannel($workspace->id, 'email');
    $sms = inboxChannel($workspace->id, 'sms');
    $contact = Contact::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Rina Customer',
        'phone' => '+15555550123',
    ]);

    foreach ([
        'whatsapp' => $whatsapp,
        'messenger' => $messenger,
        'telegram' => $telegram,
        'instagram' => $instagram,
        'threads' => $threads,
        'email' => $email,
        'sms' => $sms,
    ] as $provider => $channel) {
        $conversation = Conversation::query()->create([
            'workspace_id' => $workspace->id,
            'channel_account_id' => $channel->id,
            'provider' => $provider,
            'provider_conversation_id' => $provider.'-recipient',
            'contact_id' => $contact->id,
            'status' => 'open',
            'last_message_at' => now(),
            'labels' => [],
        ]);

        Message::query()->create([
            'workspace_id' => $workspace->id,
            'channel_account_id' => $channel->id,
            'provider' => $provider,
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => 'inbound',
            'type' => 'text',
            'body' => ucfirst($provider).' hello',
            'status' => 'received',
        ]);
    }

    $this->withoutMiddleware()
        ->actingAs($user)
        ->getJson(route('user.inbox.conversations', ['provider' => 'all']))
        ->assertOk()
        ->assertJsonPath('counts.all', 5)
        ->assertJsonCount(5, 'conversations')
        ->assertJsonPath('channels.0.value', 'all')
        ->assertJsonPath('channels.1.value', 'whatsapp')
        ->assertJsonPath('channels.2.value', 'telegram')
        ->assertJsonPath('channels.3.value', 'messenger')
        ->assertJsonPath('channels.4.value', 'instagram')
        ->assertJsonPath('channels.5.value', 'threads')
        ->assertJsonMissingPath('channels.6');

    $this->withoutMiddleware()
        ->actingAs($user)
        ->getJson(route('user.inbox.conversations', ['provider' => 'messenger']))
        ->assertOk()
        ->assertJsonPath('counts.all', 1)
        ->assertJsonCount(1, 'conversations')
        ->assertJsonPath('conversations.0.provider', 'messenger')
        ->assertJsonPath('conversations.0.provider_label', 'Messenger');
});

it('only shows conversations and filters for connected inbox channels', function (): void {
    [$user, $workspace] = inboxChannelContext();

    $connected = inboxChannel($workspace->id, 'whatsapp');
    $disconnected = inboxChannel($workspace->id, 'telegram', 'disconnected');
    $contact = Contact::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Connected Customer',
        'phone' => '+15555550123',
    ]);

    foreach ([
        'whatsapp' => $connected,
        'telegram' => $disconnected,
    ] as $provider => $channel) {
        $conversation = Conversation::query()->create([
            'workspace_id' => $workspace->id,
            'channel_account_id' => $channel->id,
            'provider' => $provider,
            'provider_conversation_id' => $provider.'-recipient',
            'contact_id' => $contact->id,
            'status' => 'open',
            'last_message_at' => now(),
            'labels' => [],
        ]);

        Message::query()->create([
            'workspace_id' => $workspace->id,
            'channel_account_id' => $channel->id,
            'provider' => $provider,
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => 'inbound',
            'type' => 'text',
            'body' => ucfirst($provider).' hello',
            'status' => 'received',
        ]);
    }

    $this->withoutMiddleware()
        ->actingAs($user)
        ->getJson(route('user.inbox.conversations', ['provider' => 'all']))
        ->assertOk()
        ->assertJsonPath('counts.all', 1)
        ->assertJsonCount(1, 'conversations')
        ->assertJsonPath('conversations.0.provider', 'whatsapp')
        ->assertJsonPath('channels.0.value', 'all')
        ->assertJsonPath('channels.1.value', 'whatsapp')
        ->assertJsonMissingPath('channels.2');

    $this->withoutMiddleware()
        ->actingAs($user)
        ->getJson(route('user.inbox.conversations', ['provider' => 'telegram']))
        ->assertOk()
        ->assertJsonPath('counts.all', 0)
        ->assertJsonCount(0, 'conversations');
});

it('renders failed outbound message status in the error color', function (): void {
    [$user] = inboxChannelContext();

    $response = $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.inbox.index'))
        ->assertOk()
        ->assertSee("message.status === 'failed' ? 'text-error'", false)
        ->assertSee('ph-paperclip', false)
        ->assertSee('attachmentPreviewUrl', false)
        ->assertSee('chat-date-separator', false)
        ->assertSee('chat-message--grouped', false)
        ->assertSee('chat-bubble__meta', false)
        ->assertSee('inbox-composer__input', false)
        ->assertSee('inbox-composer__actions', false)
        ->assertSee('inbox-composer__field', false)
        ->assertSee('is-rail-collapsed', false)
        ->assertSee('is-list-collapsed', false)
        ->assertSee('toggleRail()', false)
        ->assertSee('toggleConversationList()', false)
        ->assertSee('openConversationList()', false)
        ->assertSee('Collapse views panel')
        ->assertSee('Collapse conversations')
        ->assertSee('Open conversations')
        ->assertSee('Back to conversations')
        ->assertSee('generateAiReply()', false)
        ->assertSee('ph-sparkle', false)
        ->assertSee('aiReply', false)
        ->assertSee('Auto reply')
        ->assertSee('toggleAutomatedReply', false)
        ->assertSee('automation', false)
        ->assertSee('syncCrmStageForPipeline($event.target.value)', false)
        ->assertSee('data-commerce-help="inbox"', false)
        ->assertSee('Send catalog or products from WhatsApp.')
        ->assertSee('Share complete catalog')
        ->assertSee('Opens the synchronized Meta catalog in WhatsApp')
        ->assertSee('commerceNotice', false)
        ->assertSee('Click send to see what action is required.')
        ->assertSee('ring-neutral-200/70', false)
        ->assertSee('toggleCrmPanel()', false)
        ->assertSee('closeCrmPanel()', false)
        ->assertSee('Hide CRM profile')
        ->assertSee('Open CRM profile')
        ->assertSee('crmPanelOpen && activeConversation?.contact_id && routes.crm', false);

    $content = $response->getContent();

    expect(strpos($content, 'data-commerce-help="inbox"'))
        ->toBeGreaterThan(strpos($content, 'class="inbox__crm"'))
        ->toBeLessThan(strpos($content, 'id="commerceHelpInbox"'));
});

it('generates an ai reply draft from platform ai without sending a message', function (): void {
    configureInboxPlatformAi();
    $user = User::factory()->create(['email_verified_at' => now()]);
    Permission::findOrCreate('inbox.reply', 'web');
    $user->givePermissionTo('inbox.reply');
    $conversation = inboxAiConversation($user, 'Can you explain your premium plan?');
    $subscription = inboxAiCreditPlan($conversation->workspace_id, 5);

    $this->withoutMiddleware([EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class])
        ->actingAs($user)
        ->postJson(route('user.inbox.conversations.ai-reply', $conversation), [
            'instruction' => 'Keep it short',
        ])
        ->assertOk()
        ->assertJsonPath('provider', 'openai')
        ->assertJsonPath('reply', 'AI draft reply for AI Customer: Can you explain your premium plan? (Instruction: Keep it short)');

    expect(Message::query()
        ->where('conversation_id', $conversation->id)
        ->where('direction', 'outbound')
        ->exists())->toBeFalse()
        ->and((int) data_get($subscription->fresh()->usage, 'max_ai_credits'))->toBe(1);
});

it('rejects ai reply generation when platform ai credits are over', function (): void {
    configureInboxPlatformAi();
    $user = User::factory()->create(['email_verified_at' => now()]);
    Permission::findOrCreate('inbox.reply', 'web');
    $user->givePermissionTo('inbox.reply');
    $conversation = inboxAiConversation($user);
    $subscription = inboxAiCreditPlan($conversation->workspace_id, 1, 1);

    $this->withoutMiddleware([EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class])
        ->actingAs($user)
        ->postJson(route('user.inbox.conversations.ai-reply', $conversation))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('plan')
        ->assertJsonPath('errors.plan.0', 'Your platform AI credits are over. Please upgrade your plan or buy more credits.');

    expect((int) data_get($subscription->fresh()->usage, 'max_ai_credits'))->toBe(1);
});

it('requires inbox reply permission before generating an ai reply draft', function (): void {
    configureInboxPlatformAi();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $conversation = inboxAiConversation($owner);
    $member = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($owner);
    $workspace->members()->attach($member->id, [
        'role' => WorkspaceMemberRole::Staff->value,
        'status' => WorkspaceMemberStatus::Active->value,
    ]);

    $this->withoutMiddleware([EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class])
        ->actingAs($member)
        ->postJson(route('user.inbox.conversations.ai-reply', $conversation))
        ->assertForbidden();
});

it('rejects ai reply generation when platform ai is not configured', function (): void {
    config([
        'ai.default' => 'openai',
        'ai.providers.openai' => [
            'driver' => 'openai',
        ],
    ]);
    $user = User::factory()->create(['email_verified_at' => now()]);
    Permission::findOrCreate('inbox.reply', 'web');
    $user->givePermissionTo('inbox.reply');
    $conversation = inboxAiConversation($user);

    $this->withoutMiddleware([EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class])
        ->actingAs($user)
        ->postJson(route('user.inbox.conversations.ai-reply', $conversation))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ai');
});

it('sends replies through the selected conversation provider identity', function (): void {
    [$user, $workspace] = inboxChannelContext();

    $channel = inboxChannel($workspace->id, 'telegram');
    $contact = Contact::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Tarin Customer',
    ]);
    ContactProviderIdentity::query()->create([
        'workspace_id' => $workspace->id,
        'contact_id' => $contact->id,
        'channel_account_id' => $channel->id,
        'provider' => 'telegram',
        'provider_contact_id' => '998877',
        'identity_type' => 'telegram_user_id',
    ]);
    $conversation = Conversation::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'telegram',
        'provider_conversation_id' => '998877',
        'contact_id' => $contact->id,
        'status' => 'open',
        'labels' => [],
    ]);

    Http::fake([
        'https://api.telegram.org/bottelegram-token/sendMessage' => Http::response([
            'ok' => true,
            'result' => ['message_id' => 'tg-mid-123'],
        ]),
    ]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->postJson(route('user.inbox.conversations.messages.store', $conversation), [
            'body' => 'Reply on Telegram',
        ])
        ->assertCreated()
        ->assertJsonPath('message.provider_message_id', 'tg-mid-123')
        ->assertJsonPath('conversation.provider', 'telegram');

    Http::assertSent(fn ($request): bool => $request['chat_id'] === '998877'
        && $request['text'] === 'Reply on Telegram');

    expect(Message::query()->where('provider', 'telegram')->where('body', 'Reply on Telegram')->exists())->toBeTrue();
});

it('keeps failed telegram replies visible when the contact has no telegram recipient', function (): void {
    [$user, $workspace] = inboxChannelContext();

    $channel = inboxChannel($workspace->id, 'telegram');
    $contact = Contact::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Missing Telegram Recipient',
    ]);
    $conversation = Conversation::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'telegram',
        'contact_id' => $contact->id,
        'status' => 'open',
        'labels' => [],
    ]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->postJson(route('user.inbox.conversations.messages.store', $conversation), [
            'body' => 'Can you see this?',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('message.body', 'Can you see this?')
        ->assertJsonPath('message.status', 'failed')
        ->assertJsonPath('conversation.last_message', 'Can you see this?');

    $this->withoutMiddleware()
        ->actingAs($user)
        ->getJson(route('user.inbox.conversations.show', $conversation))
        ->assertOk()
        ->assertJsonPath('messages.0.body', 'Can you see this?')
        ->assertJsonPath('messages.0.status', 'failed');

    expect(Message::query()
        ->where('provider', 'telegram')
        ->where('conversation_id', $conversation->id)
        ->where('body', 'Can you see this?')
        ->where('status', 'failed')
        ->exists())->toBeTrue();
});

it('creates telegram contacts and inbox messages from new webhook senders', function (): void {
    [, $workspace] = inboxChannelContext();

    $channel = inboxChannel($workspace->id, 'telegram');
    $event = ChannelWebhookEvent::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'telegram',
        'event_type' => 'message',
        'provider_event_id' => 'telegram-update-1',
        'payload_hash' => sha1('telegram-update-1'),
        'payload' => [
            'message' => [
                'message_id' => 'tg-inbound-1',
                'from' => [
                    'id' => '998877',
                    'username' => 'tarin_customer',
                ],
                'text' => 'Hello from Telegram',
            ],
        ],
        'status' => ChannelWebhookEventStatus::Pending->value,
    ]);

    app()->call([(new ProcessChannelWebhookJob($event->id)), 'handle']);

    $identity = ContactProviderIdentity::query()
        ->where('workspace_id', $workspace->id)
        ->where('provider', 'telegram')
        ->where('provider_contact_id', '998877')
        ->firstOrFail();

    $conversation = Conversation::query()
        ->where('workspace_id', $workspace->id)
        ->where('provider', 'telegram')
        ->where('contact_id', $identity->contact_id)
        ->firstOrFail();

    expect($identity->username)->toBe('tarin_customer')
        ->and($conversation->provider_conversation_id)->toBe('998877')
        ->and(Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'inbound')
            ->where('body', 'Hello from Telegram')
            ->where('status', 'received')
            ->exists())->toBeTrue()
        ->and($event->fresh()->status)->toBe(ChannelWebhookEventStatus::Processed);
});

it('creates threads contacts and inbox messages from webhook replies', function (): void {
    [$user, $workspace] = inboxChannelContext();

    $channel = inboxChannel($workspace->id, 'threads');
    $event = ChannelWebhookEvent::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'threads',
        'event_type' => 'message',
        'provider_event_id' => 'threads-reply-1',
        'payload_hash' => sha1('threads-reply-1'),
        'payload' => [
            'entry' => [[
                'id' => 'threads-account',
                'changes' => [[
                    'value' => [
                        'id' => 'threads-reply-1',
                        'text' => 'Nice Threads update',
                        'from' => [
                            'id' => 'threads-user-456',
                            'username' => 'threadfan',
                        ],
                    ],
                ]],
            ]],
        ],
        'status' => ChannelWebhookEventStatus::Pending->value,
    ]);

    app()->call([(new ProcessChannelWebhookJob($event->id)), 'handle']);

    $identity = ContactProviderIdentity::query()
        ->where('workspace_id', $workspace->id)
        ->where('provider', 'threads')
        ->where('provider_contact_id', 'threads-user-456')
        ->firstOrFail();

    $conversation = Conversation::query()
        ->where('workspace_id', $workspace->id)
        ->where('provider', 'threads')
        ->where('contact_id', $identity->contact_id)
        ->firstOrFail();

    expect($identity->username)->toBe('threadfan')
        ->and($conversation->provider_conversation_id)->toBe('threads-user-456')
        ->and(Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'inbound')
            ->where('provider_message_id', 'threads-reply-1')
            ->where('body', 'Nice Threads update')
            ->where('status', 'received')
            ->exists())->toBeTrue()
        ->and($event->fresh()->status)->toBe(ChannelWebhookEventStatus::Processed);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->getJson(route('user.inbox.conversations', ['provider' => 'threads']))
        ->assertOk()
        ->assertJsonPath('counts.all', 1)
        ->assertJsonPath('conversations.0.provider', 'threads')
        ->assertJsonPath('conversations.0.last_message', 'Nice Threads update');
});

it('sends threads inbox replies to the latest inbound reply target', function (): void {
    [$user, $workspace] = inboxChannelContext();

    $channel = inboxChannel($workspace->id, 'threads');
    $contact = Contact::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Threads Fan',
    ]);
    ContactProviderIdentity::query()->create([
        'workspace_id' => $workspace->id,
        'contact_id' => $contact->id,
        'channel_account_id' => $channel->id,
        'provider' => 'threads',
        'provider_contact_id' => 'threads-user-456',
        'identity_type' => 'provider_user_id',
    ]);
    $conversation = Conversation::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'threads',
        'provider_conversation_id' => 'threads-user-456',
        'contact_id' => $contact->id,
        'status' => 'open',
        'labels' => [],
    ]);
    Message::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'threads',
        'conversation_id' => $conversation->id,
        'contact_id' => $contact->id,
        'direction' => 'inbound',
        'type' => 'text',
        'body' => 'Inbound Threads reply',
        'provider_message_id' => 'threads-reply-1',
        'status' => 'received',
    ]);

    Http::fake([
        'https://graph.threads.net/v1.0/threads-account/threads' => Http::response(['id' => 'threads-creation-1']),
        'https://graph.threads.net/v1.0/threads-account/threads_publish' => Http::response(['id' => 'threads-agent-reply-1']),
    ]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->postJson(route('user.inbox.conversations.messages.store', $conversation), [
            'body' => 'Thanks for your reply',
        ])
        ->assertCreated()
        ->assertJsonPath('message.provider_message_id', 'threads-agent-reply-1')
        ->assertJsonPath('conversation.provider', 'threads');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://graph.threads.net/v1.0/threads-account/threads'
        && $request['text'] === 'Thanks for your reply'
        && $request['reply_to_id'] === 'threads-reply-1');

    expect(Message::query()
        ->where('provider', 'threads')
        ->where('direction', 'outbound')
        ->where('body', 'Thanks for your reply')
        ->where('provider_message_id', 'threads-agent-reply-1')
        ->where('status', 'sent')
        ->exists())->toBeTrue();
});

it('keeps failed threads replies visible when no inbound reply target exists', function (): void {
    [$user, $workspace] = inboxChannelContext();

    $channel = inboxChannel($workspace->id, 'threads');
    $contact = Contact::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Threads Without Target',
    ]);
    ContactProviderIdentity::query()->create([
        'workspace_id' => $workspace->id,
        'contact_id' => $contact->id,
        'channel_account_id' => $channel->id,
        'provider' => 'threads',
        'provider_contact_id' => 'threads-user-456',
        'identity_type' => 'provider_user_id',
    ]);
    $conversation = Conversation::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'threads',
        'provider_conversation_id' => 'threads-user-456',
        'contact_id' => $contact->id,
        'status' => 'open',
        'labels' => [],
    ]);

    Http::fake();

    $this->withoutMiddleware()
        ->actingAs($user)
        ->postJson(route('user.inbox.conversations.messages.store', $conversation), [
            'body' => 'Trying to reply',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('message.body', 'Trying to reply')
        ->assertJsonPath('message.status', 'failed')
        ->assertJsonPath('conversation.last_message', 'Trying to reply');

    Http::assertNothingSent();

    expect(Message::query()
        ->where('provider', 'threads')
        ->where('conversation_id', $conversation->id)
        ->where('body', 'Trying to reply')
        ->where('status', 'failed')
        ->exists())->toBeTrue();
});

it('sends whatsapp image attachments with an optional caption from the inbox', function (): void {
    [$user, $workspace] = inboxChannelContext();

    Storage::fake('public');
    $channel = inboxChannel($workspace->id, 'whatsapp');
    $contact = Contact::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Photo Customer',
        'phone' => '+15555550126',
    ]);
    $conversation = Conversation::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'whatsapp',
        'provider_conversation_id' => $contact->phone,
        'contact_id' => $contact->id,
        'status' => 'open',
        'labels' => [],
    ]);

    Http::fake([
        'https://graph.facebook.com/*/phone-123/messages' => Http::response([
            'messages' => [['id' => 'wamid.image']],
        ]),
    ]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->post(route('user.inbox.conversations.messages.store', $conversation), [
            'body' => 'Here is the image',
            'attachment' => UploadedFile::fake()->image('receipt.jpg', 640, 480),
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('message.type', 'image')
        ->assertJsonPath('message.body', 'Here is the image')
        ->assertJsonPath('message.attachment.name', 'receipt.jpg')
        ->assertJsonPath('message.attachment.type', 'image');

    Http::assertSent(fn ($request): bool => data_get($request->data(), 'type') === 'image'
        && data_get($request->data(), 'image.caption') === 'Here is the image'
        && str_contains((string) data_get($request->data(), 'image.link'), '/storage/inbox/'));

    $message = Message::query()->where('provider_message_id', 'wamid.image')->firstOrFail();

    Storage::disk('public')->assertExists(data_get($message->payload, 'attachment.path'));
});

it('sends telegram image attachments with a caption from the inbox', function (): void {
    [$user, $workspace] = inboxChannelContext();

    Storage::fake('public');
    $channel = inboxChannel($workspace->id, 'telegram');
    $contact = Contact::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Telegram Photo Customer',
    ]);
    ContactProviderIdentity::query()->create([
        'workspace_id' => $workspace->id,
        'contact_id' => $contact->id,
        'channel_account_id' => $channel->id,
        'provider' => 'telegram',
        'provider_contact_id' => '998877',
        'identity_type' => 'telegram_user_id',
    ]);
    $conversation = Conversation::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'telegram',
        'provider_conversation_id' => '998877',
        'contact_id' => $contact->id,
        'status' => 'open',
        'labels' => [],
    ]);

    Http::fake([
        'https://api.telegram.org/bottelegram-token/sendPhoto' => Http::response([
            'ok' => true,
            'result' => ['message_id' => 'tg-photo-123'],
        ]),
    ]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->post(route('user.inbox.conversations.messages.store', $conversation), [
            'body' => 'Telegram caption',
            'attachment' => UploadedFile::fake()->image('telegram.jpg', 640, 480),
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('message.type', 'image')
        ->assertJsonPath('message.provider_message_id', 'tg-photo-123')
        ->assertJsonPath('message.attachment.name', 'telegram.jpg');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.telegram.org/bottelegram-token/sendPhoto'
        && $request->isMultipart()
        && collect($request->data())->firstWhere('name', 'chat_id')['contents'] === '998877'
        && collect($request->data())->firstWhere('name', 'caption')['contents'] === 'Telegram caption'
        && str_ends_with(collect($request->data())->firstWhere('name', 'photo')['filename'], '.jpg'));
});

it('sends messenger image attachments with a reusable url payload', function (): void {
    [$user, $workspace] = inboxChannelContext();

    Storage::fake('public');
    $channel = inboxChannel($workspace->id, 'messenger');
    $contact = Contact::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Messenger Photo Customer',
    ]);
    ContactProviderIdentity::query()->create([
        'workspace_id' => $workspace->id,
        'contact_id' => $contact->id,
        'channel_account_id' => $channel->id,
        'provider' => 'messenger',
        'provider_contact_id' => 'psid-123',
        'identity_type' => 'psid',
    ]);
    $conversation = Conversation::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'messenger',
        'provider_conversation_id' => 'psid-123',
        'contact_id' => $contact->id,
        'status' => 'open',
        'labels' => [],
    ]);

    Http::fake([
        'https://graph.facebook.com/*/messenger-account/messages' => Http::response([
            'message_id' => 'mid.image.123',
        ]),
    ]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->post(route('user.inbox.conversations.messages.store', $conversation), [
            'body' => 'Messenger caption',
            'attachment' => UploadedFile::fake()->image('messenger.jpg', 640, 480),
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('message.type', 'image')
        ->assertJsonPath('message.provider_message_id', 'mid.image.123');

    Http::assertSent(fn ($request): bool => data_get($request->data(), 'recipient.id') === 'psid-123'
        && data_get($request->data(), 'message.attachment.type') === 'image'
        && data_get($request->data(), 'message.attachment.payload.is_reusable') === true
        && str_contains((string) data_get($request->data(), 'message.attachment.payload.url'), '/storage/inbox/'));
});

it('rejects threads attachments without recording a failed message', function (): void {
    [$user, $workspace] = inboxChannelContext();

    Storage::fake('public');
    $channel = inboxChannel($workspace->id, 'threads');
    $contact = Contact::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Threads Attachment Customer',
    ]);
    $conversation = Conversation::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'threads',
        'provider_conversation_id' => 'threads-user-456',
        'contact_id' => $contact->id,
        'status' => 'open',
        'labels' => [],
    ]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->post(route('user.inbox.conversations.messages.store', $conversation), [
            'body' => 'Threads image',
            'attachment' => UploadedFile::fake()->image('threads.jpg', 640, 480),
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('attachment');

    expect(Message::query()->where('conversation_id', $conversation->id)->exists())->toBeFalse();
});

it('records website widget agent attachment replies for widget polling', function (): void {
    [$user, $workspace] = inboxChannelContext();

    Storage::fake('public');
    $contact = Contact::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Widget Visitor',
    ]);
    $conversation = Conversation::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'website_widget',
        'provider_conversation_id' => 'widget-1',
        'contact_id' => $contact->id,
        'status' => 'open',
        'labels' => ['Website widget'],
    ]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->post(route('user.inbox.conversations.messages.store', $conversation), [
            'body' => 'Please review',
            'attachment' => UploadedFile::fake()->image('widget-reply.jpg', 640, 480),
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('message.type', 'image')
        ->assertJsonPath('message.attachment.name', 'widget-reply.jpg')
        ->assertJsonPath('conversation.attachment_supported', true);

    $message = Message::query()->where('conversation_id', $conversation->id)->firstOrFail();

    expect($message->provider)->toBe('website_widget')
        ->and(data_get($message->payload, 'attachment.name'))->toBe('widget-reply.jpg');
});

it('builds a local readable body for whatsapp template inbox messages', function (): void {
    [, $workspace] = inboxChannelContext();

    $channel = inboxChannel($workspace->id, 'whatsapp');
    $contact = Contact::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Ada Lovelace',
        'phone' => '+15555550123',
    ]);
    $template = MessageTemplate::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'whatsapp',
        'name' => 'order_ready',
        'language' => 'en_US',
        'category' => 'utility',
        'status' => 'approved',
        'components' => [
            ['type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Hi {{first_name}}'],
            ['type' => 'BODY', 'text' => 'Your order {{1}} is ready for pickup.'],
            ['type' => 'FOOTER', 'text' => 'Thanks for choosing us.'],
        ],
    ]);
    $campaign = Campaign::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'whatsapp',
        'message_type' => 'template',
        'message_template_id' => $template->id,
        'variables' => ['fixed' => ['1' => 'A-100']],
        'uuid' => (string) Str::uuid(),
        'name' => 'Template inbox preview',
        'status' => CampaignStatus::Sending->value,
        'audience_type' => 'groups',
        'send_rate_per_minute' => 60,
    ]);
    $recipient = CampaignRecipient::query()->create([
        'workspace_id' => $workspace->id,
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'channel_account_id' => $channel->id,
        'provider' => 'whatsapp',
        'uuid' => (string) Str::uuid(),
        'to' => $contact->phone,
        'recipient_address' => $contact->phone,
        'status' => CampaignRecipientStatus::Queued->value,
        'queued_at' => now(),
    ]);

    $payload = app(CampaignRecipientService::class)->buildPayload($campaign->fresh(), $recipient->fresh());

    expect($payload['body'])->toBe("Hi Ada\n\nYour order A-100 is ready for pickup.\n\nThanks for choosing us.")
        ->and($payload['meta_payload']['template'])->toBe([
            'name' => 'order_ready',
            'language' => ['code' => 'en_US'],
            'components' => [
                [
                    'type' => 'header',
                    'parameters' => [
                        ['type' => 'text', 'text' => 'Ada'],
                    ],
                ],
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => 'A-100'],
                    ],
                ],
            ],
        ]);
});

it('renders legacy whatsapp template messages without a stored body', function (): void {
    [$user, $workspace] = inboxChannelContext();

    $channel = inboxChannel($workspace->id, 'whatsapp');
    MessageTemplate::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'whatsapp',
        'name' => 'hello_world',
        'language' => 'en_US',
        'category' => 'utility',
        'status' => 'approved',
        'components' => [
            ['type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Hello World'],
            ['type' => 'BODY', 'text' => 'Welcome and congratulations!! This message demonstrates your ability to send a WhatsApp message notification from the Cloud API, hosted by Meta. Thank you for taking the time to test with us.'],
            ['type' => 'FOOTER', 'text' => 'WhatsApp Business Platform sample message'],
        ],
    ]);
    $contact = Contact::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Grace Hopper',
        'phone' => '+15555550124',
    ]);
    $conversation = Conversation::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'whatsapp',
        'provider_conversation_id' => $contact->phone,
        'contact_id' => $contact->id,
        'status' => 'open',
        'last_message_at' => now(),
        'labels' => [],
    ]);
    Message::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'whatsapp',
        'conversation_id' => $conversation->id,
        'contact_id' => $contact->id,
        'direction' => 'outbound',
        'type' => 'template',
        'body' => null,
        'payload' => ['template_name' => 'hello_world', 'language' => 'en_US'],
        'status' => 'sent',
    ]);
    $expectedBody = "Hello World\n\nWelcome and congratulations!! This message demonstrates your ability to send a WhatsApp message notification from the Cloud API, hosted by Meta. Thank you for taking the time to test with us.\n\nWhatsApp Business Platform sample message";

    $this->withoutMiddleware()
        ->actingAs($user)
        ->getJson(route('user.inbox.conversations.show', $conversation))
        ->assertOk()
        ->assertJsonPath('messages.0.body', $expectedBody);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->getJson(route('user.inbox.conversations', ['provider' => 'whatsapp']))
        ->assertOk()
        ->assertJsonPath('conversations.0.last_message', $expectedBody);
});

it('falls back to the whatsapp template title when no legacy template record exists', function (): void {
    [$user, $workspace] = inboxChannelContext();

    $channel = inboxChannel($workspace->id, 'whatsapp');
    $contact = Contact::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'No Template',
        'phone' => '+15555550125',
    ]);
    $conversation = Conversation::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'whatsapp',
        'provider_conversation_id' => $contact->phone,
        'contact_id' => $contact->id,
        'status' => 'open',
        'last_message_at' => now(),
        'labels' => [],
    ]);
    Message::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'whatsapp',
        'conversation_id' => $conversation->id,
        'contact_id' => $contact->id,
        'direction' => 'outbound',
        'type' => 'template',
        'body' => null,
        'payload' => ['template_name' => 'missing_template'],
        'status' => 'sent',
    ]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->getJson(route('user.inbox.conversations.show', $conversation))
        ->assertOk()
        ->assertJsonPath('messages.0.body', 'Template: missing_template');
});
