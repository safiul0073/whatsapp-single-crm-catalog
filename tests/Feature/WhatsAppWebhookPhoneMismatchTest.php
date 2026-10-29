<?php

use App\Models\User;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Models\ChannelWebhookEvent;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('ignores webhooks for a previously connected WhatsApp phone number', function (): void {
    $workspace = app(WorkspaceResolver::class)->current(User::factory()->create());

    ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'whatsapp',
        'name' => 'New Line',
        'status' => 'connected',
        'connected_at' => now(),
        'provider_account_id' => 'waba-1',
        'provider_phone_id' => 'phone-new',
        'credentials' => ['access_token' => 'secret-token'],
        'webhook_verify_token' => 'verify-token',
        'webhook_code' => 'code-1',
    ]);

    $this->postJson('/webhooks/channels/whatsapp/code-1', [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => 'waba-1',
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'metadata' => ['phone_number_id' => 'phone-old'],
                    'messages' => [['from' => '15555550123', 'id' => 'wamid.old', 'type' => 'text', 'text' => ['body' => 'Hi']]],
                ],
            ]],
        ]],
    ])->assertOk();

    expect(ChannelWebhookEvent::query()->count())->toBe(0);
});
