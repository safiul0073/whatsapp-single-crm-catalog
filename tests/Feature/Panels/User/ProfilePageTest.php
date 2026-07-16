<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the WaPro user profile page without old blue portal styling', function (): void {
    $user = User::factory()->create([
        'name' => 'Marcus Reed',
        'email' => 'marcus@softivus.example',
    ]);

    $this->actingAs($user)
        ->get(route('user.profile.edit'))
        ->assertOk()
        ->assertSee('My Profile')
        ->assertSee('Your personal account', false)
        ->assertSee('class="app-card mt-6 p-5 sm:p-6"', false)
        ->assertSee('data-tab-group="profile"', false)
        ->assertSee('Personal details')
        ->assertSee('Two-factor authentication')
        ->assertSee('Active sessions')
        ->assertSee("localStorage.getItem('theme')", false)
        ->assertSee('data-action="toggle-theme"', false)
        ->assertDontSee('bg-brand-blue')
        ->assertDontSee('portal-input')
        ->assertDontSee('text-brand-blue')
        ->assertDontSee('to-blue-500');
});
