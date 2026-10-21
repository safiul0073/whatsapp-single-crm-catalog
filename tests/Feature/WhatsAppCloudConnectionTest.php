<?php

use App\Models\User;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\WhatsAppCloud\Services\ChannelSetupService;
use App\Modules\WhatsAppCloud\Services\WhatsAppCloudClient;
use App\Modules\WhatsAppCloud\Services\WhatsAppCloudDriver;
use App\Modules\WhatsAppCloud\Services\WhatsAppMessagePayloadBuilder;
use App\Modules\Workspaces\Models\Workspace;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

it('returns helpful whatsapp connection validation errors before calling meta', function (): void {
    $client = Mockery::mock(WhatsAppCloudClient::class);
    $client->shouldNotReceive('phoneNumbers');

    $driver = new WhatsAppCloudDriver($client, new WhatsAppMessagePayloadBuilder);

    $missingWaba = $driver->testConnection(new ChannelAccount([
        'provider' => 'whatsapp',
        'credentials' => ['access_token' => 'token'],
    ]));
    $missingToken = $driver->testConnection(new ChannelAccount([
        'provider' => 'whatsapp',
        'provider_account_id' => 'waba-123',
        'credentials' => [],
    ]));

    expect($missingWaba['ok'])->toBeFalse()
        ->and($missingWaba['error'])->toContain('Business Account ID is missing')
        ->and($missingToken['ok'])->toBeFalse()
        ->and($missingToken['error'])->toContain('access token is missing');
});

it('returns the meta error message when whatsapp connection test fails', function (): void {
    $client = Mockery::mock(WhatsAppCloudClient::class);
    $client->shouldReceive('phoneNumbers')
        ->once()
        ->with('waba-123', 'token')
        ->andReturn(new Response(new PsrResponse(404, ['Content-Type' => 'application/json'], json_encode([
            'error' => [
                'message' => 'Unsupported get request. Object with ID waba-123 does not exist, cannot be loaded due to missing permissions, or does not support this operation.',
                'code' => 100,
            ],
        ], JSON_THROW_ON_ERROR))));

    $driver = new WhatsAppCloudDriver($client, new WhatsAppMessagePayloadBuilder);
    $result = $driver->testConnection(new ChannelAccount([
        'provider' => 'whatsapp',
        'provider_account_id' => 'waba-123',
        'credentials' => ['access_token' => 'token'],
    ]));

    expect($result['ok'])->toBeFalse()
        ->and($result['error_code'])->toBe(100)
        ->and($result['error'])->toContain('Unsupported get request');
});

it('prevents syncing a disconnected whatsapp channel without access token', function (): void {
    $service = app(ChannelSetupService::class);
    $user = User::factory()->create();
    $workspace = Workspace::query()->create([
        'name' => 'Sync Test',
        'slug' => 'sync-test-'.Str::uuid(),
        'owner_id' => $user->id,
        'status' => 'active',
    ]);
    $workspace->members()->attach($user, ['role' => 'owner']);

    ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'whatsapp',
        'name' => 'Test WhatsApp',
        'status' => ChannelAccountStatus::Disconnected->value,
        'credentials' => null,
    ]);

    $result = $service->sync($user);

    expect($result['ok'])->toBeFalse()
        ->and($result['error'])->toContain('Channel is disconnected');
});

it('allows removing a whatsapp channel configuration', function (): void {
    $service = app(ChannelSetupService::class);
    $user = User::factory()->create();
    $workspace = Workspace::query()->create([
        'name' => 'Delete Test',
        'slug' => 'delete-test-'.Str::uuid(),
        'owner_id' => $user->id,
        'status' => 'active',
    ]);
    $workspace->members()->attach($user, ['role' => 'owner']);

    $channel = ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'whatsapp',
        'name' => 'To Delete',
        'status' => ChannelAccountStatus::Disconnected->value,
        'credentials' => null,
    ]);

    $service->destroy($user, $channel);

    expect(ChannelAccount::query()->where('id', $channel->id)->exists())->toBeFalse();
});
