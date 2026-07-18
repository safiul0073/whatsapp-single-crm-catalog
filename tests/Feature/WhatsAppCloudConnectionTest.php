<?php

use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\WhatsAppCloud\Services\WhatsAppCloudClient;
use App\Modules\WhatsAppCloud\Services\WhatsAppCloudDriver;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response;

it('returns helpful whatsapp connection validation errors before calling meta', function (): void {
    $client = Mockery::mock(WhatsAppCloudClient::class);
    $client->shouldNotReceive('phoneNumbers');

    $driver = new WhatsAppCloudDriver($client);

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

    $driver = new WhatsAppCloudDriver($client);
    $result = $driver->testConnection(new ChannelAccount([
        'provider' => 'whatsapp',
        'provider_account_id' => 'waba-123',
        'credentials' => ['access_token' => 'token'],
    ]));

    expect($result['ok'])->toBeFalse()
        ->and($result['error_code'])->toBe(100)
        ->and($result['error'])->toContain('Unsupported get request');
});
