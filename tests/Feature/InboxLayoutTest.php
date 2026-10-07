<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;

uses(RefreshDatabase::class);

it('renders the inbox in the viewport constrained layout', function (): void {
    $this->withoutMiddleware()->actingAs(User::factory()->create())
        ->get(route('user.inbox.index'))
        ->assertSuccessful()
        ->assertSee('app-shell--full-height', false)
        ->assertSee('flex h-dvh min-h-0 flex-col overflow-hidden', false)
        ->assertSee('flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden p-0', false)
        ->assertDontSee('px-4 py-6 sm:px-6 lg:px-8', false);
});

it('keeps standard user layouts padded and scrollable', function (): void {
    $this->actingAs(User::factory()->create());
    $html = Blade::render('<x-layouts.user title="Dashboard"><p>Dashboard content</p></x-layouts.user>');
    expect($html)->toContain('min-h-screen overflow-x-hidden', 'px-4 py-6 sm:px-6 lg:px-8')
        ->not->toContain('app-shell--full-height', 'flex h-dvh');
});

it('places the impersonation banner in flow only for viewport constrained layouts', function (): void {
    view()->share('isImpersonating', true);
    $inline = Blade::render('<x-ui.impersonation-banner :in-flow="true" />');
    $standard = Blade::render('<x-ui.impersonation-banner />');
    expect($inline)->toContain('relative shrink-0')->not->toContain('fixed top-0', 'class="h-10"');
    expect($standard)->toContain('fixed top-0', 'class="h-10"');
    view()->share('isImpersonating', false);
});
