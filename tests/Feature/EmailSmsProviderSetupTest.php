<?php

use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Http\Middleware\PanelAccess;
use App\Models\User;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\MarketingChannels\Contracts\MarketingChannelDriver;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

function emailSmsWorkspaceFor(User $user)
{
    return app(WorkspaceResolver::class)->current($user);
}

function withoutPanelMiddleware($test): mixed
{
    return $test->withoutMiddleware([
        Authorize::class,
        EnsureEmailIsVerified::class,
        EnsureTwoFactorAuthenticated::class,
        PanelAccess::class,
        EnsureOnboardingComplete::class,
    ]);
}

it('shows Email provider selector and dynamic credential sections', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    withoutPanelMiddleware($this)
        ->actingAs($user)
        ->get(route('user.email.index'))
        ->assertOk()
        ->assertSee('name="mail_mailer"', false)
        ->assertSee('value="log"', false)
        ->assertSee('data-mailer-section="smtp"', false)
        ->assertSee('data-mailer-section="mailgun"', false)
        ->assertSee('Write email payloads to the application log for safe testing.')
        ->assertSee('No additional credentials are required for this provider.');
});

it('stores only SMTP email credentials and preserves blank saved password on update', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    emailSmsWorkspaceFor($user);

    withoutPanelMiddleware($this)
        ->actingAs($user)
        ->post(route('user.email.store'), [
            'name' => 'SMTP Email',
            'provider_display_id' => 'noreply@example.com',
            'mail_mailer' => 'smtp',
            'mail_from_name' => 'WaPro',
            'mail_host' => 'smtp.example.com',
            'mail_port' => 587,
            'mail_encryption' => 'tls',
            'mail_username' => 'smtp-user',
            'mail_password' => 'smtp-secret',
        ])
        ->assertRedirect();

    $channel = ChannelAccount::query()->where('provider', 'email')->firstOrFail();

    expect($channel->credentials)->toMatchArray([
        'mail_mailer' => 'smtp',
        'mail_host' => 'smtp.example.com',
        'mail_password' => 'smtp-secret',
    ])->not->toHaveKey('mailgun_secret');

    withoutPanelMiddleware($this)
        ->actingAs($user)
        ->put(route('user.email.update', $channel), [
            'name' => 'SMTP Email Updated',
            'provider_display_id' => 'noreply@example.com',
            'mail_mailer' => 'smtp',
            'mail_from_name' => 'WaPro',
            'mail_host' => 'smtp2.example.com',
            'mail_port' => 465,
            'mail_encryption' => 'ssl',
            'mail_username' => 'smtp-user-2',
            'mail_password' => '',
        ])
        ->assertRedirect();

    $channel->refresh();

    expect($channel->credentials)->toMatchArray([
        'mail_mailer' => 'smtp',
        'mail_host' => 'smtp2.example.com',
        'mail_password' => 'smtp-secret',
    ]);
});

it('switches Email provider and drops stale credentials', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = emailSmsWorkspaceFor($user);

    $channel = ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'email',
        'name' => 'SMTP Email',
        'status' => 'connected',
        'provider_display_id' => 'noreply@example.com',
        'credentials' => [
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.example.com',
            'mail_password' => 'smtp-secret',
        ],
    ]);

    withoutPanelMiddleware($this)
        ->actingAs($user)
        ->put(route('user.email.update', $channel), [
            'name' => 'Mailgun Email',
            'provider_display_id' => 'noreply@example.com',
            'mail_mailer' => 'mailgun',
            'mail_from_name' => 'WaPro',
            'mailgun_domain' => 'mg.example.com',
            'mailgun_secret' => 'mailgun-secret',
            'mailgun_endpoint' => 'api.mailgun.net',
        ])
        ->assertRedirect();

    $channel->refresh();

    expect($channel->credentials)->toMatchArray([
        'mail_mailer' => 'mailgun',
        'mailgun_domain' => 'mg.example.com',
        'mailgun_secret' => 'mailgun-secret',
    ])->not->toHaveKey('mail_host')
        ->not->toHaveKey('mail_password');
});

it('stores Sendmail and Log email channels without provider credential fields', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    emailSmsWorkspaceFor($user);

    withoutPanelMiddleware($this)
        ->actingAs($user)
        ->post(route('user.email.store'), [
            'name' => 'Log Email',
            'provider_display_id' => 'noreply@example.com',
            'mail_mailer' => 'log',
        ])
        ->assertRedirect();

    $channel = ChannelAccount::query()->where('provider', 'email')->firstOrFail();

    expect($channel->credentials)->toBe(['mail_mailer' => 'log']);
    expect($channel->status->value)->toBe('connected')
        ->and($channel->connected_at)->not->toBeNull();

    withoutPanelMiddleware($this)
        ->actingAs($user)
        ->put(route('user.email.update', $channel), [
            'name' => 'Sendmail Email',
            'provider_display_id' => 'noreply@example.com',
            'mail_mailer' => 'sendmail',
        ])
        ->assertRedirect();

    $channel->refresh();

    expect($channel->credentials)->toBe(['mail_mailer' => 'sendmail']);
});

it('keeps email channel in error status when the connection test fails', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    emailSmsWorkspaceFor($user);

    app(ChannelManager::class)->register(new class implements MarketingChannelDriver
    {
        public function provider(): string
        {
            return 'email';
        }

        public function sendMessage(ChannelAccount $account, array $recipient, array $payload): array
        {
            return ['ok' => false, 'status' => 'failed'];
        }

        public function verifyWebhook(Request $request, ChannelAccount $account): bool
        {
            return false;
        }

        public function handleWebhook(Request $request, ChannelAccount $account): array
        {
            return ['ok' => true];
        }

        public function syncTemplates(ChannelAccount $account): array
        {
            return ['ok' => true, 'synced' => 0];
        }

        public function getHealthStatus(ChannelAccount $account): array
        {
            return ['connected' => false];
        }

        public function testConnection(ChannelAccount $account): array
        {
            return ['ok' => false, 'provider' => 'email', 'error' => 'SMTP denied'];
        }

        public function processWebhook(ChannelAccount $account, array $payload): array
        {
            return ['ok' => true, 'events' => []];
        }

        public function getCapabilities(): array
        {
            return ['Campaigns'];
        }

        public function validateCampaign(ChannelAccount $account, Campaign $campaign): void {}
    });

    withoutPanelMiddleware($this)
        ->actingAs($user)
        ->post(route('user.email.store'), [
            'name' => 'Broken Email',
            'provider_display_id' => 'noreply@example.com',
            'mail_mailer' => 'log',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    $channel = ChannelAccount::query()->where('provider', 'email')->firstOrFail();

    expect($channel->status->value)->toBe('error')
        ->and($channel->connected_at)->toBeNull()
        ->and($channel->settings['last_error'])->toBe('SMTP denied');
});

it('shows SMS provider selector and dynamic credential sections', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    withoutPanelMiddleware($this)
        ->actingAs($user)
        ->get(route('user.sms.index'))
        ->assertOk()
        ->assertSee('name="sms_provider"', false)
        ->assertSee('value="log"', false)
        ->assertSee('data-sms-provider-section="twilio"', false)
        ->assertSee('data-sms-provider-section="vonage"', false)
        ->assertSee('Write SMS payloads to the application log for safe testing.')
        ->assertSee('No additional credentials are required for this provider.');
});

it('stores only Twilio SMS credentials and preserves blank saved auth token on update', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    emailSmsWorkspaceFor($user);

    withoutPanelMiddleware($this)
        ->actingAs($user)
        ->post(route('user.sms.store'), [
            'name' => 'Twilio SMS',
            'provider_display_id' => '+12345678901',
            'sms_provider' => 'twilio',
            'twilio_sid' => 'twilio-sid',
            'twilio_auth_token' => 'twilio-secret',
        ])
        ->assertRedirect();

    $channel = ChannelAccount::query()->where('provider', 'sms')->firstOrFail();

    expect($channel->credentials)->toMatchArray([
        'sms_provider' => 'twilio',
        'sms_from_number' => '+12345678901',
        'twilio_sid' => 'twilio-sid',
        'twilio_auth_token' => 'twilio-secret',
    ])->not->toHaveKey('vonage_api_secret');

    withoutPanelMiddleware($this)
        ->actingAs($user)
        ->put(route('user.sms.update', $channel), [
            'name' => 'Twilio SMS Updated',
            'provider_display_id' => '+12345678901',
            'sms_provider' => 'twilio',
            'twilio_sid' => 'twilio-sid-2',
            'twilio_auth_token' => '',
        ])
        ->assertRedirect();

    $channel->refresh();

    expect($channel->credentials)->toMatchArray([
        'sms_provider' => 'twilio',
        'twilio_sid' => 'twilio-sid-2',
        'twilio_auth_token' => 'twilio-secret',
    ]);
});

it('switches SMS provider and drops stale credentials', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = emailSmsWorkspaceFor($user);

    $channel = ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'sms',
        'name' => 'Twilio SMS',
        'status' => 'connected',
        'provider_display_id' => '+12345678901',
        'credentials' => [
            'sms_provider' => 'twilio',
            'sms_from_number' => '+12345678901',
            'twilio_sid' => 'twilio-sid',
            'twilio_auth_token' => 'twilio-secret',
        ],
    ]);

    withoutPanelMiddleware($this)
        ->actingAs($user)
        ->put(route('user.sms.update', $channel), [
            'name' => 'Vonage SMS',
            'provider_display_id' => '+12345678901',
            'sms_provider' => 'vonage',
            'vonage_api_key' => 'vonage-key',
            'vonage_api_secret' => 'vonage-secret',
        ])
        ->assertRedirect();

    $channel->refresh();

    expect($channel->credentials)->toMatchArray([
        'sms_provider' => 'vonage',
        'vonage_api_key' => 'vonage-key',
        'vonage_api_secret' => 'vonage-secret',
    ])->not->toHaveKey('twilio_sid')
        ->not->toHaveKey('twilio_auth_token');
});

it('stores Log SMS channel without gateway credential fields', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    emailSmsWorkspaceFor($user);

    withoutPanelMiddleware($this)
        ->actingAs($user)
        ->post(route('user.sms.store'), [
            'name' => 'Log SMS',
            'provider_display_id' => '+12345678901',
            'sms_provider' => 'log',
        ])
        ->assertRedirect();

    $channel = ChannelAccount::query()->where('provider', 'sms')->firstOrFail();

    expect($channel->credentials)->toBe([
        'sms_provider' => 'log',
        'sms_from_number' => '+12345678901',
    ]);
    expect($channel->status->value)->toBe('connected')
        ->and($channel->connected_at)->not->toBeNull();
});
