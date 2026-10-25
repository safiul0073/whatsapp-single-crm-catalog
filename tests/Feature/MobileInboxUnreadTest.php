<?php

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Http\Controllers\Api\MobileInboxController;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\Inbox\Services\InboxService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

function mobileInboxContext(): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);

    return [$user, $workspace];
}

function mobileInboxConversation(int $workspaceId, string $provider = 'whatsapp', array $attributes = []): Conversation
{
    $contact = Contact::query()->create([
        'workspace_id' => $workspaceId,
        'name' => 'Customer '.uniqid(),
        'phone' => '+1555555'.random_int(1000, 9999),
    ]);

    return Conversation::query()->create(array_merge([
        'workspace_id' => $workspaceId,
        'provider' => $provider,
        'provider_conversation_id' => $contact->phone,
        'contact_id' => $contact->id,
        'status' => 'open',
        'last_message_at' => now(),
        'labels' => [],
    ], $attributes));
}

function mobileInboxMessage(Conversation $conversation, string $direction = 'inbound', string $type = 'text'): Message
{
    return Message::query()->create([
        'workspace_id' => $conversation->workspace_id,
        'provider' => $conversation->provider,
        'conversation_id' => $conversation->id,
        'contact_id' => $conversation->contact_id,
        'direction' => $direction,
        'type' => $type,
        'body' => 'Hello '.uniqid(),
        'status' => $direction === 'inbound' ? 'received' : 'sent',
    ]);
}

function mobileInboxList(User $user, array $filters = []): array
{
    return app(InboxService::class)->conversationsForUser($user, $filters);
}

it('counts unread inbound messages per conversation and clears them on mark read', function (): void {
    [$user, $workspace] = mobileInboxContext();
    $conversation = mobileInboxConversation($workspace->id);
    mobileInboxMessage($conversation);
    mobileInboxMessage($conversation);
    mobileInboxMessage($conversation, 'outbound');

    $before = mobileInboxList($user);

    expect($before['conversations'][0]['unread_count'])->toBe(2)
        ->and($before['conversations'][0]['is_unread'])->toBeTrue()
        ->and($before['counts']['unread'])->toBe(1);

    $result = app(InboxService::class)->markRead($user, $conversation->id);

    expect($result)->toBe(['ok' => true, 'unread_count' => 0]);

    $after = mobileInboxList($user);

    expect($after['conversations'][0]['unread_count'])->toBe(0)
        ->and($after['conversations'][0]['is_unread'])->toBeFalse()
        ->and($after['counts']['unread'])->toBe(0);
});

it('becomes unread again when a new inbound message arrives after reading', function (): void {
    [$user, $workspace] = mobileInboxContext();
    $conversation = mobileInboxConversation($workspace->id);
    mobileInboxMessage($conversation);
    app(InboxService::class)->markRead($user, $conversation->id);

    mobileInboxMessage($conversation);

    $list = mobileInboxList($user);

    expect($list['conversations'][0]['unread_count'])->toBe(1)
        ->and($list['counts']['unread'])->toBe(1);
});

it('filters the unread tab to conversations that really have unread messages', function (): void {
    [$user, $workspace] = mobileInboxContext();
    $unread = mobileInboxConversation($workspace->id);
    mobileInboxMessage($unread);
    $read = mobileInboxConversation($workspace->id);
    mobileInboxMessage($read);
    app(InboxService::class)->markRead($user, $read->id);
    $outboundOnly = mobileInboxConversation($workspace->id);
    mobileInboxMessage($outboundOnly, 'outbound');

    $ids = collect(mobileInboxList($user, ['status' => 'unread'])['conversations'])->pluck('id')->all();

    expect($ids)->toBe([$unread->id]);
});

it('ignores provider event messages when counting unread', function (): void {
    [$user, $workspace] = mobileInboxContext();
    $conversation = mobileInboxConversation($workspace->id);
    mobileInboxMessage($conversation, 'inbound', 'reaction');

    $list = mobileInboxList($user);

    expect($list['conversations'][0]['unread_count'])->toBe(0)
        ->and($list['counts']['unread'])->toBe(0);
});

it('flags a closed whatsapp reply window and leaves other providers alone', function (): void {
    [$user, $workspace] = mobileInboxContext();
    $expired = mobileInboxConversation($workspace->id, 'whatsapp', ['session_expires_at' => now()->subHour()]);
    $open = mobileInboxConversation($workspace->id, 'whatsapp', ['session_expires_at' => now()->addHours(3)]);
    $messenger = mobileInboxConversation($workspace->id, 'messenger', ['session_expires_at' => now()->subHour()]);

    $byId = collect(mobileInboxList($user)['conversations'])->keyBy('id');

    expect($byId[$expired->id]['window_expired'])->toBeTrue()
        ->and($byId[$expired->id]['window_expires_at'])->not->toBeNull()
        ->and($byId[$open->id]['window_expired'])->toBeFalse()
        ->and($byId[$messenger->id]['window_expired'])->toBeFalse();
});

it('paginates the conversation list', function (): void {
    [$user, $workspace] = mobileInboxContext();
    foreach (range(1, 12) as $index) {
        mobileInboxConversation($workspace->id, 'whatsapp', ['last_message_at' => now()->subMinutes($index)]);
    }

    $first = mobileInboxList($user, ['per_page' => 10]);
    $second = mobileInboxList($user, ['per_page' => 10, 'page' => 2]);

    expect($first['conversations'])->toHaveCount(10)
        ->and($first['meta'])->toBe(['page' => 1, 'per_page' => 10, 'has_more' => true])
        ->and($second['conversations'])->toHaveCount(2)
        ->and($second['meta']['has_more'])->toBeFalse()
        ->and(collect($first['conversations'])->pluck('id')->intersect(collect($second['conversations'])->pluck('id')))->toBeEmpty();
});

it('registers the mobile mark read route', function (): void {
    $route = Route::getRoutes()->match(Request::create('api/v1/mobile/workspaces/5/inbox/conversations/42/read', 'POST'));

    expect($route->getActionMethod())->toBe('markRead')
        ->and($route->getController())->toBeInstanceOf(MobileInboxController::class);
});
