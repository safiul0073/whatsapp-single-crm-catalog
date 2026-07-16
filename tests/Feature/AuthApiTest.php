<?php

use App\Models\User;
use App\Modules\AuthApi\Models\SocialAccount;
use App\Modules\AuthApi\Services\AuthChallengeService;
use App\Modules\NotificationTemplates\Models\NotificationTemplate;
use App\Modules\NotificationTemplates\Notifications\SendAutoNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;

uses(RefreshDatabase::class);

it('registers a user through the api', function (): void {
    Notification::fake();

    NotificationTemplate::query()->create([
        'slug' => 'welcome',
        'name' => 'Welcome',
        'channels' => ['email', 'in_app'],
        'variables' => ['user_name' => 'Name', 'login_url' => 'Login URL'],
        'email_subject' => 'Welcome to {{site_name}}',
        'email_body' => '<p>Hello {{user_name}}</p>',
        'in_app_title' => 'Welcome',
        'in_app_body' => 'Hello {{user_name}}',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/auth/register', [
        'first_name' => 'API',
        'last_name' => 'User',
        'email' => 'api@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'device_name' => 'iPhone',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', 'api@example.com')
        ->assertJsonStructure([
            'data' => [
                'token',
                'token_type',
                'user',
            ],
        ]);

    $user = User::query()->where('email', 'api@example.com')->first();

    expect($user)->not->toBeNull();

    Notification::assertSentTo($user, SendAutoNotification::class);
});

it('returns a two factor challenge when the user has two factor enabled', function (): void {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'twofactor@example.com',
        'password' => 'password123',
        'otp_two_factor_enabled' => true,
        'otp_two_factor_channel' => 'email',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
        'device_name' => 'Pixel 9',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.requires_two_factor', true)
        ->assertJsonPath('data.channel', 'email')
        ->assertJsonStructure([
            'data' => ['challenge_token', 'expires_in', 'channel', 'destination'],
        ]);
});

it('verifies a two factor challenge and issues a token', function (): void {
    $user = User::factory()->create([
        'password' => 'password123',
        'otp_two_factor_enabled' => true,
        'otp_two_factor_channel' => 'email',
    ]);

    $challenge = app(AuthChallengeService::class)->issue($user, 'Pixel 9', 'email', $user->email);

    $response = $this->postJson('/api/v1/auth/2fa/verify', [
        'challenge_token' => $challenge['challenge_token'],
        'otp' => '123456',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => ['token', 'token_type', 'user'],
        ]);
});

it('returns the authenticated user for sanctum tokens', function (): void {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.user.email', $user->email);
});

it('authenticates a mobile social login and links a social account', function (): void {
    Notification::fake();

    NotificationTemplate::query()->create([
        'slug' => 'welcome',
        'name' => 'Welcome',
        'channels' => ['email', 'in_app'],
        'variables' => ['user_name' => 'Name', 'login_url' => 'Login URL'],
        'email_subject' => 'Welcome to {{site_name}}',
        'email_body' => '<p>Hello {{user_name}}</p>',
        'in_app_title' => 'Welcome',
        'in_app_body' => 'Hello {{user_name}}',
        'is_active' => true,
    ]);

    Config::set('services.google', [
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'redirect' => 'http://localhost/api/v1/auth/social/google/callback',
    ]);

    $providerUser = new class implements SocialiteUserContract
    {
        public string $token = 'provider-access-token';

        public ?string $refreshToken = 'provider-refresh-token';

        public int $expiresIn = 3600;

        public function getId()
        {
            return 'google-user-1';
        }

        public function getNickname()
        {
            return null;
        }

        public function getName()
        {
            return 'Google User';
        }

        public function getEmail()
        {
            return 'google-user@example.com';
        }

        public function getAvatar()
        {
            return 'https://example.com/avatar.png';
        }
    };

    Socialite::shouldReceive('driver->stateless->userFromToken')
        ->once()
        ->with('provider-token')
        ->andReturn($providerUser);

    $response = $this->postJson('/api/v1/auth/social/google/mobile', [
        'access_token' => 'provider-token',
        'device_name' => 'Android App',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.user.email', 'google-user@example.com')
        ->assertJsonStructure([
            'data' => ['token', 'token_type', 'user'],
        ]);

    $user = User::query()->where('email', 'google-user@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->email_verified_at)->not->toBeNull();
    expect(SocialAccount::query()->where('provider', 'google')->where('provider_user_id', 'google-user-1')->exists())->toBeTrue();
    Notification::assertSentTo($user, SendAutoNotification::class);
});

it('marks existing users as email verified when they sign in with a matching social provider email', function (): void {
    $user = User::factory()->create([
        'email' => 'existing-social@example.com',
        'email_verified_at' => null,
    ]);

    Config::set('services.google', [
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'redirect' => 'http://localhost/api/v1/auth/social/google/callback',
    ]);

    $providerUser = new class implements SocialiteUserContract
    {
        public string $token = 'provider-access-token';

        public ?string $refreshToken = null;

        public int $expiresIn = 3600;

        public function getId()
        {
            return 'google-existing-user';
        }

        public function getNickname()
        {
            return null;
        }

        public function getName()
        {
            return 'Existing Social';
        }

        public function getEmail()
        {
            return 'existing-social@example.com';
        }

        public function getAvatar()
        {
            return null;
        }
    };

    Socialite::shouldReceive('driver->stateless->userFromToken')
        ->once()
        ->with('provider-token')
        ->andReturn($providerUser);

    $this->postJson('/api/v1/auth/social/google/mobile', [
        'access_token' => 'provider-token',
        'device_name' => 'Android App',
    ])->assertOk();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});
