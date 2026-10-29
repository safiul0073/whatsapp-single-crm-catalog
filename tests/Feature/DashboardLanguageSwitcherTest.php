<?php

use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Models\User;
use App\Modules\Languages\Models\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Language::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'is_active' => true, 'is_default' => true, 'sort_order' => 1]);
    Language::query()->create(['code' => 'bn', 'name' => 'Bangla', 'native_name' => 'বাংলা', 'direction' => 'ltr', 'is_active' => true, 'is_default' => false, 'sort_order' => 2]);
});

it('shows a language switcher in the dashboard footer', function (): void {
    $this->withoutMiddleware([EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class])
        ->actingAs(User::factory()->create())
        ->get(route('user.dashboard'))
        ->assertOk()
        ->assertSee('name="locale" value="bn"', false)
        ->assertSee('name="locale" value="en"', false);
});

it('switches the dashboard footer to Bangla after choosing the language', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('locale.switch'), ['locale' => 'bn'])->assertRedirect();

    $this->withoutMiddleware([EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class])
        ->actingAs($user)
        ->get(route('user.dashboard'))
        ->assertOk()
        ->assertSee('সর্বস্বত্ব সংরক্ষিত');
});
