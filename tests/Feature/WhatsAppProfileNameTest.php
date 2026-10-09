<?php

use App\Models\User;
use App\Modules\Automations\Services\AutomationDispatcher;
use App\Modules\AutoReplies\Services\AutoReplyService;
use App\Modules\Contacts\Models\Contact;
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

function profileNameAccount(): ChannelAccount
{
    $workspace = app(WorkspaceResolver::class)->current(User::factory()->create(['email_verified_at' => now()]));

    return ChannelAccount::query()->create([
        'workspace_id' => $workspace->id, 'provider' => 'whatsapp', 'name' => 'WhatsApp', 'status' => ChannelAccountStatus::Connected,
        'credentials' => ['access_token' => 'token'], 'webhook_verify_token' => 'verify', 'provider_account_id' => 'waba-1', 'provider_phone_id' => 'phone-1',
    ]);
}

/** The shape Meta really sends: the sender's name is in value.contacts, not inside the message. */
function metaInboundPayload(ChannelAccount $account, string $from, ?string $name, string $messageId = 'wamid.1'): array
{
    $value = [
        'messaging_product' => 'whatsapp',
        'metadata' => ['phone_number_id' => $account->provider_phone_id],
        'messages' => [['from' => $from, 'id' => $messageId, 'type' => 'text', 'text' => ['body' => 'hello']]],
    ];

    if ($name !== null) {
        $value['contacts'] = [['profile' => ['name' => $name], 'wa_id' => $from]];
    }

    return ['entry' => [['id' => $account->provider_account_id, 'changes' => [['field' => 'messages', 'value' => $value]]]]];
}

function receiveMetaWebhook(ChannelAccount $account, array $payload): void
{
    app(WhatsAppCloudDriver::class)->handleWebhook(Request::create('/webhook', 'POST', $payload), $account);
    $event = ChannelWebhookEvent::query()->latest('id')->firstOrFail();

    (new ProcessChannelWebhookJob($event->id))->handle(app(ChannelManager::class), app(AutomationDispatcher::class), app(AutoReplyService::class));
}

it('names a new contact with the WhatsApp profile name from the contacts block', function () {
    $account = profileNameAccount();

    receiveMetaWebhook($account, metaInboundPayload($account, '8801711223344', 'Rahim Uddin'));

    expect(Contact::query()->where('workspace_id', $account->workspace_id)->sole()->name)->toBe('Rahim Uddin');
});

it('replaces a phone-number placeholder name when the person messages again', function () {
    $account = profileNameAccount();
    $contact = Contact::query()->create(['workspace_id' => $account->workspace_id, 'phone' => '+8801711223344', 'name' => '+8801711223344']);

    receiveMetaWebhook($account, metaInboundPayload($account, '8801711223344', 'Rahim Uddin'));

    expect($contact->fresh()->name)->toBe('Rahim Uddin')->and(Contact::query()->count())->toBe(1);
});

it('never overwrites a name someone typed', function () {
    $account = profileNameAccount();
    $contact = Contact::query()->create(['workspace_id' => $account->workspace_id, 'phone' => '+8801711223344', 'name' => 'VIP Rahim']);

    receiveMetaWebhook($account, metaInboundPayload($account, '8801711223344', 'Rahim Uddin'));

    expect($contact->fresh()->name)->toBe('VIP Rahim');
});

it('falls back to the phone number when WhatsApp sends no profile name', function () {
    $account = profileNameAccount();

    receiveMetaWebhook($account, metaInboundPayload($account, '8801711223344', null));

    expect(Contact::query()->where('workspace_id', $account->workspace_id)->sole()->name)->toBe('+8801711223344')
        ->and(app(WhatsAppCloudDriver::class)->processWebhook($account, metaInboundPayload($account, '8801711223344', 'Rahim Uddin'))['events'][0]['name'])->toBe('Rahim Uddin');
});
