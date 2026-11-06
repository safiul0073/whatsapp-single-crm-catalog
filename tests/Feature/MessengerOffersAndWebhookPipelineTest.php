<?php

use App\Models\User;
use App\Modules\Campaigns\Enums\CampaignRecipientStatus;
use App\Modules\Campaigns\Jobs\SendCampaignRecipientJob;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Services\CampaignRecipientService;
use App\Modules\Chatbots\Jobs\GenerateAiReplyJob;
use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Chatbots\Services\ChannelAiReplyService;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactProviderIdentity;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MetaSocial\Models\MessengerMarketingOptin;
use App\Modules\MetaSocial\Services\MessengerMarketingService;
use App\Modules\MetaSocial\Services\MetaSocialSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: ChannelAccount}
 */
function offersChannel(string $provider = 'messenger'): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $account = ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => $provider,
        'name' => ucfirst($provider).' Page',
        'status' => 'connected',
        'credentials' => ['access_token' => 'page-token'],
        'webhook_verify_token' => $provider.'-hook',
        'provider_account_id' => $provider.'-page',
        'settings' => [],
        'connected_at' => now(),
    ]);

    return [$user, $account];
}

/**
 * @param  array<string, mixed>  $item
 * @return array<string, mixed>
 */
function pageWebhook(string $provider, array $item): array
{
    return [
        'object' => $provider === 'instagram' ? 'instagram' : 'page',
        'entry' => [['id' => $provider.'-page', 'time' => 1710000000, 'messaging' => [$item]]],
    ];
}

/**
 * @return array{0: Contact, 1: Conversation}
 */
function offersCustomer(ChannelAccount $account, string $psid, ?DateTimeInterface $lastMessageAt = null): array
{
    $contact = Contact::query()->create(['workspace_id' => $account->workspace_id, 'name' => 'Customer '.$psid, 'opt_in_status' => 'subscribed']);
    ContactProviderIdentity::query()->create([
        'workspace_id' => $account->workspace_id, 'contact_id' => $contact->id, 'channel_account_id' => $account->id,
        'provider' => $account->provider, 'provider_contact_id' => $psid, 'identity_type' => 'psid',
    ]);
    $conversation = Conversation::query()->create([
        'workspace_id' => $account->workspace_id, 'channel_account_id' => $account->id, 'provider' => $account->provider,
        'provider_conversation_id' => $psid, 'contact_id' => $contact->id, 'status' => 'open', 'labels' => [],
    ]);
    $message = Message::query()->create([
        'workspace_id' => $account->workspace_id, 'channel_account_id' => $account->id, 'provider' => $account->provider,
        'conversation_id' => $conversation->id, 'contact_id' => $contact->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'Hi', 'status' => 'received',
    ]);
    $message->forceFill(['created_at' => $lastMessageAt ?? now()->subHour()])->save();

    return [$contact, $conversation];
}

function activeOptin(ChannelAccount $account, Contact $contact, string $psid, string $token = 'nm-token-1'): MessengerMarketingOptin
{
    return MessengerMarketingOptin::query()->create([
        'workspace_id' => $account->workspace_id, 'channel_account_id' => $account->id, 'contact_id' => $contact->id,
        'psid' => $psid, 'topic' => 'offers', 'token' => $token, 'status' => 'active', 'opted_in_at' => now(), 'expires_at' => now()->addMonths(6),
    ]);
}

function messengerCampaign(ChannelAccount $account): Campaign
{
    return Campaign::query()->create([
        'uuid' => (string) Str::uuid(), 'workspace_id' => $account->workspace_id, 'channel_account_id' => $account->id,
        'provider' => 'messenger', 'type' => 'broadcast', 'name' => 'Eid offers', 'message_type' => 'custom',
        'message_body' => 'Eid sale: 20% off all panjabis this week!', 'audience_type' => 'contacts', 'audience_ids' => [],
    ]);
}

beforeEach(function (): void {
    Http::fake(['graph.facebook.com/*' => Http::response(['message_id' => 'mid.out', 'first_name' => 'Rafi'])]);
});

it('runs the AI bot for Messenger messages that arrive on the Meta webhook URL', function (): void {
    Queue::fake([GenerateAiReplyJob::class]);
    [, $account] = offersChannel();
    $chatbot = Chatbot::query()->create(['workspace_id' => $account->workspace_id, 'name' => 'Bot', 'persona' => 'Help.', 'greeting' => 'Hi', 'is_active' => true]);
    app(ChannelAiReplyService::class)->updateSettings($account->workspace, ['messenger' => ['enabled' => true, 'chatbot_id' => $chatbot->id]]);

    $this->postJson('/webhooks/meta/messenger-hook', pageWebhook('messenger', [
        'sender' => ['id' => '24680'], 'recipient' => ['id' => 'messenger-page'], 'timestamp' => 1710000001,
        'message' => ['mid' => 'mid-ai-1', 'text' => 'Do you have this in red?'],
    ]))->assertOk();

    $message = Message::query()->where('provider_message_id', 'mid-ai-1')->firstOrFail();
    expect($message->direction)->toBe('inbound')
        ->and($message->body)->toBe('Do you have this in red?');
    Queue::assertPushed(GenerateAiReplyJob::class, fn (GenerateAiReplyJob $job): bool => $job->messageId === $message->id);
});

it('ignores echoes of the page\'s own messages', function (): void {
    [, $account] = offersChannel();

    $this->postJson('/webhooks/meta/messenger-hook', pageWebhook('messenger', [
        'sender' => ['id' => 'messenger-page'], 'recipient' => ['id' => '24680'], 'timestamp' => 1710000001,
        'message' => ['mid' => 'mid-echo', 'text' => 'Thanks for your order', 'is_echo' => true],
    ]))->assertOk();

    expect(Message::query()->where('provider_message_id', 'mid-echo')->exists())->toBeFalse()
        ->and(Contact::query()->where('workspace_id', $account->workspace_id)->count())->toBe(0);
});

it('stores Messenger attachments as media messages', function (): void {
    offersChannel();

    $this->postJson('/webhooks/meta/messenger-hook', pageWebhook('messenger', [
        'sender' => ['id' => '24680'], 'recipient' => ['id' => 'messenger-page'], 'timestamp' => 1710000001,
        'message' => ['mid' => 'mid-photo', 'attachments' => [['type' => 'image', 'payload' => ['url' => 'https://cdn.example.test/a.jpg']]]],
    ]))->assertOk();

    expect(Message::query()->where('provider_message_id', 'mid-photo')->value('type'))->toBe('media');
});

it('still saves Instagram comments so they can be answered publicly', function (): void {
    offersChannel('instagram');

    $this->postJson('/webhooks/meta/instagram-hook', [
        'object' => 'instagram',
        'entry' => [['id' => 'instagram-page', 'time' => 1710000000, 'changes' => [[
            'field' => 'comments',
            'value' => ['id' => 'comment-9', 'text' => 'Price please?', 'from' => ['id' => '13579', 'username' => 'shop.fan']],
        ]]]],
    ])->assertOk();

    $message = Message::query()->where('provider_message_id', 'comment-9')->firstOrFail();
    expect($message->type)->toBe('comment')
        ->and($message->body)->toBe('Price please?');
});

it('stores the Messenger offers token from the opt-in webhook and respects STOP', function (): void {
    [, $account] = offersChannel();
    [$contact] = offersCustomer($account, '24680');
    $optin = fn (array $extra): array => pageWebhook('messenger', [
        'sender' => ['id' => '24680'], 'recipient' => ['id' => 'messenger-page'], 'timestamp' => 1710000002 + count($extra),
        'optin' => array_merge(['type' => 'notification_messages', 'payload' => 'offers'], $extra),
    ]);

    $this->postJson('/webhooks/meta/messenger-hook', $optin([
        'notification_messages_token' => 'nm-token-abc',
        'notification_messages_frequency' => 'DAILY',
        'token_expiry_timestamp' => now()->addMonths(6)->getTimestampMs(),
    ]))->assertOk();

    $service = app(MessengerMarketingService::class);
    expect($service->tokenFor($contact->id, $account->id))->toBe('nm-token-abc');

    $this->postJson('/webhooks/meta/messenger-hook', $optin(['notification_messages_status' => 'STOP_NOTIFICATIONS']))->assertOk();

    expect($service->tokenFor($contact->id, $account->id))->toBeNull()
        ->and(MessengerMarketingOptin::query()->sole()->status)->toBe(MessengerMarketingOptin::STATUS_STOPPED);
});

it('sends the Get messages request from the inbox within 24 hours', function (): void {
    [$user, $account] = offersChannel();
    [, $conversation] = offersCustomer($account, '24680');

    $this->withoutMiddleware()->actingAs($user)
        ->postJson(route('user.inbox.conversations.messenger-offers', $conversation))
        ->assertOk()
        ->assertJsonPath('conversation.messenger_offers.subscribed', false);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/messenger-page/messages')
        && data_get($request->data(), 'recipient.id') === '24680'
        && data_get($request->data(), 'message.attachment.payload.template_type') === 'notification_messages');
    expect(MessengerMarketingOptin::query()->sole()->status)->toBe(MessengerMarketingOptin::STATUS_PENDING);
});

it('refuses the Get messages request after the 24 hour window', function (): void {
    [$user, $account] = offersChannel();
    [, $conversation] = offersCustomer($account, '24680', now()->subDays(2));

    $this->withoutMiddleware()->actingAs($user)
        ->postJson(route('user.inbox.conversations.messenger-offers', $conversation))
        ->assertStatus(422)
        ->assertJsonValidationErrors('messenger_offers');

    Http::assertNothingSent();
});

it('asks new Messenger customers automatically when the page owner turned it on', function (): void {
    [, $account] = offersChannel();
    app(MessengerMarketingService::class)->updateSettings($account, ['auto_request' => true, 'title' => 'Get Eid deals']);

    $this->postJson('/webhooks/meta/messenger-hook', pageWebhook('messenger', [
        'sender' => ['id' => '97531'], 'recipient' => ['id' => 'messenger-page'], 'timestamp' => 1710000001,
        'message' => ['mid' => 'mid-first', 'text' => 'Hello'],
    ]))->assertOk();

    Http::assertSent(fn (Request $request): bool => data_get($request->data(), 'message.attachment.payload.title') === 'Get Eid deals');
    expect(MessengerMarketingOptin::query()->where('psid', '97531')->exists())->toBeTrue();
});

it('broadcasts Messenger offers only to subscribed customers, using their token', function (): void {
    [, $account] = offersChannel();
    [$subscribed] = offersCustomer($account, '11111');
    [$notSubscribed] = offersCustomer($account, '22222');
    activeOptin($account, $subscribed, '11111', 'nm-token-sub');
    $campaign = messengerCampaign($account);
    $recipients = app(CampaignRecipientService::class);

    $queued = $recipients->createForContact($campaign, $subscribed);
    $skipped = $recipients->createForContact($campaign, $notSubscribed);

    expect($queued->status)->toBe(CampaignRecipientStatus::Queued)
        ->and($queued->recipient_address)->not->toContain('nm-token')
        ->and($skipped->status)->toBe(CampaignRecipientStatus::SkippedPolicy)
        ->and($skipped->error_code)->toBe('messenger_optin_missing');

    app()->call([new SendCampaignRecipientJob($queued->id), 'handle']);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/messenger-page/messages')
        && data_get($request->data(), 'recipient.notification_messages_token') === 'nm-token-sub'
        && data_get($request->data(), 'message.text') === 'Eid sale: 20% off all panjabis this week!');
});

it('does not send to a customer who stopped offers after the campaign was queued', function (): void {
    [, $account] = offersChannel();
    [$contact] = offersCustomer($account, '11111');
    $optin = activeOptin($account, $contact, '11111');
    $recipient = app(CampaignRecipientService::class)->createForContact(messengerCampaign($account), $contact);
    $optin->update(['status' => MessengerMarketingOptin::STATUS_STOPPED]);
    Http::fake();

    app()->call([new SendCampaignRecipientJob($recipient->id), 'handle']);

    expect($recipient->fresh()->status)->toBe(CampaignRecipientStatus::Failed)
        ->and($recipient->fresh()->error_code)->toBe('messenger_optin_missing');
    Http::assertNothingSent();
});

it('skips expired offer tokens', function (): void {
    [, $account] = offersChannel();
    [$contact] = offersCustomer($account, '11111');
    activeOptin($account, $contact, '11111')->update(['expires_at' => now()->subDay()]);

    $recipient = app(CampaignRecipientService::class)->createForContact(messengerCampaign($account), $contact);

    expect($recipient->status)->toBe(CampaignRecipientStatus::SkippedPolicy);
});

it('allows Messenger offer broadcasts but still blocks Instagram broadcasts', function (): void {
    [, $messenger] = offersChannel();
    [, $instagram] = offersChannel('instagram');
    $channels = app(ChannelManager::class);

    $channels->validateCampaign($messenger, messengerCampaign($messenger));

    $instagramCampaign = messengerCampaign($instagram)->forceFill(['provider' => 'instagram']);
    expect(fn () => $channels->validateCampaign($instagram, $instagramCampaign))->toThrow(ValidationException::class);
});

it('subscribes Pages to the messaging_optins webhook field', function (): void {
    expect(explode(',', MetaSocialSetupService::PAGE_WEBHOOK_FIELDS))->toContain('messaging_optins');
});
