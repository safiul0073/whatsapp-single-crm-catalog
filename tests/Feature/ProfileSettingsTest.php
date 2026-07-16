<?php

use App\Models\User;
use App\Modules\PlansSubscriptions\Enums\SubscriptionStatus;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\PlansSubscriptions\Models\Subscription;
use App\Modules\Workspaces\Enums\WorkspaceStatus;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function completeProfileSettingsOnboarding(User $user): void
{
    $workspace = Workspace::query()->create([
        'owner_id' => $user->id,
        'name' => $user->name.' Workspace',
        'slug' => 'profile-settings-'.$user->id,
        'status' => WorkspaceStatus::Active->value,
        'timezone' => config('app.timezone', 'UTC'),
        'settings' => [
            'category' => 'services',
            'team_size' => '2-5',
            'onboarding_completed_at' => now()->toIso8601String(),
        ],
    ]);

    $plan = Plan::query()->create([
        'name' => 'Profile Test Plan',
        'slug' => 'profile-test-plan-'.$user->id,
        'description' => 'Plan for profile settings tests',
        'price' => 0,
        'interval' => 'month',
        'limits' => [],
        'features' => [],
        'is_active' => true,
        'sort_order' => 1,
    ]);

    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active->value,
        'starts_at' => now(),
        'usage' => [],
    ]);
}

it('renders editable profile settings for authenticated users', function (): void {
    $user = User::factory()->create([
        'bio' => 'Handles customer conversations.',
        'timezone' => 'Asia/Dhaka',
        'locale' => 'bn',
    ]);
    completeProfileSettingsOnboarding($user);

    $this->actingAs($user)
        ->get(route('user.profile.edit'))
        ->assertOk()
        ->assertViewHas('currentLocale', 'bn')
        ->assertViewHas('currentTimezone', 'Asia/Dhaka')
        ->assertSee('Handles customer conversations.')
        ->assertSee('Asia/Dhaka')
        ->assertDontSee('Daily email digest')
        ->assertDontSee('Desktop notifications');
});

it('updates personal details independently', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    completeProfileSettingsOnboarding($user);

    $this->actingAs($user)
        ->put(route('user.profile.update'), [
            'section' => 'details',
            'name' => 'Taylor Morgan',
            'email' => 'taylor@example.com',
            'phone' => '+15550101010',
            'bio' => 'Usually online during support hours.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $user->refresh();

    expect($user->name)->toBe('Taylor Morgan')
        ->and($user->email)->toBe('taylor@example.com')
        ->and($user->phone)->toBe('+15550101010')
        ->and($user->bio)->toBe('Usually online during support hours.')
        ->and($user->email_verified_at)->toBeNull();
});

it('updates personal preferences', function (): void {
    $user = User::factory()->create();
    completeProfileSettingsOnboarding($user);

    $this->actingAs($user)
        ->put(route('user.profile.update'), [
            'section' => 'preferences',
            'locale' => 'bn',
            'timezone' => 'Europe/Paris',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $user->refresh();

    expect($user->locale)->toBe('bn')
        ->and($user->timezone)->toBe('Europe/Paris');
});

it('requires the current password before changing password', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('old-password'),
    ]);
    completeProfileSettingsOnboarding($user);

    $this->actingAs($user)
        ->from(route('user.profile.edit'))
        ->put(route('user.profile.update'), [
            'section' => 'security',
            'current_password' => 'wrong-password',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])
        ->assertRedirect(route('user.profile.edit'))
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('old-password', $user->refresh()->password))->toBeTrue();
});

it('stores uploaded avatar images as media and assigns them to the user', function (): void {
    Storage::fake('public');

    $user = User::factory()->create();
    completeProfileSettingsOnboarding($user);

    $this->actingAs($user)
        ->put(route('user.profile.update'), [
            'section' => 'avatar',
            'avatar_upload' => UploadedFile::fake()->image('avatar.jpg', 512, 512),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $user->refresh();

    expect($user->avatar)->not->toBeNull();

    $this->assertDatabaseHas('media', [
        'id' => $user->avatar,
        'type' => 'image',
        'uploaded_by' => $user->id,
    ]);
});

it('only allows verified contact methods for two-factor setup', function (): void {
    $user = User::factory()->create([
        'phone' => '+15550101010',
        'phone_verified_at' => null,
    ]);
    completeProfileSettingsOnboarding($user);

    $this->actingAs($user)
        ->post(route('user.two-factor.enable'), [
            'channel' => 'sms',
        ])
        ->assertSessionHasErrors('channel');

    expect($user->refresh()->hasOtpTwoFactorEnabled())->toBeFalse();
});

it('shows verified and unverified two-factor channel options', function (): void {
    $user = User::factory()->create([
        'phone' => '+15550101010',
        'phone_verified_at' => null,
    ]);
    completeProfileSettingsOnboarding($user);

    $this->actingAs($user)
        ->get(route('user.two-factor.setup'))
        ->assertOk()
        ->assertSee('Email')
        ->assertSee('Phone')
        ->assertSee('Verified')
        ->assertSee('Not verified');
});

it('enables phone two-factor when phone is verified', function (): void {
    $user = User::factory()->create([
        'phone' => '+15550101010',
        'phone_verified_at' => now(),
    ]);
    completeProfileSettingsOnboarding($user);

    $this->actingAs($user)
        ->post(route('user.two-factor.enable'), [
            'channel' => 'sms',
        ])
        ->assertRedirect(route('user.two-factor.setup'))
        ->assertSessionHas('2fa_setup_delivery');

    $this->actingAs($user)
        ->post(route('user.two-factor.enable'), [
            'code' => '123456',
        ])
        ->assertRedirect(route('user.profile.edit'));

    $user->refresh();

    expect($user->hasOtpTwoFactorEnabled())->toBeTrue()
        ->and($user->otp_two_factor_channel)->toBe('sms');
});
