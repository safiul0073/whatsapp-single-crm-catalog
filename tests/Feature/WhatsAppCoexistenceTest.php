<?php

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\WhatsAppCloud\Services\WhatsAppCloudClient;
use App\Modules\WhatsAppCloud\Services\WhatsAppCloudDriver;
use App\Modules\WhatsAppCloud\Services\WhatsAppMessagePayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

function coexistenceChannel(): ChannelAccount
{
    $workspace = app(WorkspaceResolver::class)->current(User::factory()->create());

    return ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'whatsapp',
        'name' => 'Coexistence Line',
        'status' => 'connected',
        'provider_account_id' => 'waba-coexist',
        'provider_phone_id' => 'phone-coexist',
        'provider_display_id' => '8801700000000',
        'credentials' => ['access_token' => 'secret-token'],
        'webhook_verify_token' => 'verify-token',
    ]);
}

function coexistenceDriver(): WhatsAppCloudDriver
{
    return new WhatsAppCloudDriver(app(WhatsAppCloudClient::class), new WhatsAppMessagePayloadBuilder);
}

function coexistenceWebhook(array $value): Request
{
    return Request::create('/webhooks/channels/whatsapp', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
        'entry' => [[
            'id' => 'waba-coexist',
            'changes' => [[
                'field' => 'messages',
                'value' => array_merge(['metadata' => ['phone_number_id' => 'phone-coexist']], $value),
            ]],
        ]],
    ], JSON_THROW_ON_ERROR));
}

it('backfills coexistence chat history into conversations', function (): void {
    $channel = coexistenceChannel();

    coexistenceDriver()->handleWebhook(coexistenceWebhook([
        'history' => [[
            'metadata' => ['phone_number' => '8801811111111'],
            'messages' => [
                [
                    'id' => 'wamid.history.inbound',
                    'from' => '8801811111111',
                    'timestamp' => '1750000000',
                    'type' => 'text',
                    'text' => ['body' => 'Do you have this in stock?'],
                ],
                [
                    'id' => 'wamid.history.outbound',
                    'from' => '8801700000000',
                    'to' => '8801811111111',
                    'timestamp' => '1750000100',
                    'type' => 'text',
                    'text' => ['body' => 'Yes, we do.'],
                ],
            ],
        ]],
    ]), $channel);

    $contact = Contact::query()->where('phone', '+8801811111111')->firstOrFail();
    $conversation = Conversation::query()->where('contact_id', $contact->id)->firstOrFail();

    expect(Message::query()->where('conversation_id', $conversation->id)->count())->toBe(2)
        ->and(Message::query()->where('provider_message_id', 'wamid.history.inbound')->value('direction'))->toBe('inbound')
        ->and(Message::query()->where('provider_message_id', 'wamid.history.outbound')->value('direction'))->toBe('outbound');
});

it('preserves original timestamps when replaying coexistence history', function (): void {
    $channel = coexistenceChannel();

    coexistenceDriver()->handleWebhook(coexistenceWebhook([
        'history' => [[
            'metadata' => ['phone_number' => '8801811111111'],
            'messages' => [[
                'id' => 'wamid.history.dated',
                'from' => '8801811111111',
                'timestamp' => '1750000000',
                'type' => 'text',
                'text' => ['body' => 'Older message'],
            ]],
        ]],
    ]), $channel);

    expect(Message::query()->where('provider_message_id', 'wamid.history.dated')->value('created_at')->timestamp)
        ->toBe(1750000000);
});

it('does not duplicate history messages when Meta resends a batch', function (): void {
    $channel = coexistenceChannel();
    $payload = [
        'history' => [[
            'metadata' => ['phone_number' => '8801811111111'],
            'messages' => [[
                'id' => 'wamid.history.repeat',
                'from' => '8801811111111',
                'timestamp' => '1750000000',
                'type' => 'text',
                'text' => ['body' => 'Same message'],
            ]],
        ]],
    ];

    coexistenceDriver()->handleWebhook(coexistenceWebhook($payload), $channel);
    coexistenceDriver()->handleWebhook(coexistenceWebhook($payload), $channel);

    expect(Message::query()->where('provider_message_id', 'wamid.history.repeat')->count())->toBe(1);
});

it('mirrors messages sent from the WhatsApp Business app as outbound', function (): void {
    $channel = coexistenceChannel();

    coexistenceDriver()->handleWebhook(coexistenceWebhook([
        'message_echoes' => [[
            'id' => 'wamid.echo.1',
            'from' => '8801700000000',
            'to' => '8801822222222',
            'timestamp' => '1750000200',
            'type' => 'text',
            'text' => ['body' => 'Sent from my phone'],
        ]],
    ]), $channel);

    $message = Message::query()->where('provider_message_id', 'wamid.echo.1')->firstOrFail();

    expect($message->direction)->toBe('outbound')
        ->and($message->body)->toBe('Sent from my phone')
        ->and(Contact::query()->where('phone', '+8801822222222')->exists())->toBeTrue();
});

it('creates contacts from a coexistence contact sync', function (): void {
    $channel = coexistenceChannel();

    coexistenceDriver()->handleWebhook(coexistenceWebhook([
        'contacts' => [[
            'wa_id' => '8801833333333',
            'profile' => ['name' => 'Rahim Traders'],
        ]],
    ]), $channel);

    expect(Contact::query()->where('phone', '+8801833333333')->value('name'))->toBe('Rahim Traders');
});
