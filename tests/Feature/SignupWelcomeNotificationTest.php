<?php

use App\Enums\NotificationTemplateSlug;
use App\Models\User;
use App\Modules\AuthApi\Models\SocialAccount;
use App\Modules\NotificationTemplates\Models\NotificationLog;
use App\Modules\NotificationTemplates\Models\NotificationTemplate;
use App\Modules\NotificationTemplates\Notifications\SendAutoNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;

uses(RefreshDatabase::class);

function createWelcomeTemplate(bool $isActive = true): NotificationTemplate
{
    return NotificationTemplate::query()->create([
        'slug' => NotificationTemplateSlug::WELCOME->value,
        'name' => 'Welcome',
        'channels' => ['email', 'in_app'],
        'variables' => ['user_name' => 'Name', 'login_url' => 'Login URL'],
        'email_subject' => 'Welcome to {{site_name}}',
        'email_body' => '<p>Hello {{user_name}}</p>',
        'in_app_title' => 'Welcome',
        'in_app_body' => 'Hello {{user_name}}',
        'is_active' => $isActive,
    ]);
}

it('sends the template welcome notification when a user registers on the web', function (): void {
    Notification::fake();
    createWelcomeTemplate();

    $response = $this->post('/register', [
        'first_name' => 'Web',
        'last_name' => 'User',
        'email' => 'web@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $user = User::query()->where('email', 'web@example.com')->first();

    $response->assertRedirect(route('onboarding.workspace'));
    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, SendAutoNotification::class);
});

it('does not send the welcome notification again for an existing social account login', function (): void {
    Notification::fake();
    createWelcomeTemplate();

    Config::set('services.google', [
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'redirect' => 'http://localhost/api/v1/auth/social/google/callback',
    ]);

    $user = User::factory()->create([
        'name' => 'Existing User',
        'email' => 'existing-social@example.com',
    ]);

    SocialAccount::query()->create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_user_id' => 'google-existing-user',
    ]);

    $providerUser = new class implements SocialiteUserContract
    {
        public string $token = 'provider-access-token';

        public ?string $refreshToken = 'provider-refresh-token';

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
            return 'Existing User';
        }

        public function getEmail()
        {
            return 'existing-social@example.com';
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

    $response->assertSuccessful();
    Notification::assertNothingSent();
});

it('keeps signup working when the welcome template is unavailable', function (?bool $isActive): void {
    if ($isActive !== null) {
        createWelcomeTemplate($isActive);
    }

    $response = $this->post('/register', [
        'first_name' => 'Fallback',
        'last_name' => 'User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect(route('onboarding.workspace'));

    expect(NotificationLog::query()->where('template_slug', NotificationTemplateSlug::WELCOME->value)->count())->toBe(0);
})->with([
    'missing template' => [null],
    'inactive template' => [false],
]);
