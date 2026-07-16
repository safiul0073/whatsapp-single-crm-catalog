<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('renders the commerce product and catalog workflows without browser errors', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Permission::findOrCreate('commerce.view', 'web');
    Permission::findOrCreate('commerce.manage', 'web');
    $user->givePermissionTo(['commerce.view', 'commerce.manage']);
    $this->actingAs($user);

    visit(route('user.commerce.products.index'))
        ->assertSee('Commerce')
        ->assertSee('Products')
        ->assertSee('Orders')
        ->assertSee('Meta Catalog')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    visit([
        route('user.commerce.products.create'),
        route('user.commerce.catalog'),
        route('user.commerce.orders.index'),
    ])->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

it('keeps the product editor usable on a mobile viewport', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Permission::findOrCreate('commerce.manage', 'web');
    $user->givePermissionTo('commerce.manage');
    $this->actingAs($user);

    visit(route('user.commerce.products.create'))
        ->on()->mobile()
        ->assertSee('Create product')
        ->assertSee('Product details')
        ->assertSee('Create draft and continue')
        ->assertNoJavaScriptErrors();
});
