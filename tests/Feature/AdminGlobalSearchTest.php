<?php

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('redirects guests to login on admin global search', function () {
    $this->get(route('admin.global-search', ['q' => 'settings']))
        ->assertRedirect(route('admin.login'));
});

it('returns search results matching pages for admin users', function () {
    $admin = Admin::factory()->create();
    Permission::findOrCreate('settings.view', 'admin');
    $admin->givePermissionTo('settings.view');

    $response = $this->actingAs($admin, 'admin')
        ->getJson(route('admin.global-search', ['q' => 'settings']))
        ->assertOk();

    $data = $response->json();
    expect($data)->toHaveKey('groups');

    $groups = collect($data['groups']);
    $pagesGroup = $groups->firstWhere('module', 'Pages');

    expect($pagesGroup)->not->toBeNull()
        ->and($pagesGroup['icon'])->toBe('ph-compass')
        ->and($pagesGroup['results'])->not->toBeEmpty();

    $settingsResult = collect($pagesGroup['results'])->firstWhere('title', 'General Settings');
    expect($settingsResult)->not->toBeNull()
        ->and($settingsResult['url'])->toBe(route('admin.settings.index'));
});
