<?php

use App\Models\User;
use App\Modules\Automations\Services\AutomationDispatcher;
use App\Modules\AutoReplies\Services\AutoReplyService;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\Inbox\Services\InboxService;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Jobs\ProcessChannelWebhookJob;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Models\ChannelWebhookEvent;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\WhatsAppCloud\Services\WhatsAppCloudDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

const EVENT_CUSTOMER = '8801711223344';

function eventAccount(): ChannelAccount
{
    $workspace = app(WorkspaceResolver::class)->current(User::factory()->create(['email_verified_at' => now()]));

    return ChannelAccount::query()->create([
        'workspace_id' => $workspace->id, 'provider' => 'whatsapp', 'name' => 'WhatsApp', 'status' => ChannelAccountStatus::Connected,
        'credentials' => ['access_token' => 'token'], 'webhook_verify_token' => 'verify', 'provider_account_id' => 'waba-1', 'provider_phone_id' => 'phone-1',
    ]);
}

function deliverWhatsAppMessage(ChannelAccount $account, array $message, ?AutoReplyService $autoReplies = null): void
{
    $payload = ['entry' => [['id' => 'waba-1', 'changes' => [['field' => 'messages', 'value' => [
        'messaging_product' => 'whatsapp',
        'metadata' => ['phone_number_id' => 'phone-1'],
        'messages' => [array_merge(['from' => EVENT_CUSTOMER, 'timestamp' => (string) time()], $message)],
    ]]]]]];

    app(WhatsAppCloudDriver::class)->handleWebhook(Request::create('/webhook', 'POST', $payload), $account);
    $event = ChannelWebhookEvent::query()->latest('id')->firstOrFail();

    (new ProcessChannelWebhookJob($event->id))->handle(app(ChannelManager::class), app(AutomationDispatcher::class), $autoReplies ?? app(AutoReplyService::class));
}

function silentAutoReplies(): AutoReplyService
{
    $autoReplies = Mockery::mock(AutoReplyService::class);
    $autoReplies->shouldNotReceive('replyToInbound');

    return $autoReplies;
}

function sendHello(ChannelAccount $account): Message
{
    deliverWhatsAppMessage($account, ['id' => 'wamid.hello', 'type' => 'text', 'text' => ['body' => 'Do you have red?']], Mockery::mock(AutoReplyService::class)->shouldIgnoreMissing());

    return Message::query()->where('provider_message_id', 'wamid.hello')->firstOrFail();
}

it('applies an edit to the original message without a new row or auto-reply', function () {
    $account = eventAccount();
    sendHello($account);

    deliverWhatsAppMessage($account, ['id' => 'wamid.edit', 'type' => 'edit', 'edit' => ['original_message_id' => 'wamid.hello', 'message' => ['type' => 'text', 'text' => ['body' => 'Do you have blue?']]]], silentAutoReplies());

    $original = Message::query()->where('provider_message_id', 'wamid.hello')->sole();
    expect($original->body)->toBe('Do you have blue?')
        ->and($original->payload['original_body'])->toBe('Do you have red?')
        ->and(Message::query()->count())->toBe(1);
});

it('stores an edit whose original is unknown as a normal text message', function () {
    $account = eventAccount();

    deliverWhatsAppMessage($account, ['id' => 'wamid.edit', 'type' => 'edit', 'edit' => ['original_message_id' => 'wamid.gone', 'message' => ['type' => 'text', 'text' => ['body' => 'New text']]]], Mockery::mock(AutoReplyService::class)->shouldIgnoreMissing());

    $message = Message::query()->sole();
    expect($message->type)->toBe('text')->and($message->body)->toBe('New text');
});

it('marks a revoked message as deleted', function () {
    $account = eventAccount();
    sendHello($account);

    deliverWhatsAppMessage($account, ['id' => 'wamid.revoke', 'type' => 'revoke', 'revoke' => ['original_message_id' => 'wamid.hello']], silentAutoReplies());

    expect(Message::query()->count())->toBe(1)
        ->and(Message::query()->sole()->payload['deleted_at'])->not->toBeNull();
});

it('adds, changes and removes a reaction on the target message', function () {
    $account = eventAccount();
    sendHello($account);
    $react = fn (string $emoji) => deliverWhatsAppMessage($account, ['id' => 'wamid.r'.md5($emoji), 'type' => 'reaction', 'reaction' => ['message_id' => 'wamid.hello', 'emoji' => $emoji]], silentAutoReplies());

    $react('❤️');
    expect(Message::query()->where('provider_message_id', 'wamid.hello')->sole()->payload['reactions'])->toBe([EVENT_CUSTOMER => '❤️']);

    $react('👍');
    expect(Message::query()->where('provider_message_id', 'wamid.hello')->sole()->payload['reactions'])->toBe([EVENT_CUSTOMER => '👍']);

    $react('');
    expect(Message::query()->where('provider_message_id', 'wamid.hello')->sole()->payload['reactions'])->toBe([])
        ->and(Message::query()->count())->toBe(1);
});

it('drops a reaction to an unknown message', function () {
    $account = eventAccount();

    deliverWhatsAppMessage($account, ['id' => 'wamid.r', 'type' => 'reaction', 'reaction' => ['message_id' => 'wamid.gone', 'emoji' => '❤️']], silentAutoReplies());

    expect(Message::query()->count())->toBe(0);
});

it('stores unsupported and system messages with a readable label and no auto-reply', function () {
    $account = eventAccount();

    deliverWhatsAppMessage($account, ['id' => 'wamid.u', 'type' => 'unsupported', 'errors' => [['code' => 131051]]], silentAutoReplies());
    deliverWhatsAppMessage($account, ['id' => 'wamid.s', 'type' => 'system', 'system' => ['body' => 'User A changed from 1 to 2', 'type' => 'user_changed_number']], silentAutoReplies());

    expect(Message::query()->where('type', 'unsupported')->sole()->body)->toContain('Unsupported message type')
        ->and(Message::query()->where('type', 'system')->sole()->body)->toBe('User A changed from 1 to 2');
});

it('hides leftover event rows and exposes edit, delete and reaction flags', function () {
    $account = eventAccount();
    $original = sendHello($account);
    $original->update(['payload' => array_merge($original->payload, ['reactions' => ['x' => '❤️'], 'edited_at' => now()->toIso8601String()])]);
    Message::query()->create(['workspace_id' => $account->workspace_id, 'conversation_id' => $original->conversation_id, 'channel_account_id' => $account->id, 'provider' => 'whatsapp', 'direction' => 'inbound', 'type' => 'reaction', 'payload' => ['type' => 'reaction'], 'status' => 'received', 'provider_message_id' => 'wamid.old']);

    $thread = (fn () => $this->threadPayload(Conversation::query()->sole()))->call(app(InboxService::class));

    expect($thread['messages'])->toHaveCount(1)
        ->and($thread['messages'][0]['reactions'])->toBe(['❤️'])
        ->and($thread['messages'][0]['is_edited'])->toBeTrue()
        ->and($thread['conversation']['last_message'])->toBe('Do you have red?');
});

it('applies stored event rows to their originals and is idempotent', function () {
    $account = eventAccount();
    $original = sendHello($account);
    Message::query()->create(['workspace_id' => $account->workspace_id, 'conversation_id' => $original->conversation_id, 'channel_account_id' => $account->id, 'provider' => 'whatsapp', 'direction' => 'inbound', 'type' => 'reaction', 'payload' => ['from' => EVENT_CUSTOMER, 'type' => 'reaction', 'reaction' => ['message_id' => 'wamid.hello', 'emoji' => '🔥']], 'status' => 'received', 'provider_message_id' => 'wamid.old']);

    $this->artisan('inbox:apply-whatsapp-events')->assertSuccessful();
    $this->artisan('inbox:apply-whatsapp-events')->assertSuccessful();

    expect(Message::query()->count())->toBe(1)
        ->and(Message::query()->sole()->payload['reactions'])->toBe([EVENT_CUSTOMER => '🔥']);
});
