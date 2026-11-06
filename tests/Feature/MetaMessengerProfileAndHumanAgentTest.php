<?php

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactProviderIdentity;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\Inbox\Services\InboxService;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: ChannelAccount}
 */
function metaProfileChannel(string $provider): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $account = ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => $provider,
        'name' => ucfirst($provider).' Inbox',
        'status' => 'connected',
        'credentials' => ['access_token' => $provider.'-token'],
        'webhook_verify_token' => $provider.'-verify',
        'provider_account_id' => $provider.'-account',
        'settings' => [],
        'connected_at' => now(),
    ]);

    return [$user, $account];
}

/**
 * @return array<string, mixed>
 */
function metaInboundWebhook(string $provider, string $senderId, string $messageId, string $text = 'Hi there'): array
{
    return [
        'object' => $provider === 'instagram' ? 'instagram' : 'page',
        'entry' => [[
            'id' => $provider.'-account',
            'time' => 1710000000,
            'messaging' => [[
                'sender' => ['id' => $senderId],
                'recipient' => ['id' => $provider.'-account'],
                'timestamp' => 1710000001,
                'message' => ['mid' => $messageId, 'text' => $text],
            ]],
        ]],
    ];
}

function profileLookups(string $senderId): int
{
    return Http::recorded(fn (Request $request): bool => $request->method() === 'GET' && str_contains($request->url(), '/'.$senderId))->count();
}

it('names a new Messenger customer from their Meta profile and looks them up only once', function (): void {
    metaProfileChannel('messenger');
    Http::fake(['graph.facebook.com/*/24680*' => Http::response(['first_name' => 'Rafi', 'last_name' => 'Ahmed', 'profile_pic' => 'https://cdn.example.test/rafi.jpg'])]);

    $this->postJson('/webhooks/meta/messenger-verify', metaInboundWebhook('messenger', '24680', 'mid-1'))->assertOk();
    $this->postJson('/webhooks/meta/messenger-verify', metaInboundWebhook('messenger', '24680', 'mid-2', 'Price?'))->assertOk();

    $identity = ContactProviderIdentity::query()->where('provider_contact_id', '24680')->firstOrFail();
    expect($identity->contact->name)->toBe('Rafi Ahmed')
        ->and($identity->metadata['profile_pic'])->toBe('https://cdn.example.test/rafi.jpg')
        ->and(profileLookups('24680'))->toBe(1);
});

it('names a new Instagram customer from their username when Meta has no name', function (): void {
    metaProfileChannel('instagram');
    Http::fake(['graph.facebook.com/*/13579*' => Http::response(['username' => 'shop.fan'])]);

    $this->postJson('/webhooks/meta/instagram-verify', metaInboundWebhook('instagram', '13579', 'ig-mid-1'))->assertOk();

    $identity = ContactProviderIdentity::query()->where('provider_contact_id', '13579')->firstOrFail();
    expect($identity->contact->name)->toBe('@shop.fan')
        ->and($identity->username)->toBe('shop.fan');
});

it('keeps the placeholder name when the profile lookup fails and does not retry the same day', function (): void {
    metaProfileChannel('messenger');
    Http::fake(['graph.facebook.com/*/97531*' => Http::response(['error' => ['code' => 10, 'message' => 'Requires Business Asset User Profile Access']], 400)]);

    $this->postJson('/webhooks/meta/messenger-verify', metaInboundWebhook('messenger', '97531', 'mid-3'))->assertOk();
    $this->postJson('/webhooks/meta/messenger-verify', metaInboundWebhook('messenger', '97531', 'mid-4'))->assertOk();

    $contact = ContactProviderIdentity::query()->where('provider_contact_id', '97531')->firstOrFail()->contact;
    expect($contact->name)->toBe('Messenger 97531')
        ->and(Message::query()->whereIn('provider_message_id', ['mid-3', 'mid-4'])->count())->toBe(2)
        ->and(profileLookups('97531'))->toBe(1);
});

it('never renames a contact someone already named', function (): void {
    [, $account] = metaProfileChannel('messenger');
    $contact = Contact::query()->create(['workspace_id' => $account->workspace_id, 'name' => 'VIP Karim']);
    ContactProviderIdentity::query()->create([
        'workspace_id' => $account->workspace_id, 'contact_id' => $contact->id, 'channel_account_id' => $account->id,
        'provider' => 'messenger', 'provider_contact_id' => '11223', 'identity_type' => 'psid',
    ]);
    Http::fake(['graph.facebook.com/*' => Http::response(['first_name' => 'Someone'])]);

    $this->postJson('/webhooks/meta/messenger-verify', metaInboundWebhook('messenger', '11223', 'mid-5'))->assertOk();

    expect($contact->refresh()->name)->toBe('VIP Karim')
        ->and(profileLookups('11223'))->toBe(0);
});

/**
 * @return array{0: User, 1: Conversation}
 */
function humanAgentConversation(string $provider, DateTimeInterface $lastCustomerMessageAt): array
{
    [$user, $account] = metaProfileChannel($provider);
    $contact = Contact::query()->create(['workspace_id' => $account->workspace_id, 'name' => 'Nadia']);
    ContactProviderIdentity::query()->create([
        'workspace_id' => $account->workspace_id, 'contact_id' => $contact->id, 'channel_account_id' => $account->id,
        'provider' => $provider, 'provider_contact_id' => '55555', 'identity_type' => 'psid',
    ]);
    $conversation = Conversation::query()->create([
        'workspace_id' => $account->workspace_id, 'channel_account_id' => $account->id, 'provider' => $provider,
        'provider_conversation_id' => '55555', 'contact_id' => $contact->id, 'status' => 'open', 'labels' => [],
    ]);
    $inbound = Message::query()->create([
        'workspace_id' => $account->workspace_id, 'channel_account_id' => $account->id, 'provider' => $provider,
        'conversation_id' => $conversation->id, 'contact_id' => $contact->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'Is this in stock?', 'status' => 'received',
    ]);
    $inbound->forceFill(['created_at' => $lastCustomerMessageAt])->save();

    return [$user, $conversation];
}

it('tags a team reply with HUMAN_AGENT when the customer wrote more than 24 hours ago', function (string $provider): void {
    [$user, $conversation] = humanAgentConversation($provider, now()->subDays(2));
    Http::fake(['graph.facebook.com/*/messages' => Http::response(['message_id' => 'mid.out.1'])]);

    app(InboxService::class)->sendMessage($user, $conversation->id, 'Yes, back in stock today.');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/messages')
        && data_get($request->data(), 'messaging_type') === 'MESSAGE_TAG'
        && data_get($request->data(), 'tag') === 'HUMAN_AGENT'
        && data_get($request->data(), 'recipient.id') === '55555');
})->with(['messenger', 'instagram']);

it('sends a normal reply without a tag inside the 24 hour window', function (): void {
    [$user, $conversation] = humanAgentConversation('messenger', now()->subHours(3));
    Http::fake(['graph.facebook.com/*/messages' => Http::response(['message_id' => 'mid.out.2'])]);

    app(InboxService::class)->sendMessage($user, $conversation->id, 'Yes, in stock.');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/messages')
        && data_get($request->data(), 'message.text') === 'Yes, in stock.'
        && data_get($request->data(), 'tag') === null
        && data_get($request->data(), 'messaging_type') === null);
});
